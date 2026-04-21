<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around OpenStreetMap's Nominatim geocoding service.
 *
 * Usage policy: https://operations.osmfoundation.org/policies/nominatim/
 *   - Max 1 request per second — caller must throttle
 *   - Must set a meaningful User-Agent identifying the application
 *   - No heavy bulk use of the public instance — self-host for > hundreds of records
 */
class NominatimGeocoder implements Geocoder
{
    public function __construct(
        private readonly string $endpoint = 'https://nominatim.openstreetmap.org/search',
        private readonly ?string $userAgent = null,
        private readonly int $timeoutSeconds = 10,
    ) {}

    /**
     * Geocode a single address.
     *
     * @return array{lat: float, lng: float}
     *
     * @throws \InvalidArgumentException when address is empty
     * @throws \RuntimeException on network error, non-200 response, no results,
     *                           or malformed response (message always set and user-readable)
     */
    public function geocode(string $address): array
    {
        $address = trim($address);
        if ($address === '') {
            throw new \InvalidArgumentException('Address is empty');
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
            throw new \RuntimeException('Network error contacting Nominatim: ' . $e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Nominatim returned HTTP ' . $response->status());
        }

        $hits = $response->json();
        if (! is_array($hits) || count($hits) === 0) {
            throw new \RuntimeException('Address not found in OpenStreetMap');
        }

        $first = $hits[0];
        if (! isset($first['lat'], $first['lon'])) {
            throw new \RuntimeException('Nominatim response missing lat/lon');
        }

        return [
            'lat' => (float) $first['lat'],
            'lng' => (float) $first['lon'],
        ];
    }
}
