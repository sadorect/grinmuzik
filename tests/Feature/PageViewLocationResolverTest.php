<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Support\PageViewLocationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageViewLocationResolverTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function private_ips_are_marked_as_private_without_external_lookup(): void
    {
        Http::fake();

        $pageView = PageView::create([
            'path' => '/about',
            'session_id' => 'private-session',
            'ip_address' => '127.0.0.1',
        ]);

        app(PageViewLocationResolver::class)->enrich($pageView);

        $pageView->refresh();

        $this->assertSame('private', $pageView->location_source);
        $this->assertSame('Private network', $pageView->country);
        $this->assertSame('Local or internal traffic', $pageView->city);
        $this->assertNotNull($pageView->resolved_location_at);
        Http::assertNothingSent();
    }

    #[Test]
    public function public_ips_can_be_enriched_with_geo_data(): void
    {
        Http::fake([
            'https://ipwho.is/*' => Http::response([
                'success' => true,
                'country' => 'United Kingdom',
                'country_code' => 'GB',
                'region' => 'England',
                'city' => 'London',
                'timezone' => [
                    'id' => 'Europe/London',
                ],
                'latitude' => 51.5072,
                'longitude' => -0.1276,
            ]),
        ]);

        $pageView = PageView::create([
            'path' => '/browse',
            'session_id' => 'geo-session',
            'ip_address' => '8.8.8.8',
        ]);

        app(PageViewLocationResolver::class)->enrich($pageView);

        $pageView->refresh();

        $this->assertSame('United Kingdom', $pageView->country);
        $this->assertSame('GB', $pageView->country_code);
        $this->assertSame('England', $pageView->region);
        $this->assertSame('London', $pageView->city);
        $this->assertSame('Europe/London', $pageView->timezone);
        $this->assertSame('ip_geolocation', $pageView->location_source);
        $this->assertNotNull($pageView->resolved_location_at);
        $this->assertSame('London, England, United Kingdom', $pageView->location_label);
    }

    #[Test]
    public function existing_location_data_for_an_ip_is_reused(): void
    {
        $existing = PageView::create([
            'path' => '/charts',
            'session_id' => 'known-1',
            'ip_address' => '8.8.4.4',
            'country' => 'Canada',
            'country_code' => 'CA',
            'region' => 'Ontario',
            'city' => 'Toronto',
            'timezone' => 'America/Toronto',
            'latitude' => 43.6532,
            'longitude' => -79.3832,
            'location_source' => 'ip_geolocation',
            'resolved_location_at' => now()->subMinute(),
        ]);

        $pageView = PageView::create([
            'path' => '/search',
            'session_id' => 'known-2',
            'ip_address' => $existing->ip_address,
        ]);

        Http::fake();

        app(PageViewLocationResolver::class)->enrich($pageView);

        $pageView->refresh();

        $this->assertSame('Canada', $pageView->country);
        $this->assertSame('Toronto', $pageView->city);
        $this->assertSame('cache', $pageView->location_source);
        Http::assertNothingSent();
    }
}
