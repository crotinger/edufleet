<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class MaintenanceTimeline extends Page
{
    protected string $view = 'filament.pages.maintenance-timeline';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'Maintenance timeline';

    protected static ?string $title = 'Maintenance timeline';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 45;

    /** @var int|null  which Vehicle to focus; null = "all vehicles" summary */
    public ?int $vehicle_id = null;

    public function mount(): void
    {
        // Default to the first active vehicle if any exist
        $first = Vehicle::where('status', Vehicle::STATUS_ACTIVE)
            ->orderBy('type')
            ->orderBy('unit_number')
            ->first();
        $this->vehicle_id = $first?->id;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_maintenance') ?? false;
    }

    public function getVehicleOptions(): Collection
    {
        return Vehicle::where('status', Vehicle::STATUS_ACTIVE)
            ->orderBy('type')
            ->orderBy('unit_number')
            ->get();
    }

    public function getSelectedVehicle(): ?Vehicle
    {
        return $this->vehicle_id ? Vehicle::find($this->vehicle_id) : null;
    }

    /**
     * For the selected vehicle, return a combined timeline of recent records + projected future.
     *
     * @return array{schedules: \Illuminate\Support\Collection, past: \Illuminate\Support\Collection, future: \Illuminate\Support\Collection}
     */
    public function getTimeline(): array
    {
        $vehicle = $this->getSelectedVehicle();
        if (! $vehicle) {
            return ['schedules' => collect(), 'past' => collect(), 'future' => collect()];
        }

        $schedules = $vehicle->maintenanceSchedules()
            ->where('is_active', true)
            ->orderBy('service_type')
            ->get();

        $past = $vehicle->maintenanceRecords()
            ->orderByDesc('performed_on')
            ->limit(20)
            ->get();

        // Future = schedule projections, sorted by urgency then date
        $future = $schedules->map(function (MaintenanceSchedule $s) {
            $p = $s->projection();
            return (object) [
                'schedule' => $s,
                'service_type' => $s->service_type,
                'service_label' => MaintenanceRecord::serviceTypes()[$s->service_type] ?? $s->service_type,
                'interval' => $s->interval_summary,
                'next_due_on' => $p['next_due_on'],
                'next_due_miles' => $p['next_due_miles'],
                'days_remaining' => $p['days_remaining'],
                'miles_remaining' => $p['miles_remaining'],
                'urgency' => $p['urgency'],
                'last_record' => $p['last_record'],
            ];
        })->sortBy(function ($row) {
            // Overdue first (negative sort), then soonest date
            $urgencyRank = match ($row->urgency) {
                'overdue' => 0,
                'soon' => 1,
                'upcoming' => 2,
                default => 3,
            };
            $date = $row->next_due_on ? $row->next_due_on->timestamp : PHP_INT_MAX;
            return $urgencyRank * 1e12 + $date;
        })->values();

        return compact('schedules', 'past', 'future');
    }
}
