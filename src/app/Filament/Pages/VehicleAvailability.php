<?php

namespace App\Filament\Pages;

use App\Models\Trip;
use App\Models\TripReservation;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class VehicleAvailability extends Page
{
    protected string $view = 'filament.pages.vehicle-availability';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Vehicle availability';

    protected static ?string $title = 'Vehicle availability';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_vehicle_availability') ?? false;
    }

    /** @return Collection<int, array{vehicle: Vehicle, state: string, label: string, current: ?TripReservation, upcoming: Collection}> */
    public function getBoard(): Collection
    {
        $vehicles = Vehicle::query()
            ->where('status', Vehicle::STATUS_ACTIVE)
            ->orderBy('type')
            ->orderBy('unit_number')
            ->get();

        $now = now();
        $windowEnd = now()->addDays(14)->endOfDay();

        $relevant = TripReservation::query()
            ->whereNotNull('vehicle_id')
            ->whereIn('status', [
                TripReservation::STATUS_RESERVED,
                TripReservation::STATUS_CLAIMED,
            ])
            ->where(function ($q) use ($windowEnd) {
                $q->where('desired_start_at', '<=', $windowEnd)
                  ->orWhereNull('desired_start_at');
            })
            ->with('vehicle', 'requestedBy', 'trip')
            ->orderBy('desired_start_at')
            ->orderBy('issued_at')
            ->get()
            ->groupBy('vehicle_id');

        return $vehicles->map(function (Vehicle $v) use ($relevant, $now) {
            $vRes = $relevant->get($v->id, collect());

            // In-use right now = claimed OR an open trip exists
            $inUse = $vRes->first(fn (TripReservation $r) => $r->status === TripReservation::STATUS_CLAIMED);
            $hasOpenTrip = Trip::where('vehicle_id', $v->id)->whereNull('ended_at')->exists();

            // Reserved in the current window = status=reserved and start window contains now
            $currentReservation = $vRes->first(fn (TripReservation $r) =>
                $r->status === TripReservation::STATUS_RESERVED &&
                ($r->desired_start_at === null || $r->desired_start_at->lte($now)) &&
                ($r->expected_return_at === null || $r->expected_return_at->gte($now))
            );

            if ($inUse || $hasOpenTrip) {
                $state = 'in_use';
                $label = 'In use';
                $current = $inUse;
            } elseif ($currentReservation) {
                $state = 'reserved';
                $label = 'Reserved now';
                $current = $currentReservation;
            } else {
                $state = 'available';
                $label = 'Available';
                $current = null;
            }

            $upcoming = $vRes
                ->filter(fn ($r) => $r !== $current && ($r->desired_start_at === null || $r->desired_start_at->gt($now)))
                ->values();

            return [
                'vehicle' => $v,
                'state' => $state,
                'label' => $label,
                'current' => $current,
                'upcoming' => $upcoming,
            ];
        });
    }

    public function getSummary(): array
    {
        $board = $this->getBoard();
        return [
            'total' => $board->count(),
            'available' => $board->where('state', 'available')->count(),
            'in_use' => $board->where('state', 'in_use')->count(),
            'reserved' => $board->where('state', 'reserved')->count(),
        ];
    }
}
