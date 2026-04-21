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

    /** Version currently loaded in the editor (null = unsaved draft). */
    public ?int $currentPathId = null;

    public function mount(int|string|Route $record): void
    {
        $this->record = $record instanceof Route
            ? $record
            : Route::findOrFail($record);
        $this->currentPathId = $this->record->activePath?->id;
    }

    public function getTitle(): string
    {
        return "Plan — {$this->record->code} {$this->record->name}";
    }

    /** @return array<int, array> */
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
        $loaded = $this->currentPathId ? RoutePath::find($this->currentPathId) : null;
        if ($loaded && is_array($loaded->stops) && count($loaded->stops) > 0) {
            $first = $loaded->stops[0];
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

        return [39.8283, -98.5795];
    }

    /** @return array<int, array> */
    public function getVersions(): array
    {
        return $this->record->paths()
            ->orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (RoutePath $p) => [
                'id' => (int) $p->id,
                'version_name' => $p->version_name,
                'stops_count' => $p->stop_count,
                'distance_miles' => $p->distance_miles,
                'duration_minutes' => $p->duration_minutes,
                'is_active' => (bool) $p->is_active,
                'updated_at' => $p->updated_at?->diffForHumans(),
            ])
            ->values()
            ->all();
    }

    /**
     * Full state snapshot shape that Alpine applies on every mutating call.
     *
     * @return array<string, mixed>
     */
    private function snapshot(?RoutePath $loaded = null): array
    {
        $loaded ??= $this->currentPathId
            ? RoutePath::where('id', $this->currentPathId)->where('route_id', $this->record->id)->first()
            : null;

        return [
            'stops' => $loaded?->stops ?? [],
            'geometry' => $loaded?->geometry,
            'distance_meters' => $loaded?->distance_meters,
            'duration_seconds' => $loaded?->duration_seconds,
            'version_name' => $loaded?->version_name ?? 'v1',
            'current_path_id' => $loaded?->id,
            'active_path_id' => $this->record->activePath()->value('id'),
            'versions' => $this->getVersions(),
        ];
    }

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

    /** Update the currently-loaded version in place (or create if none). */
    public function save(array $payload): array
    {
        $path = $this->currentPathId
            ? RoutePath::where('id', $this->currentPathId)->where('route_id', $this->record->id)->first()
            : null;

        $creating = $path === null;

        if ($creating) {
            $path = new RoutePath([
                'route_id' => $this->record->id,
                'is_active' => true, // first path = auto-active
            ]);
        }

        $this->writePayload($path, $payload);
        $path->save();

        $this->currentPathId = $path->id;

        Notification::make()
            ->title($creating ? "Created {$path->version_name}" : "Saved {$path->version_name}")
            ->body(count($path->stops ?? []) . ' stops')
            ->success()
            ->send();

        return $this->snapshot($path->fresh());
    }

    /** Create a new version row; don't activate automatically. */
    public function saveAsNewVersion(array $payload): array
    {
        $path = new RoutePath([
            'route_id' => $this->record->id,
            'is_active' => false,
        ]);

        $payload['version_name'] = $this->resolveNewVersionName($payload['version_name'] ?? '');
        $this->writePayload($path, $payload);
        $path->save();

        $this->currentPathId = $path->id;

        Notification::make()
            ->title("Created {$path->version_name}")
            ->body('Not active — click Activate in the version list to use it for reporting.')
            ->success()
            ->send();

        return $this->snapshot($path->fresh());
    }

    public function loadVersion(int $pathId): array
    {
        $path = RoutePath::where('id', $pathId)->where('route_id', $this->record->id)->first();
        if (! $path) {
            return $this->snapshot();
        }
        $this->currentPathId = $path->id;
        return $this->snapshot($path);
    }

    public function activateVersion(int $pathId): array
    {
        $path = RoutePath::where('id', $pathId)->where('route_id', $this->record->id)->first();
        if (! $path) {
            return $this->snapshot();
        }
        $path->markActive();

        Notification::make()->title("Activated {$path->version_name}")->success()->send();

        return $this->snapshot($path->fresh());
    }

    public function deleteVersion(int $pathId): array
    {
        $path = RoutePath::where('id', $pathId)->where('route_id', $this->record->id)->first();
        if (! $path) {
            return $this->snapshot();
        }

        if ($path->is_active) {
            Notification::make()
                ->title('Cannot delete the active version')
                ->body('Activate a different version first.')
                ->danger()
                ->send();
            return $this->snapshot();
        }

        $wasLoaded = $this->currentPathId === $pathId;
        $path->delete();

        if ($wasLoaded) {
            $this->currentPathId = $this->record->activePath()->value('id');
        }

        Notification::make()->title("Deleted {$path->version_name}")->success()->send();

        return $this->snapshot();
    }

    public function renameVersion(int $pathId, string $newName): array
    {
        $newName = trim($newName);
        if ($newName === '') {
            return $this->snapshot();
        }

        $path = RoutePath::where('id', $pathId)->where('route_id', $this->record->id)->first();
        if (! $path) {
            return $this->snapshot();
        }

        $path->version_name = mb_substr($newName, 0, 128);
        $path->save();

        return $this->snapshot($path->fresh());
    }

    private function writePayload(RoutePath $path, array $payload): void
    {
        $stops = $payload['stops'] ?? [];
        if (! is_array($stops)) {
            $stops = [];
        }
        foreach ($stops as $i => &$s) {
            $s['order'] = $i;
        }
        unset($s);

        $versionName = trim($payload['version_name'] ?? '');
        if ($versionName === '') {
            $versionName = $path->version_name ?: 'v1';
        }

        $path->version_name = mb_substr($versionName, 0, 128);
        $path->stops = $stops;
        $path->geometry = $payload['geometry'] ?? null;
        $path->distance_meters = isset($payload['distance_meters']) ? (int) $payload['distance_meters'] : null;
        $path->duration_seconds = isset($payload['duration_seconds']) ? (int) $payload['duration_seconds'] : null;
        $path->profile = $payload['profile'] ?? 'driving';
    }

    private function resolveNewVersionName(string $requested): string
    {
        $requested = trim($requested);
        if ($requested !== '') {
            return mb_substr($requested, 0, 128);
        }

        $existing = $this->record->paths()
            ->pluck('version_name')
            ->filter(fn ($n) => preg_match('/^v(\d+)$/', $n) === 1)
            ->map(fn ($n) => (int) substr($n, 1))
            ->all();

        $next = $existing ? max($existing) + 1 : 1;
        return "v{$next}";
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
