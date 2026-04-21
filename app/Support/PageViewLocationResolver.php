<?php

namespace App\Support;

use App\Models\PageView;
use Illuminate\Support\Facades\Http;
use Throwable;

class PageViewLocationResolver
{
    public function enrich(PageView $pageView): void
    {
        if ($pageView->resolved_location_at || blank($pageView->ip_address)) {
            return;
        }

        if ($this->isPrivateOrLocalIp($pageView->ip_address)) {
            $pageView->forceFill([
                'country' => 'Private network',
                'region' => null,
                'city' => 'Local or internal traffic',
                'timezone' => null,
                'latitude' => null,
                'longitude' => null,
                'location_source' => 'private',
                'resolved_location_at' => now(),
            ])->save();

            return;
        }

        $knownLocation = PageView::query()
            ->where('ip_address', $pageView->ip_address)
            ->whereNotNull('resolved_location_at')
            ->whereKeyNot($pageView->id)
            ->latest('resolved_location_at')
            ->first([
                'country',
                'country_code',
                'region',
                'city',
                'timezone',
                'latitude',
                'longitude',
                'location_source',
            ]);

        if ($knownLocation) {
            $pageView->forceFill([
                'country' => $knownLocation->country,
                'country_code' => $knownLocation->country_code,
                'region' => $knownLocation->region,
                'city' => $knownLocation->city,
                'timezone' => $knownLocation->timezone,
                'latitude' => $knownLocation->latitude,
                'longitude' => $knownLocation->longitude,
                'location_source' => 'cache',
                'resolved_location_at' => now(),
            ])->save();

            return;
        }

        if (! config('services.ip_geolocation.enabled', true)) {
            return;
        }

        $baseUrl = rtrim((string) config('services.ip_geolocation.base_url', 'https://ipwho.is'), '/');
        $timeout = (int) config('services.ip_geolocation.timeout', 3);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get("{$baseUrl}/{$pageView->ip_address}", [
                    'fields' => 'success,country,country_code,region,city,latitude,longitude,timezone,message',
                ]);

            if (! $response->successful()) {
                return;
            }

            $payload = $response->json();

            if (! data_get($payload, 'success')) {
                return;
            }

            $pageView->forceFill([
                'country' => data_get($payload, 'country'),
                'country_code' => data_get($payload, 'country_code'),
                'region' => data_get($payload, 'region'),
                'city' => data_get($payload, 'city'),
                'timezone' => data_get($payload, 'timezone.id') ?? data_get($payload, 'timezone'),
                'latitude' => data_get($payload, 'latitude'),
                'longitude' => data_get($payload, 'longitude'),
                'location_source' => 'ip_geolocation',
                'resolved_location_at' => now(),
            ])->save();
        } catch (Throwable) {
            // Analytics enrichment should never interrupt the app.
        }
    }

    protected function isPrivateOrLocalIp(string $ipAddress): bool
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        return true;
    }
}
