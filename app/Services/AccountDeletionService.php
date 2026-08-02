<?php

namespace App\Services;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Performs account deletion and describes, in one place, exactly what that
 * means.
 *
 * The disclosure lists below are not decoration: Google Play requires the
 * public deletion page to state what is deleted and what is retained, and the
 * Data safety form has to agree with it. Keeping the copy next to the code that
 * does the deleting is the only way they stay in step — a disclosure that
 * drifts from behaviour is worse than none, because it is a false promise.
 *
 * The actual cascade is enforced by foreign keys, not here. See the migrations:
 * tracks, albums, playlists, comments, likes, follows and the artist profile are
 * cascadeOnDelete; donations, play history, page views, contact messages and
 * notification events are nullOnDelete and survive without an owner.
 */
class AccountDeletionService
{
    /**
     * What is destroyed. Mirrors the cascadeOnDelete foreign keys.
     *
     * @return array<int, string>
     */
    public function deletedItems(): array
    {
        return [
            'Your profile, name, email address and password.',
            'Your artist profile, if you have one.',
            'Every track and album you have uploaded, including the audio files and artwork.',
            'Your playlists, likes, follows and comments.',
            'Your push notification subscriptions.',
        ];
    }

    /**
     * What survives, and why. Mirrors the nullOnDelete foreign keys.
     *
     * Being specific here matters — "some data may be retained" is exactly the
     * kind of vagueness that fails a Data safety review.
     *
     * @return array<int, string>
     */
    public function retainedItems(): array
    {
        return [
            'Donation and payment records are kept, with your name and account removed, because we are required to retain financial records for tax and accounting purposes.',
            'Aggregate play counts and chart positions remain, as they are totals that identify no one.',
            'Support messages you sent us are kept, with your account unlinked, so the conversation history stays coherent for our team.',
            'Server and security logs are kept for a short period and then expire on their own schedule.',
        ];
    }

    /**
     * Delete the account.
     *
     * Wrapped in a transaction so a failure part-way through cannot leave a
     * half-deleted account — a user whose tracks are gone but whose login still
     * works is worse than one whose deletion failed cleanly and can retry.
     */
    public function delete(User $user, ?AccountDeletionRequest $request = null): void
    {
        $userId = $user->id;

        DB::transaction(function () use ($user, $request): void {
            if ($request) {
                $request->forceFill([
                    'status' => AccountDeletionRequest::STATUS_COMPLETED,
                    'completed_at' => now(),
                ])->save();
            }

            // Any other live request for this account is now moot.
            AccountDeletionRequest::query()
                ->where('user_id', $user->id)
                ->where('status', AccountDeletionRequest::STATUS_PENDING)
                ->update(['status' => AccountDeletionRequest::STATUS_CANCELLED]);

            $user->delete();
        });

        // Deliberately logs the id and not the email: the point of the exercise
        // is that the address is gone.
        Log::info('Account deleted following a verified request.', ['user_id' => $userId]);
    }
}
