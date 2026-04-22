<?php

namespace App\Filament\Pages;

use App\Models\Student;
use App\Models\Vehicle;
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

        // Actual VROOM call comes in the next commit.
        Notification::make()
            ->title('Solver wiring lands in commit 3')
            ->body('Inputs validated: ' . count($this->selectedVehicleIds) . ' vehicles, ' . $this->studentPool->count() . ' students.')
            ->info()
            ->send();
    }
}
