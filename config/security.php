<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CAPTCHA
    |--------------------------------------------------------------------------
    |
    | Anti-abuse challenge shown on the public auth forms, the contact form and
    | the admin panel login.
    |
    | Left unset, "enabled" is on everywhere except the test suite, and the
    | driver resolves to turnstile when both Turnstile keys are present and to
    | the legacy math challenge otherwise. That way an environment with no keys
    | still gets a working, solvable form rather than an unsolvable one.
    |
    */

    'captcha' => [
        'enabled' => env('CAPTCHA_ENABLED'),

        /*
         * "turnstile" — Cloudflare Turnstile; invisible for most visitors,
         *               accessible, and actually adversarial to solvers.
         * "math"      — the in-house "a + b" challenge, kept only as the
         *               fallback for environments with no Turnstile keys.
         */
        'driver' => env('CAPTCHA_DRIVER'),

        'turnstile' => [
            'site_key' => env('TURNSTILE_SITE_KEY'),
            'secret_key' => env('TURNSTILE_SECRET_KEY'),
            'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
            'timeout' => (int) env('TURNSTILE_TIMEOUT', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | The site is served through Cloudflare, so without this every request
    | arrives with REMOTE_ADDR set to a Cloudflare edge address and
    | $request->ip() returns that instead of the visitor. Per-IP rate limiting
    | would then bucket the whole internet into a handful of keys — one
    | attacker would lock out every visitor.
    |
    | Trusting the CIDR list rather than "*" keeps this safe even if the origin
    | is reachable directly: a request that did not come through Cloudflare has
    | a REMOTE_ADDR outside these ranges, so its forwarded headers are ignored
    | and $request->ip() falls back to the real peer.
    |
    | Refresh from https://www.cloudflare.com/ips/ — last synced 2026-08-01.
    |
    */

    'trusted_proxies' => [
        // IPv4
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',

        // IPv6
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ],

    /*
    |--------------------------------------------------------------------------
    | Content moderation
    |--------------------------------------------------------------------------
    |
    | Google Play's User Generated Content policy requires an in-app reporting
    | mechanism and a process behind it.
    |
    | auto_hide_threshold is not a verdict — it bounds how long plainly
    | objectionable content stays up when nobody is at a desk. A moderator can
    | restore it in one click. Reports in ContentReport::urgentReasons() bypass
    | the threshold entirely and hide on the first report.
    |
    */

    'moderation' => [
        'auto_hide_threshold' => (int) env('MODERATION_AUTO_HIDE_THRESHOLD', 3),
        'notify_address' => env('MODERATION_NOTIFY_ADDRESS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account deletion
    |--------------------------------------------------------------------------
    |
    | How long an emailed deletion link stays usable. Short enough that a stale
    | link in an old mailbox is not a standing risk; long enough that someone
    | who requests deletion at night can act on it the next day.
    |
    */

    'account_deletion' => [
        'token_hours' => (int) env('ACCOUNT_DELETION_TOKEN_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Play (Trusted Web Activity)
    |--------------------------------------------------------------------------
    |
    | Used by the /.well-known/assetlinks.json route. The fingerprint is the
    | SHA-256 of the PLAY APP SIGNING key (Play Console → Setup → App signing),
    | not the local upload keystore — Google re-signs every upload. Comma
    | separate to list more than one (e.g. while rotating, or to also trust the
    | upload key for local testing).
    |
    */

    'play' => [
        'package_name' => env('PLAY_PACKAGE_NAME', 'com.grinmuzik.app'),
        'app_fingerprint' => env('PLAY_APP_FINGERPRINT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication rate limits
    |--------------------------------------------------------------------------
    |
    | Two dimensions, because they stop different attacks:
    |
    |   per_email — many passwords against ONE account (credential stuffing).
    |   per_ip    — one password against MANY accounts (password spraying).
    |               Without this, every fresh email is a fresh key and a
    |               spraying run from a single host is effectively unlimited.
    |
    | Decays are in minutes.
    |
    */

    'rate_limits' => [
        'login' => [
            'per_email' => (int) env('RATE_LIMIT_LOGIN_PER_EMAIL', 5),
            'per_ip' => (int) env('RATE_LIMIT_LOGIN_PER_IP', 20),
            'decay_minutes' => (int) env('RATE_LIMIT_LOGIN_DECAY', 1),
        ],

        'admin_login' => [
            'per_ip' => (int) env('RATE_LIMIT_ADMIN_LOGIN_PER_IP', 10),
            'decay_minutes' => (int) env('RATE_LIMIT_ADMIN_LOGIN_DECAY', 1),
        ],

        'register' => [
            'per_ip' => (int) env('RATE_LIMIT_REGISTER_PER_IP', 5),
            'decay_minutes' => (int) env('RATE_LIMIT_REGISTER_DECAY', 10),
        ],

        // Requesting a reset link. Low, because each one sends mail from our
        // domain — flooding it is both an attack on the user and on our
        // sending reputation.
        'password_reset_request' => [
            'per_ip' => (int) env('RATE_LIMIT_PASSWORD_RESET_REQUEST_PER_IP', 5),
            'decay_minutes' => (int) env('RATE_LIMIT_PASSWORD_RESET_REQUEST_DECAY', 10),
        ],

        // Submitting the reset form. Guards against walking the token space.
        'password_reset' => [
            'per_ip' => (int) env('RATE_LIMIT_PASSWORD_RESET_PER_IP', 10),
            'decay_minutes' => (int) env('RATE_LIMIT_PASSWORD_RESET_DECAY', 10),
        ],

        'contact' => [
            'per_ip' => (int) env('RATE_LIMIT_CONTACT_PER_IP', 5),
            'decay_minutes' => (int) env('RATE_LIMIT_CONTACT_DECAY', 10),
        ],

        // Generous: a person legitimately finding several bad tracks in one
        // session should not be silenced, and duplicate reports are collapsed
        // rather than counted.
        'content_report' => [
            'per_ip' => (int) env('RATE_LIMIT_CONTENT_REPORT_PER_IP', 20),
            'decay_minutes' => (int) env('RATE_LIMIT_CONTENT_REPORT_DECAY', 10),
        ],

        // Public and unauthenticated, and each request sends mail to an address
        // the requester chose — so it is worth keeping tight.
        'account_deletion' => [
            'per_ip' => (int) env('RATE_LIMIT_ACCOUNT_DELETION_PER_IP', 5),
            'decay_minutes' => (int) env('RATE_LIMIT_ACCOUNT_DELETION_DECAY', 30),
        ],
    ],

];
