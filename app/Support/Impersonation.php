<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * "Sign in as" for the super admin.
 *
 * Every rule about who may sign in as whom lives in denialReason(), and both
 * the admin buttons and start() read it, so the rules cannot drift apart.
 *
 * State is two session keys: who the real admin is, and where to send them
 * back to. Nothing is written to the users table.
 */
class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public const RETURN_KEY = 'impersonation_return_url';

    /**
     * True while start()/stop() swap the signed-in user, so the Login
     * listener does not record a sign-in that the user never made.
     */
    private static bool $switching = false;

    public static function isSwitching(): bool
    {
        return self::$switching;
    }

    public function isActive(): bool
    {
        try {
            return session()->has(self::SESSION_KEY);
        } catch (Throwable) {
            return false;
        }
    }

    public function impersonator(): ?User
    {
        if (! $this->isActive()) {
            return null;
        }

        return User::find(session(self::SESSION_KEY));
    }

    /**
     * Why $actor may not sign in as $target, or null when they may.
     */
    public function denialReason(?User $actor, ?User $target): ?string
    {
        if (! $actor || ! $target) {
            return 'You need to be signed in.';
        }

        if ($this->isActive()) {
            return 'You are already signed in as someone else. Return to your own account first.';
        }

        if (! $actor->isSuperAdmin()) {
            return 'Only the super admin can sign in as other users.';
        }

        if ($actor->is($target)) {
            return 'That is your own account.';
        }

        if ($target->isAdmin()) {
            return 'Admin accounts cannot be signed in as.';
        }

        return null;
    }

    public function canImpersonate(?User $actor, ?User $target): bool
    {
        return $this->denialReason($actor, $target) === null;
    }

    /**
     * @throws AuthorizationException
     */
    public function start(User $actor, User $target, ?string $returnUrl = null): void
    {
        if ($reason = $this->denialReason($actor, $target)) {
            throw new AuthorizationException($reason);
        }

        // Recorded while the admin is still the signed-in user, so the row's
        // actor is the admin and its subject is the account they entered.
        app(AuditLogger::class)->record('impersonation.started', $target, [
            'target_user_id' => $target->getKey(),
        ], $target->name);

        $this->switchTo($target);

        session()->put(self::SESSION_KEY, $actor->getKey());
        session()->put(self::RETURN_KEY, $returnUrl);
    }

    /**
     * Return to the admin's own account. Gives back the URL to send them to,
     * or null when nobody was being impersonated.
     */
    public function stop(): ?string
    {
        if (! $this->isActive()) {
            return null;
        }

        $admin = $this->impersonator();
        $target = Auth::guard('web')->user();
        $returnUrl = session(self::RETURN_KEY);

        session()->forget([self::SESSION_KEY, self::RETURN_KEY]);

        if (! $admin) {
            // The admin account vanished mid-session; never leave the visitor
            // signed in as the artist with no way back.
            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();

            return route('home');
        }

        $this->switchTo($admin);

        if ($target instanceof User) {
            app(AuditLogger::class)->record('impersonation.stopped', $target, [
                'target_user_id' => $target->getKey(),
            ], $target->name);
        }

        return $returnUrl ?: route('home');
    }

    private function switchTo(User $user): void
    {
        self::$switching = true;

        try {
            // login() migrates the session id, so the swap also defeats
            // session fixation. The stored password hash belongs to the
            // previous user; drop it so AuthenticateSession re-stores the
            // right one instead of signing the new user out.
            session()->forget('password_hash_web');
            Auth::guard('web')->login($user);
            session()->regenerateToken();
        } finally {
            self::$switching = false;
        }
    }
}
