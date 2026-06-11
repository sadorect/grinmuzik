<?php

namespace App\Http\Controllers;

use App\Models\ArtistProfile;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeConnectController extends Controller
{
    /**
     * Initiate Stripe Express Connect onboarding for the authenticated artist.
     * Only accessible when payments are enabled by admin.
     */
    public function onboard(Request $request): RedirectResponse
    {
        abort_unless(SiteSetting::paymentsEnabled(), 403, 'Payments are currently disabled.');

        $artist = $request->user()->artistProfile;

        abort_if($artist === null, 403, 'No artist profile found.');

        $stripeSecret = config('cashier.secret');
        if (! filled($stripeSecret)) {
            Log::error('Stripe API key (STRIPE_SECRET) is not configured.');

            return redirect()->route('artist.dashboard')
                ->with('error', 'Payout onboarding is temporarily unavailable. Please try again later.');
        }

        $stripe = new StripeClient($stripeSecret);

        try {
            // Create a new Express account if none exists yet
            if (! filled($artist->stripe_connect_account_id)) {
                $account = $stripe->accounts->create([
                    'type'  => 'express',
                    'email' => $request->user()->email,
                    'capabilities' => [
                        'transfers' => ['requested' => true],
                    ],
                    'business_type' => 'individual',
                    'metadata' => [
                        'artist_profile_id' => $artist->id,
                        'user_id'           => $request->user()->id,
                    ],
                ]);

                $artist->update([
                    'stripe_connect_account_id' => $account->id,
                    'stripe_connect_status'     => 'pending',
                ]);
            }

            $accountLink = $stripe->accountLinks->create([
                'account'     => $artist->stripe_connect_account_id,
                'refresh_url' => route('artist.connect.onboard'),
                'return_url'  => route('artist.connect.return'),
                'type'        => 'account_onboarding',
            ]);

            return redirect()->away($accountLink->url);
        } catch (ApiErrorException $e) {
            Log::error('Stripe Connect onboarding failed', [
                'artist_profile_id' => $artist->id,
                'message'           => $e->getMessage(),
            ]);

            return redirect()->route('artist.dashboard')
                ->with('error', 'Could not start payout onboarding. Please try again.');
        }
    }

    /**
     * Stripe redirects the artist back here after completing (or abandoning) onboarding.
     */
    public function handleReturn(Request $request): RedirectResponse
    {
        abort_unless(SiteSetting::paymentsEnabled(), 403, 'Payments are currently disabled.');

        $artist = $request->user()->artistProfile;

        abort_if($artist === null || ! filled($artist->stripe_connect_account_id), 403);

        $stripeSecret = config('cashier.secret');
        if (! filled($stripeSecret)) {
            Log::error('Stripe API key (STRIPE_SECRET) is not configured.');

            return redirect()->route('artist.dashboard')
                ->with('error', 'Payout verification is temporarily unavailable. Please try again later.');
        }

        $stripe = new StripeClient($stripeSecret);

        try {
            $account = $stripe->accounts->retrieve($artist->stripe_connect_account_id);

            $status = $account->charges_enabled ? 'active' : 'restricted';

            $artist->update(['stripe_connect_status' => $status]);

            $message = $status === 'active'
                ? 'Your payout account is ready. You can now receive donations in your preferred currency.'
                : 'Stripe needs a little more information — check your email to complete setup.';

            return redirect()->route('artist.dashboard')->with('success', $message);
        } catch (ApiErrorException $e) {
            Log::error('Stripe Connect return verification failed', [
                'artist_profile_id' => $artist->id,
                'message'           => $e->getMessage(),
            ]);

            return redirect()->route('artist.dashboard')
                ->with('error', 'Could not verify your payout account status. Please try again.');
        }
    }

    /**
     * Sync an artist's Connect account status with Stripe (called from admin or via webhook).
     * Returns the resolved status string.
     */
    public static function syncStatus(ArtistProfile $artist): string
    {
        if (! filled($artist->stripe_connect_account_id)) {
            return 'not_connected';
        }

        $stripeSecret = config('cashier.secret');
        if (! filled($stripeSecret)) {
            Log::error('Stripe API key (STRIPE_SECRET) is not configured — Connect status sync skipped.');

            return 'not_connected';
        }

        $stripe = new StripeClient($stripeSecret);

        try {
            $account = $stripe->accounts->retrieve($artist->stripe_connect_account_id);
            $status  = $account->charges_enabled ? 'active' : 'restricted';
        } catch (ApiErrorException) {
            $status = 'restricted';
        }

        $artist->update(['stripe_connect_status' => $status]);

        return $status;
    }
}
