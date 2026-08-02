<?php

namespace App\Support;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rate limiting for the authentication forms.
 *
 * These are Livewire/Volt forms, so `->middleware('throttle:...')` on the route
 * only limits how often the PAGE may be fetched — the actual submissions go to
 * /livewire/update and never touch the route's middleware. Every guard has to
 * run inside the component action instead, which is what this class is for.
 *
 * Two dimensions, because they stop different attacks and neither catches the
 * other's:
 *
 *   byEmail — many passwords against ONE account (credential stuffing).
 *   byIp    — one password against MANY accounts (password spraying). Without
 *             it, every fresh email is a fresh key and a spraying run from a
 *             single host is effectively unlimited.
 */
class AuthThrottle
{
    /**
     * Guard a login attempt on both dimensions.
     *
     * @throws ValidationException
     */
    public static function ensureLoginAllowed(string $email, string $errorKey = 'form.email'): void
    {
        $config = config('security.rate_limits.login');

        self::ensure(
            self::ipKey('login'),
            (int) $config['per_ip'],
            (int) $config['decay_minutes'],
            $errorKey,
        );

        self::ensure(
            self::emailKey('login', $email),
            (int) $config['per_email'],
            (int) $config['decay_minutes'],
            $errorKey,
        );
    }

    /**
     * Record a failed login against both dimensions.
     */
    public static function recordFailedLogin(string $email): void
    {
        $config = config('security.rate_limits.login');
        $decay = (int) $config['decay_minutes'] * 60;

        RateLimiter::hit(self::ipKey('login'), $decay);
        RateLimiter::hit(self::emailKey('login', $email), $decay);
    }

    /**
     * Clear a successful login from both dimensions.
     *
     * The IP counter is cleared too, so a shared address (an office, a mobile
     * carrier NAT) is not slowly poisoned by one person's typos.
     */
    public static function clearLogin(string $email): void
    {
        RateLimiter::clear(self::ipKey('login'));
        RateLimiter::clear(self::emailKey('login', $email));
    }

    /**
     * Guard any other per-IP auth action: registration, reset requests, the
     * reset form itself.
     *
     * @throws ValidationException
     */
    public static function ensureAllowed(string $action, string $errorKey, ?int $maxAttempts = null, ?int $decayMinutes = null): void
    {
        $config = config("security.rate_limits.{$action}", []);

        $max = $maxAttempts ?? (int) ($config['per_ip'] ?? 10);
        $decay = $decayMinutes ?? (int) ($config['decay_minutes'] ?? 1);

        self::ensure(self::ipKey($action), $max, $decay, $errorKey);

        RateLimiter::hit(self::ipKey($action), $decay * 60);
    }

    /**
     * @throws ValidationException
     */
    protected static function ensure(string $key, int $maxAttempts, int $decayMinutes, string $errorKey): void
    {
        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            $errorKey => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected static function ipKey(string $action): string
    {
        return $action.'|ip|'.request()->ip();
    }

    protected static function emailKey(string $action, string $email): string
    {
        return $action.'|email|'.Str::transliterate(Str::lower($email));
    }
}
