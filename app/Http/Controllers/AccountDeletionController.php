<?php

namespace App\Http\Controllers;

use App\Models\AccountDeletionRequest;
use App\Services\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountDeletionController extends Controller
{
    /**
     * Complete a deletion confirmed from the account's mailbox.
     *
     * Deliberately a POST behind a confirmation screen rather than a plain GET
     * on the emailed link: mail clients and security scanners routinely prefetch
     * links, and a GET that deletes would let a scanner destroy an account
     * nobody ever clicked.
     */
    public function confirm(string $token): mixed
    {
        $deletionRequest = AccountDeletionRequest::findPendingByToken($token);

        if (! $deletionRequest || ! $deletionRequest->user) {
            return response()->view('account-deletion-invalid', [], 410);
        }

        return response()->view('account-deletion-confirm', [
            'token' => $token,
            'email' => $deletionRequest->email,
            'service' => app(AccountDeletionService::class),
        ]);
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $deletionRequest = AccountDeletionRequest::findPendingByToken($token);

        if (! $deletionRequest || ! $deletionRequest->user) {
            return redirect()->route('account.deletion')
                ->with('status', 'That deletion link is no longer valid. Please request a new one.');
        }

        $user = $deletionRequest->user;

        // If the person happens to be signed in as the account being deleted,
        // end the session first — otherwise the request finishes holding a
        // session pointing at a user id that no longer exists.
        if (Auth::check() && Auth::id() === $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        app(AccountDeletionService::class)->delete($user, $deletionRequest);

        return redirect()->route('home')
            ->with('status', 'Your account and its data have been permanently deleted.');
    }
}
