<?php

namespace App\Filament\Resources\Routes\Pages;

use App\Filament\Resources\Routes\RouteResource;
use App\Models\Route;
use App\Models\RoutePath;
use App\Models\Student;
use App\Services\OsrmClient;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

class PlanRoute extends Page
{
    protected static string $resource = RouteResource::class;

    protected string $view = 'filament.resources.routes.pages.plan-route';

    public Route $record;

    public ?RoutePath $activePath = null;

    public function mount(int|string $record): void
    {
        $this->record = Route::findOrFail($record);
        $this->activePath = $this->record->activePath;
    }

    public function getTitle(): string
    {
        return "Plan — {$this->record->code} {$this->record->name}";
    }

    /** @return array<int, array{id: int, name: string, lat: float, lng: float, address: ?string}> */
    public function getRouteStudents(): array
    {
        return $this->record->students()
            ->whereNotNull('home_lat')
            ->whereNotNull('home_lng')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Student $s) => [
                'id' => (int) $s->id,
                'name' => trim("{$s->last_name}, {$s->first_name}") . ($s->grade ? " (Gr {$s->grade})" : ''),
                'lat' => (float) $s->home_lat,
                'lng' => (float) $s->home_lng,
                'address' => $s->home_address,
            ])
            ->values()
            ->all();
    }

    public function getCenterCoordinates(): array
    {
        if ($this->activePath && is_array($this->activePath->stops) && count($this->activePath->stops) > 0) {
            $first = $this->activePath->stops[0];
            if (isset($first['lat'], $first['lng'])) {
                return [(float) $first['lat'], (float) $first['lng']];
            }
        }

        $student = $this->record->students()
            ->whereNotNull('home_lat')
            ->whereNotNull('home_lng')
            ->first();
        if ($student) {
            return [(float) $student->home_lat, (float) $student->home_lng];
        }

        $anyStudent = Student::query()
            ->whereNotNull('home_lat')
            ->whereNotNull('home_lng')
            ->first();
        if ($anyStudent) {
            return [(float) $anyStudent->home_lat, (float) $anyStudent->home_lng];
        }

        // US geographic center as last-resort fallback
        return [39.8283, -98.5795];
    }

    /**
     * Trace the route through the given stops in the given order. Returns
     * geometry + distance + duration (nulls on failure).
     *
     * @param array<int, array{lat: float|string, lng: float|string, name?: string}> $stops
     * @return array{geometry: array|null, distance_meters: int|null, duration_seconds: int|null}
     */
    public function recalculate(array $stops): array
    {
        $coords = $this->extractCoordinates($stops);
        if (count($coords) < 2) {
            Notification::make()->title('At least two stops are needed to trace a route.')->warning()->send();
            return ['geometry' => null, 'distance_meters' => null, 'duration_seconds' => null];
        }

        $result = app(OsrmClient::class)->route($coords);
        if ($result === null) {
            Notification::make()->title('Routing failed')->body('OSRM did not return a route. Try again.')->danger()->send();
            return ['geometry' => null, 'distance_meters' => null, 'duration_seconds' => null];
        }

        Notification::make()
            ->title('Route calculated')
            ->body(round($result['distance_meters'] / 1609.344, 2) . ' mi, ' . (int) round($result['duration_seconds'] / 60) . ' min.')
            ->success()
            ->send();

        return $result;
    }

    /**
     * Solve the TSP over the given stops. Returns reordered stops plus the
     * traced geometry and totals.
     *
     * @param array<int, array> $stops
     * @return array{stops: array, geometry: array|null, distance_meters: int|null, duration_seconds: int|null}
     */
    public function optimize(array $stops): array
    {
        $coords = $this->extractCoordinates($stops);
        if (count($coords) < 3) {
            Notification::make()
                ->title('Nothing to optimize')
                ->body('Add at least three stops before optimizing order.')
                ->warning()
                ->send();
            return [
                'stops' => $stops,
                'geometry' => null,
                'distance_meters' => null,
                'duration_seconds' => null,
            ];
        }

        // Keep first + last as anchors (depot → school), solve middle.
        $result = app(OsrmClient::class)->trip($coords, source: 'first', destination: 'last', roundtrip: false);

        if ($result === null || empty($result['order'])) {
            Notification::make()->title('Optimization failed')->body('OSRM did not return an optimized order.')->danger()->send();
            return [
                'stops' => $stops,
                'geometry' => null,
                'distance_meters' => null,
                'duration_seconds' => null,
            ];
        }

        $reordered = [];
        foreach ($result['order'] as $visitIndex => $originalIndex) {
            if (! isset($stops[$originalIndex])) {
                continue;
            }
            $stop = $stops[$originalIndex];
            $stop['order'] = $visitIndex;
            $reordered[] = $stop;
        }

        Notification::make()
            ->title('Optimized order')
            ->body(round($result['distance_meters'] / 1609.344, 2) . ' mi, ' . (int) round($result['duration_seconds'] / 60) . ' min — hit Save to keep.')
            ->success()
            ->send();

        return [
            'stops' => $reordered,
            'geometry' => $result['geometry'],
            'distance_meters' => $result['distance_meters'],
            'duration_seconds' => $result['duration_seconds'],
        ];
    }

    /** @return array<int, array{0: float, 1: float}> */
    private function extractCoordinates(array $stops): array
    {
        $out = [];
        foreach ($stops as $s) {
            if (! isset($s['lat'], $s['lng'])) {
                continue;
            }
            $lat = is_numeric($s['lat']) ? (float) $s['lat'] : null;
            $lng = is_numeric($s['lng']) ? (float) $s['lng'] : null;
            if ($lat === null || $lng === null) {
                continue;
            }
            $out[] = [$lat, $lng];
        }
        return $out;
    }

    public function save(array $payload): void
    {
        $stops = $payload['stops'] ?? [];
        $versionName = trim($payload['version_name'] ?? '') ?: 'v1';

        if (! is_array($stops)) {
            $stops = [];
        }

        // Re-number stop order to be deterministic on read
        foreach ($stops as $i => &$stop) {
            $stop['order'] = $i;
        }
        unset($stop);

        $path = $this->record->activePath ?? new RoutePath(['route_id' => $this->record->id]);
        $path->version_name = $versionName;
        $path->stops = $stops;
        $path->geometry = $payload['geometry'] ?? null;
        $path->distance_meters = isset($payload['distance_meters']) ? (int) $payload['distance_meters'] : null;
        $path->duration_seconds = isset($payload['duration_seconds']) ? (int) $payload['duration_seconds'] : null;
        $path->profile = $payload['profile'] ?? 'driving';
        $path->is_active = true;
        $path->save();

        $this->activePath = $path->fresh();

        Notification::make()
            ->title('Route saved')
            ->body("Saved " . count($stops) . " stop" . (count($stops) === 1 ? '' : 's') . ".")
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToRoute')
                ->label('Back to route')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->url(fn () => RouteResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
