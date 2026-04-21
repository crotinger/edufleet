<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin wrapper around OpenStreetMap's Nominatim geocoding service.
 *
 * Usage policy: https://operations.osmfoundation.org/policies/nominatim/
 *   - Max 1 request per second — caller must throttle
 *   - Must set a meaningful User-Agent identifying the application
 *   - No heavy bulk use of the public instance — self-host for > hundreds of records
 */
class NominatimGeocoder
{
    public function __construct(
        private readonly string $endpoint = 'https://nominatim.openstreetmap.org/search',
        private readonly ?string $userAgent = null,
        private readonly int $timeoutSeconds = 10,
    ) {}

    /**
     * Geocode a single address. Returns ['lat' => float, 'lng' => float] or null.
     */
    public function geocode(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        $ua = $this->userAgent ?? ('edufleet/1.0 (' . config('app.url') . ')');

        try {
            $response = Http::withHeaders(['User-Agent' => $ua])
                ->timeout($this->timeoutSeconds)
                ->get($this->endpoint, [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 0,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Nominatim geocode failed', ['address' => $address, 'error' => $e->getMessage()]);
            return null;
        }

        if (! $response->successful()) {
            Log::warning('Nominatim geocode non-200', ['address' => $address, 'status' => $response->status()]);
            return null;
        }

        $hits = $response->json();
        if (! is_array($hits) || count($hits) === 0) {
            return null;
        }

        $first = $hits[0];
        if (! isset($first['lat'], $first['lon'])) {
            return null;
        }

        return [
            'lat' => (float) $first['lat'],
            'lng' => (float) $first['lon'],
        ];
    }
}
