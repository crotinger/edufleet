<?php

namespace App\Filament\Pages;

use App\Models\Student;
use App\Models\Vehicle;
use App\Services\VroomClient;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class RouteOptimizer extends Page
{
    protected string $view = 'filament.pages.route-optimizer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $navigationLabel = 'Route optimizer';

    protected static ?string $title = 'Route optimizer';

    protected static string|\UnitEnum|null $navigationGroup = 'Fleet';

    protected static ?int $navigationSort = 40;

    /** @var array<int, int> */
    public array $selectedVehicleIds = [];

    public ?float $schoolLat = null;

    public ?float $schoolLng = null;

    public ?string $attendanceCenter = null;

    /**
     * VROOM result, normalized for the view.
     *
     * Shape:
     *   null (no solve yet) OR
     *   [
     *     'routes' => [['vehicle_id', 'unit', 'capacity', 'students',
     *                   'distance_miles', 'duration_minutes', 'geometry',
     *                   'stops' => [['type', 'job_id', 'student_name', 'arrival', 'lat', 'lng'], ...]
     *                  ], ...],
     *     'unassigned' => [['student_name', 'reason'], ...],
     *     'summary' => VROOM raw summary,
     *     'solve_time_ms' => int,
     *     'solved_at' => ISO timestamp,
     *   ]
     *
     * @var array<string, mixed>|null
     */
    public ?array $result = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('use_route_optimizer') ?? false;
    }

    /** @return \Illuminate\Support\Collection<int, Vehicle> */
    public function getEligibleVehiclesProperty(): \Illuminate\Support\Collection
    {
        return Vehicle::query()
            ->where('status', Vehicle::STATUS_ACTIVE)
            ->orderBy('unit_number')
            ->get();
    }

    /** @return array<string, string> */
    public function getAttendanceCentersProperty(): array
    {
        return Student::query()
            ->whereNotNull('attendance_center')
            ->distinct()
            ->orderBy('attendance_center')
            ->pluck('attendance_center', 'attendance_center')
            ->all();
    }

    /** @return \Illuminate\Support\Collection<int, Student> */
    public function getStudentPoolProperty(): \Illuminate\Support\Collection
    {
        return Student::query()
            ->where('active', true)
            ->whereNotNull('home_lat')
            ->whereNotNull('home_lng')
            ->when($this->attendanceCenter, fn ($q) => $q->where('attendance_center', $this->attendanceCenter))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Payload for the Alpine map: vehicle depots that have coordinates set.
     *
     * @return array<int, array>
     */
    public function getVehicleMarkers(): array
    {
        return $this->eligibleVehicles
            ->filter(fn (Vehicle $v) => $v->hasDepot())
            ->map(fn (Vehicle $v) => [
                'id' => (int) $v->id,
                'unit' => $v->unit_number,
                'capacity' => (int) ($v->capacity_passengers ?? 0),
                'lat' => (float) $v->default_depot_lat,
                'lng' => (float) $v->default_depot_lng,
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array> */
    public function getStudentMarkers(): array
    {
        return $this->studentPool
            ->map(fn (Student $s) => [
                'id' => (int) $s->id,
                'name' => trim("{$s->last_name}, {$s->first_name}"),
                'lat' => (float) $s->home_lat,
                'lng' => (float) $s->home_lng,
                'center' => $s->attendance_center,
            ])
            ->values()
            ->all();
    }

    public function updatedAttendanceCenter(): void
    {
        $this->dispatch('optimizer-filter-changed');
    }

    public function setSchool(float $lat, float $lng): void
    {
        $this->schoolLat = round($lat, 6);
        $this->schoolLng = round($lng, 6);
    }

    public function solve(): void
    {
        if ($this->selectedVehicleIds === []) {
            Notification::make()->title('Pick at least one vehicle')->warning()->send();
            return;
        }
        if ($this->schoolLat === null || $this->schoolLng === null) {
            Notification::make()->title('Pick a school drop-off point')->body('Click on the map or type coordinates.')->warning()->send();
            return;
        }
        if ($this->studentPool->isEmpty()) {
            Notification::make()->title('Student pool is empty')->body('Import + geocode students, or adjust the attendance-center filter.')->warning()->send();
            return;
        }

        $vroom = app(VroomClient::class);
        if (! $vroom->isConfigured()) {
            Notification::make()
                ->title('VROOM is not configured')
                ->body('Set VROOM_URL in .env and start the vrp compose profile.')
                ->danger()
                ->send();
            return;
        }

        // Load fresh copies of the selected vehicles with depots.
        $vehicles = Vehicle::query()
            ->whereIn('id', $this->selectedVehicleIds)
            ->whereNotNull('default_depot_lat')
            ->whereNotNull('default_depot_lng')
            ->get();

        if ($vehicles->isEmpty()) {
            Notification::make()
                ->title('No depot-ready vehicles selected')
                ->danger()
                ->send();
            return;
        }

        $vroomVehicles = [];
        foreach ($vehicles as $v) {
            $vroomVehicles[] = [
                'id' => (int) $v->id,
                'profile' => 'car',
                'start' => [(float) $v->default_depot_lng, (float) $v->default_depot_lat],
                'end' => [(float) $this->schoolLng, (float) $this->schoolLat],
                'capacity' => [max(1, (int) ($v->capacity_passengers ?? 10))],
                'description' => (string) $v->unit_number,
            ];
        }

        $students = $this->studentPool;
        $jobs = [];
        foreach ($students as $s) {
            $jobs[] = [
                'id' => (int) $s->id,
                'location' => [(float) $s->home_lng, (float) $s->home_lat],
                'delivery' => [1], // each student occupies one seat
                'description' => trim("{$s->last_name}, {$s->first_name}"),
            ];
        }

        $start = microtime(true);
        try {
            $body = $vroom->solve($jobs, $vroomVehicles, ['g' => true]);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('VROOM failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }
        $ms = (int) round((microtime(true) - $start) * 1000);

        $this->result = $this->formatResult($body, $vehicles, $students, $ms);

        $assigned = array_sum(array_map(
            fn (array $r) => (int) $r['students'],
            $this->result['routes'],
        ));
        $unassigned = count($this->result['unassigned']);

        Notification::make()
            ->title("Solved in {$ms} ms")
            ->body("{$assigned} students assigned across " . count($this->result['routes']) . ' route'
                . (count($this->result['routes']) === 1 ? '' : 's')
                . ($unassigned > 0 ? ", {$unassigned} unassigned." : '.'))
            ->success()
            ->send();
    }

    /**
     * Normalize VROOM's raw response into the shape the view + future
     * save-as-RoutePath action expect.
     *
     * @param array<string, mixed>                       $body
     * @param \Illuminate\Support\Collection<int, Vehicle> $vehicles
     * @param \Illuminate\Support\Collection<int, Student> $students
     * @return array<string, mixed>
     */
    private function formatResult(array $body, \Illuminate\Support\Collection $vehicles, \Illuminate\Support\Collection $students, int $wallClockMs): array
    {
        $vehicleMap = $vehicles->keyBy('id');
        $studentMap = $students->keyBy('id');

        $routes = [];
        foreach ($body['routes'] ?? [] as $r) {
            $vehicleId = (int) ($r['vehicle'] ?? 0);
            $vehicle = $vehicleMap[$vehicleId] ?? null;

            $stops = [];
            $studentCount = 0;
            foreach ($r['steps'] ?? [] as $step) {
                $type = $step['type'] ?? 'unknown';
                $jobId = isset($step['job']) ? (int) $step['job'] : null;
                $student = $jobId !== null ? ($studentMap[$jobId] ?? null) : null;
                if ($type === 'job') {
                    $studentCount++;
                }

                $loc = $step['location'] ?? null;
                $stops[] = [
                    'type' => $type,
                    'job_id' => $jobId,
                    'student_name' => $student ? trim("{$student->last_name}, {$student->first_name}") : null,
                    'arrival' => $step['arrival'] ?? null,
                    'lng' => is_array($loc) ? (float) ($loc[0] ?? 0) : null,
                    'lat' => is_array($loc) ? (float) ($loc[1] ?? 0) : null,
                ];
            }

            $routes[] = [
                'vehicle_id' => $vehicleId,
                'unit' => $vehicle?->unit_number ?? (string) $vehicleId,
                'capacity' => (int) ($vehicle?->capacity_passengers ?? 0),
                'students' => $studentCount,
                'distance_meters' => (int) ($r['distance'] ?? 0),
                'duration_seconds' => (int) ($r['duration'] ?? 0),
                'distance_miles' => round(((int) ($r['distance'] ?? 0)) / 1609.344, 2),
                'duration_minutes' => (int) round(((int) ($r['duration'] ?? 0)) / 60),
                'geometry' => $r['geometry'] ?? null,
                'stops' => $stops,
            ];
        }

        $unassigned = [];
        foreach ($body['unassigned'] ?? [] as $u) {
            $jobId = isset($u['id']) ? (int) $u['id'] : null;
            $student = $jobId !== null ? ($studentMap[$jobId] ?? null) : null;
            $unassigned[] = [
                'job_id' => $jobId,
                'student_name' => $student ? trim("{$student->last_name}, {$student->first_name}") : (string) $jobId,
                'reason' => $u['reason'] ?? null,
            ];
        }

        return [
            'routes' => $routes,
            'unassigned' => $unassigned,
            'summary' => $body['summary'] ?? [],
            'solve_time_ms' => $wallClockMs,
            'solved_at' => now()->toIso8601String(),
        ];
    }
}
