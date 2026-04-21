<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * US Census Bureau geocoder — free, no API key, US-only.
 *
 * Uses TIGER/Line data which covers essentially every street in the US
 * (including rural addresses OSM frequently misses). No published rate
 * limit for single-address lookups.
 *
 * Docs: https://geocoding.geo.census.gov/geocoder/
 */
class CensusGeocoder implements Geocoder
{
    public function __construct(
        private readonly string $endpoint = 'https://geocoding.geo.census.gov/geocoder/locations/onelineaddress',
        private readonly string $benchmark = 'Public_AR_Current',
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function geocode(string $address): array
    {
        $address = trim($address);
        if ($address === '') {
            throw new \InvalidArgumentException('Address is empty');
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->get($this->endpoint, [
                    'address' => $address,
                    'benchmark' => $this->benchmark,
                    'format' => 'json',
                ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Network error contacting Census geocoder: ' . $e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Census geocoder returned HTTP ' . $response->status());
        }

        $body = $response->json();
        $matches = $body['result']['addressMatches'] ?? null;
        if (! is_array($matches) || count($matches) === 0) {
            throw new \RuntimeException('Address not found in Census TIGER data');
        }

        $first = $matches[0];
        $coord = $first['coordinates'] ?? null;
        if (! is_array($coord) || ! isset($coord['x'], $coord['y'])) {
            throw new \RuntimeException('Census geocoder response missing coordinates');
        }

        // Census returns x = longitude, y = latitude.
        return [
            'lat' => (float) $coord['y'],
            'lng' => (float) $coord['x'],
        ];
    }
}
