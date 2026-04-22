<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client for a VROOM server (https://github.com/VROOM-Project/vroom-express).
 *
 * VROOM solves the vehicle-routing problem (VRP) — given jobs (stops),
 * vehicles (buses), and constraints (capacity, time windows, skills, start/
 * end points), returns the optimal assignment + sequence of jobs per
 * vehicle. Use the solveSingleTour() convenience for one-bus TSP; use
 * solve() directly to pass raw VROOM problem payloads for multi-vehicle
 * VRP.
 *
 * Expects a self-hosted VROOM reachable at config('services.vroom.url').
 * The vrp compose profile spins one up as `http://vroom:3000`.
 */
class VroomClient
{
    private readonly ?string $baseUrl;
    private readonly int $timeoutSeconds;

    public function __construct(?string $baseUrl = null, int $timeoutSeconds = 120)
    {
        $configured = $baseUrl ?? config('services.vroom.url');
        $this->baseUrl = $configured ? rtrim($configured, '/') : null;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== null;
    }

    /**
     * Raw VROOM call — see https://github.com/VROOM-Project/vroom/blob/master/docs/API.md
     *
     * @param array<int, array<string, mixed>> $jobs     Each: {id, location: [lng, lat], ...}
     * @param array<int, array<string, mixed>> $vehicles Each: {id, start, end, profile, ...}
     * @param array<string, mixed>             $options  e.g. ['g' => true] to return geometry
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException on network error or non-200 / VROOM error code
     */
    public function solve(array $jobs, array $vehicles, array $options = []): array
    {
        $this->requireConfigured();

        $payload = [
            'jobs' => $jobs,
            'vehicles' => $vehicles,
        ];
        if ($options !== []) {
            $payload['options'] = $options;
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->asJson()
                ->post($this->baseUrl, $payload);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Network error contacting VROOM: ' . $e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new \RuntimeException('VROOM returned HTTP ' . $response->status() . ': ' . $response->body());
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new \RuntimeException('VROOM returned non-array response');
        }

        // VROOM returns code=0 on success. Any other code is an error with
        // a message in the `error` field.
        $code = $body['code'] ?? null;
        if ($code !== 0 && $code !== null) {
            $msg = $body['error'] ?? 'unknown error';
            throw new \RuntimeException("VROOM error code {$code}: {$msg}");
        }

        return $body;
    }

    /**
     * Convenience: solve a single-vehicle tour over `$stops` anchored at
     * `$start` and `$end`. Returns visit order (as indices into $stops),
     * geometry, distance, and duration.
     *
     * @param array<int, array{0: float, 1: float}> $stops  [[lat, lng], ...] — intermediate pickups
     * @param array{0: float, 1: float}             $start  Depot coord [lat, lng]
     * @param array{0: float, 1: float}             $end    School coord [lat, lng]
     *
     * @return array{order: array<int, int>, geometry: mixed, distance_meters: int, duration_seconds: int}
     */
    public function solveSingleTour(array $stops, array $start, array $end, string $profile = 'car'): array
    {
        if (count($stops) < 1) {
            throw new \InvalidArgumentException('At least one stop is required');
        }

        $jobs = [];
        foreach ($stops as $i => $coord) {
            $jobs[] = [
                'id' => $i + 1,
                'location' => [$coord[1], $coord[0]], // VROOM wants [lng, lat]
            ];
        }

        $vehicle = [
            'id' => 1,
            'profile' => $profile,
            'start' => [$start[1], $start[0]],
            'end' => [$end[1], $end[0]],
        ];

        $result = $this->solve($jobs, [$vehicle], ['g' => true]);

        $routes = $result['routes'] ?? [];
        if (empty($routes)) {
            throw new \RuntimeException('VROOM returned no routes');
        }

        $route = $routes[0];

        $order = [];
        foreach ($route['steps'] ?? [] as $step) {
            if (($step['type'] ?? null) === 'job' && isset($step['job'])) {
                $order[] = ((int) $step['job']) - 1; // back to 0-indexed
            }
        }

        return [
            'order' => $order,
            'geometry' => $route['geometry'] ?? null,
            'distance_meters' => (int) ($route['distance'] ?? 0),
            'duration_seconds' => (int) ($route['duration'] ?? 0),
        ];
    }

    private function requireConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'VROOM is not configured — set VROOM_URL (e.g. http://vroom:3000) and start the vrp compose profile.'
            );
        }
    }
}
