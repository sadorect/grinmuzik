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
        $origins = [];

        foreach (['r2_private', 'r2_public', 's3'] as $disk) {
            $origins = array_merge($origins, $this->diskConnectOrigins($disk));
        }

        return array_values(array_unique($origins));
    }

    /**
     * @return array<int, string>
     */
    protected function diskConnectOrigins(string $disk): array
    {
        $config = (array) config("filesystems.disks.{$disk}", []);
        $origins = [];

        foreach ([$config['endpoint'] ?? null, $config['url'] ?? null] as $candidate) {
            $origin = $this->normalizeOrigin($candidate);

            if ($origin !== null) {
                $origins[] = $origin;
            }
        }

        $bucketOrigin = $this->virtualHostedBucketOrigin(
            $config['bucket'] ?? null,
            $config['endpoint'] ?? ($config['url'] ?? null),
            (bool) ($config['use_path_style_endpoint'] ?? false),
        );

        if ($bucketOrigin !== null) {
            $origins[] = $bucketOrigin;
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

    protected function virtualHostedBucketOrigin(?string $bucket, ?string $baseUrl, bool $usePathStyleEndpoint): ?string
    {
        if ($usePathStyleEndpoint || blank($bucket) || blank($baseUrl)) {
            return null;
        }

        $parts = parse_url($baseUrl);

        if (! is_array($parts) || blank($parts['scheme'] ?? null) || blank($parts['host'] ?? null)) {
            return null;
        }

        $origin = $parts['scheme'] . '://' . $bucket . '.' . $parts['host'];

        if (filled($parts['port'] ?? null)) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }
}
