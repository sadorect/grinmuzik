<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Everything keyed on the client IP — the auth throttles, page-view analytics,
 * geolocation — is only correct if $request->ip() means the visitor rather than
 * the Cloudflare edge node that relayed them.
 */
class TrustedProxyTest extends TestCase
{
    public function test_the_cloudflare_ranges_are_actually_configured(): void
    {
        $proxies = config('security.trusted_proxies');

        $this->assertIsArray($proxies);
        $this->assertNotEmpty($proxies, 'Without trusted proxies every per-IP rate limit collapses onto a handful of Cloudflare edge addresses.');

        // A wildcard would let anyone who can reach the origin directly spoof
        // X-Forwarded-For and evade every per-IP limit.
        $this->assertNotContains('*', $proxies);

        $this->assertContains('173.245.48.0/20', $proxies);
        $this->assertContains('2400:cb00::/32', $proxies);
    }

    public function test_a_spoofed_forwarded_header_from_an_untrusted_peer_is_ignored(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '198.51.100.7',           // not a Cloudflare address
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',  // attacker-supplied
        ]);

        \Illuminate\Http\Request::setTrustedProxies(
            config('security.trusted_proxies'),
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR,
        );

        $this->assertSame('198.51.100.7', $request->ip());
    }

    public function test_a_forwarded_header_relayed_by_cloudflare_is_honoured(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '173.245.48.1',           // inside Cloudflare's range
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
        ]);

        \Illuminate\Http\Request::setTrustedProxies(
            config('security.trusted_proxies'),
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR,
        );

        $this->assertSame('203.0.113.99', $request->ip());
    }
}
