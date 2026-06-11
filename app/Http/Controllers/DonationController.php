<?php

namespace App\Http\Controllers;

use App\Models\ArtistProfile;
use App\Models\Donation;
use App\Models\SiteSetting;
use App\Models\Track;
use App\Support\ArtistActivityNotifier;
use App\Support\CurrencyHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class DonationController extends Controller
{
    /**
     * Create a Stripe PaymentIntent for unlocking or tipping.
     *
     * The donation is charged in the donor's chosen currency (defaulting to
     * the platform default, NGN). If the artist has an active Stripe Connect
     * account, the charge is routed there via a destination charge so Stripe
     * handles any FX conversion to the artist's payout currency.
     */
    public function createIntent(Request $request, Track $track): JsonResponse
    {
        abort_unless(SiteSetting::paymentsEnabled(), 403, 'Payments are currently disabled.');

        $platformCurrency = SiteSetting::platformCurrency();

        $request->validate([
            'type'     => ['required', 'in:unlock,tip'],
            'amount'   => ['required_if:type,tip', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'in:' . implode(',', array_keys(CurrencyHelper::SUPPORTED))],
            'message'  => ['nullable', 'string', 'max:280'],
        ]);

        $type     = $request->input('type');
        $user     = $request->user();
        $currency = CurrencyHelper::normalize($request->input('currency', $platformCurrency));
        $artist   = $track->artistProfile;

        abort_if($artist === null, 422, 'Track has no associated artist.');

        if ($type === 'unlock') {
            abort_if(! $track->requires_donation, 400, 'Track does not require a donation.');

            $alreadyUnlocked = Donation::where('user_id', $user->id)
                ->where('track_id', $track->id)
                ->where('type', 'unlock')
                ->where('status', 'completed')
                ->exists();

            if ($alreadyUnlocked) {
                return response()->json(['already_unlocked' => true]);
            }

            $amount = (float) $track->donation_amount;
        } else {
            abort_if(! $track->canReceiveGifts(), 400, 'Artist gifts are not available for this track.');

            $amount = (float) $request->input('amount', CurrencyHelper::minimum($currency));

            $minimum = CurrencyHelper::minimum($currency);
            abort_if($amount < $minimum, 422, "Minimum donation is {$minimum} {$currency}.");
        }

        $amountSmallest = CurrencyHelper::toSmallestUnit($amount, $currency);

        $stripeSecret = config('cashier.secret');
        if (! filled($stripeSecret)) {
            Log::error('Stripe API key (STRIPE_SECRET) is not configured.');

            return response()->json(
                ['error' => 'Payment processing is temporarily unavailable. Please try again later.'],
                503,
            );
        }

        $stripe = new StripeClient($stripeSecret);

        $intentParams = [
            'amount'   => $amountSmallest,
            'currency' => strtolower($currency),
            'metadata' => [
                'user_id'    => $user->id,
                'track_id'   => $track->id,
                'artist_id'  => $artist->id,
                'type'       => $type,
            ],
            'automatic_payment_methods' => ['enabled' => true],
        ];

        // Route funds to artist's connected account when available
        if ($artist->hasStripeConnect()) {
            $feePercent = SiteSetting::platformFeePercent();
            $platformFee = (int) round($amountSmallest * ($feePercent / 100));

            $intentParams['transfer_data']          = ['destination' => $artist->stripe_connect_account_id];
            $intentParams['application_fee_amount'] = max(0, $platformFee);
        }

        try {
            $intent = $stripe->paymentIntents->create($intentParams);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe PaymentIntent creation failed', [
                'track_id' => $track->id,
                'message'  => $e->getMessage(),
            ]);

            return response()->json(
                ['error' => 'Payment processing is temporarily unavailable. Please try again later.'],
                503,
            );
        }

        Donation::create([
            'user_id'                  => $user->id,
            'artist_profile_id'        => $artist->id,
            'track_id'                 => $track->id,
            'amount'                   => $amount,
            'currency'                 => $currency,
            'payout_currency'          => $artist->effectiveCurrency(),
            'stripe_payment_intent_id' => $intent->id,
            'type'                     => $type,
            'status'                   => 'pending',
            'message'                  => $request->input('message'),
        ]);

        return response()->json(['client_secret' => $intent->client_secret]);
    }

    /**
     * Handle Stripe webhook events.
     */
    public function webhook(Request $request): \Illuminate\Http\Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('cashier.webhook.secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature failed', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        match ($event->type) {
            'payment_intent.succeeded'       => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed'  => $this->handlePaymentFailed($event->data->object),
            default                          => null,
        };

        return response('Webhook handled', 200);
    }

    private function handlePaymentSucceeded(object $intent): void
    {
        $donation = Donation::where('stripe_payment_intent_id', $intent->id)->first();

        if (! $donation || $donation->status === 'completed') {
            return;
        }

        $donation->update(['status' => 'completed']);

        $artist = ArtistProfile::find($donation->artist_profile_id);

        if (! $artist) {
            return;
        }

        $artist->increment('total_donations', $donation->amount);
        $artist->refresh();

        $notifier = app(ArtistActivityNotifier::class);
        $notifier->sendDonationReceived($donation->fresh(['artist.user', 'track']));
        $notifier->sendDonationMilestoneIfReached($artist->fresh('user'));
    }

    private function handlePaymentFailed(object $intent): void
    {
        Donation::where('stripe_payment_intent_id', $intent->id)
            ->update(['status' => 'failed']);
    }
}
