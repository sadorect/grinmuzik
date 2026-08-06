<?php

namespace App\Observers;

use App\Models\ArtistLedgerEntry;
use App\Models\Donation;
use App\Support\PlayBilling;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Credits the artist's ledger the moment a purchase completes.
 *
 * At the model layer on purpose. A donation reaches 'completed' from the
 * Stripe webhook today and will also from the Play purchase verifier; putting
 * this in either controller would mean the other silently skips the ledger,
 * and a missing credit is money an artist is never told they earned.
 *
 * Stripe destination charges pay the artist directly, so those purchases carry
 * no platform liability and are recorded at zero provider fee only when the
 * money genuinely passed through the platform. Play always creates a debt,
 * because Google pays the developer alone.
 */
class DonationObserver
{
    public function updated(Donation $donation): void
    {
        // Only the transition into 'completed' earns anything.
        if (! $donation->wasChanged('status') || $donation->status !== 'completed') {
            return;
        }

        $this->credit($donation);
    }

    public function created(Donation $donation): void
    {
        // Play purchases are verified server-side before the row is written,
        // so they can arrive already completed rather than transitioning.
        if ($donation->status === 'completed') {
            $this->credit($donation);
        }
    }

    private function credit(Donation $donation): void
    {
        if ($donation->artist_profile_id === null) {
            return;
        }

        try {
            $split = PlayBilling::split((float) $donation->amount, (string) $donation->provider);

            // The unique index on (donation_id, type) is the real guard against
            // double-crediting a replayed webhook; firstOrCreate keeps the
            // happy path from relying on catching an exception.
            DB::transaction(function () use ($donation, $split): void {
                ArtistLedgerEntry::query()->firstOrCreate(
                    [
                        'donation_id' => $donation->getKey(),
                        'type' => ArtistLedgerEntry::TYPE_CREDIT,
                    ],
                    [
                        'artist_profile_id' => $donation->artist_profile_id,
                        'provider' => $donation->provider,
                        'currency' => $donation->currency,
                        'gross_amount' => $split['gross'],
                        'provider_fee' => $split['provider_fee'],
                        'platform_fee' => $split['platform_fee'],
                        'net_amount' => $split['net'],
                        'description' => sprintf(
                            '%s via %s%s',
                            $donation->type === 'unlock' ? 'Track unlock' : 'Gift',
                            $donation->provider,
                            $donation->track_id ? " (track #{$donation->track_id})" : '',
                        ),
                    ],
                );
            });
        } catch (Throwable $exception) {
            // Never fail the purchase because the bookkeeping failed — the
            // listener has already been charged by then. Report it so the
            // missing credit can be replayed.
            report($exception);
        }
    }
}
