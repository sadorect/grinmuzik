<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $connectSources = array_merge([
            "'self'",
            'https://api.stripe.com',
        ], $this->configuredConnectOrigins());

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://js.stripe.com",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://fonts.bunny.net",
            'connect-src ' . implode(' ', array_unique($connectSources)),
            "frame-src https://js.stripe.com https://hooks.stripe.com",
            "media-src 'self' blob: https:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }

    /**
     * @return array<int, string>
     */
    protected function configuredConnectOrigins(): array
    {
        $candidates = [
            config('filesystems.disks.r2_private.endpoint'),
            config('filesystems.disks.r2_public.endpoint'),
            config('filesystems.disks.r2_public.url'),
            config('filesystems.disks.s3.endpoint'),
            config('filesystems.disks.s3.url'),
        ];

        $origins = [];

        foreach ($candidates as $candidate) {
            $origin = $this->normalizeOrigin($candidate);

            if ($origin !== null) {
                $origins[] = $origin;
            }
        }

        return array_values(array_unique($origins));
    }

    protected function normalizeOrigin(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || blank($parts['scheme'] ?? null) || blank($parts['host'] ?? null)) {
            return null;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];

        if (filled($parts['port'] ?? null)) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }
}
