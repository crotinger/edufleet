<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the Open Source Routing Machine (OSRM) HTTP API.
 *
 * Default base URL is the OSRM project's public demo instance — fine for
 * small-district use (a handful of routes with tens of stops). For heavier
 * traffic, self-host OSRM or point at a paid provider of the same API.
 *
 * Docs: http://project-osrm.org/docs/v5.24.0/api/
 *
 * All public methods accept coordinates as [[lat, lng], ...] to match
 * typical app-side data (students.home_lat/home_lng, leaflet's LatLng, etc.).
 * Internally they are converted to OSRM's "lng,lat" wire format.
 */
class OsrmClient
{
    public function __construct(
        private readonly string $baseUrl = 'https://router.project-osrm.org',
        private readonly int $timeoutSeconds = 20,
    ) {}

    /**
     * Trace the route through the given coordinates in the given order.
     *
     * @param array<int, array{0: float, 1: float}> $coordinates  [[lat, lng], …]
     * @return array{geometry: array, distance_meters: int, duration_seconds: int}|null
     */
    public function route(array $coordinates, string $profile = 'driving'): ?array
    {
        if (count($coordinates) < 2) {
            return null;
        }

        $body = $this->call("/route/v1/{$profile}/" . $this->encode($coordinates), [
            'overview' => 'full',
            'geometries' => 'geojson',
            'steps' => 'false',
        ]);

        if ($body === null || ($body['code'] ?? null) !== 'Ok' || empty($body['routes'])) {
            return null;
        }

        $r = $body['routes'][0];

        return [
            'geometry' => $r['geometry'],
            'distance_meters' => (int) round($r['distance']),
            'duration_seconds' => (int) round($r['duration']),
        ];
    }

    /**
     * Solve the TSP over the given coordinates. Returns the optimal visit
     * order as an array of *original* indices plus the traced geometry.
     *
     * @param array<int, array{0: float, 1: float}> $coordinates
     * @return array{order: array<int, int>, geometry: array, distance_meters: int, duration_seconds: int}|null
     */
    public function trip(
        array $coordinates,
        string $profile = 'driving',
        bool $roundtrip = false,
        string $source = 'first',
        string $destination = 'last',
    ): ?array {
        if (count($coordinates) < 2) {
            return null;
        }

        $body = $this->call("/trip/v1/{$profile}/" . $this->encode($coordinates), [
            'overview' => 'full',
            'geometries' => 'geojson',
            'source' => $source,
            'destination' => $destination,
            'roundtrip' => $roundtrip ? 'true' : 'false',
            'steps' => 'false',
        ]);

        if ($body === null || ($body['code'] ?? null) !== 'Ok' || empty($body['trips']) || empty($body['waypoints'])) {
            return null;
        }

        $trip = $body['trips'][0];

        // Each waypoint has a `waypoint_index` = position in the computed trip.
        // To get visit order: build an array where position = waypoint_index and
        // value = original input index.
        $order = array_fill(0, count($coordinates), null);
        foreach ($body['waypoints'] as $originalIdx => $wp) {
            if (isset($wp['waypoint_index'])) {
                $order[$wp['waypoint_index']] = $originalIdx;
            }
        }
        $order = array_values(array_filter($order, fn ($v) => $v !== null));

        return [
            'order' => $order,
            'geometry' => $trip['geometry'],
            'distance_meters' => (int) round($trip['distance']),
            'duration_seconds' => (int) round($trip['duration']),
        ];
    }

    /** @param array<int, array{0: float, 1: float}> $coordinates */
    private function encode(array $coordinates): string
    {
        return implode(';', array_map(
            fn (array $c) => sprintf('%F,%F', $c[1], $c[0]),
            $coordinates,
        ));
    }

    private function call(string $path, array $query): ?array
    {
        try {
            $response = Http::timeout($this->timeoutSeconds)->get($this->baseUrl . $path, $query);
        } catch (\Throwable $e) {
            Log::warning('OSRM call failed', ['path' => $path, 'error' => $e->getMessage()]);
            return null;
        }

        if (! $response->successful()) {
            Log::warning('OSRM non-200', ['path' => $path, 'status' => $response->status()]);
            return null;
        }

        return $response->json();
    }
}
