<?php

namespace App\Filament\Pages;

use App\Models\Trip;
use App\Models\TripReservation;
use App\Models\Vehicle;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class ReservationSchedule extends Page
{
    protected string $view = 'filament.pages.reservation-schedule';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $navigationLabel = 'Reservation schedule';

    protected static ?string $title = 'Reservation schedule';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 9;

    /** Monday-anchored start of the displayed week, as YYYY-MM-DD. */
    public string $weekStart = '';

    public function mount(): void
    {
        $this->weekStart = now()->startOfWeek()->toDateString();
    }

    public function prevWeek(): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function thisWeek(): void
    {
        $this->weekStart = now()->startOfWeek()->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_vehicle_availability') ?? false;
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable, days: array<int, CarbonImmutable>} */
    public function getWeek(): array
    {
        $start = CarbonImmutable::parse($this->weekStart ?: now()->startOfWeek()->toDateString())->startOfDay();
        $end = $start->addDays(6)->endOfDay();
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $start->addDays($i);
        }
        return ['start' => $start, 'end' => $end, 'days' => $days];
    }

    /**
     * Returns a collection keyed by vehicle_id, each containing the vehicle model
     * and an array keyed by day-index (0=Mon..6=Sun) of the reservations/trips
     * that overlap that day.
     */
    public function getGrid(): Collection
    {
        $week = $this->getWeek();
        $start = $week['start'];
        $end = $week['end'];

        $vehicles = Vehicle::query()
            ->where('status', Vehicle::STATUS_ACTIVE)
            ->orderBy('type')
            ->orderBy('unit_number')
            ->get();

        $reservations = TripReservation::query()
            ->whereNotNull('vehicle_id')
            ->whereIn('status', [
                TripReservation::STATUS_REQUESTED,
                TripReservation::STATUS_RESERVED,
                TripReservation::STATUS_CLAIMED,
                TripReservation::STATUS_RETURNED,
            ])
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($qq) use ($end) {
                    $qq->whereNull('desired_start_at')->orWhere('desired_start_at', '<=', $end);
                })->where(function ($qq) use ($start) {
                    $qq->whereNull('expected_return_at')->orWhere('expected_return_at', '>=', $start);
                });
            })
            ->get();

        $adHocTrips = Trip::query()
            ->whereBetween('started_at', [$start, $end])
            ->whereNull('reservation_id')
            ->whereIn('status', [Trip::STATUS_APPROVED, Trip::STATUS_PENDING])
            ->get();

        return $vehicles->mapWithKeys(function (Vehicle $v) use ($reservations, $adHocTrips, $week) {
            $cells = array_fill(0, 7, []);

            foreach ($reservations->where('vehicle_id', $v->id) as $r) {
                $rStart = $r->desired_start_at ?: $r->issued_at;
                $rEnd   = $r->expected_return_at ?: $rStart;
                for ($i = 0; $i < 7; $i++) {
                    $day = $week['days'][$i];
                    $dayEnd = $day->endOfDay();
                    if ($rStart->lte($dayEnd) && $rEnd->gte($day)) {
                        $cells[$i][] = [
                            'kind' => 'reservation',
                            'purpose' => $r->purpose,
                            'driver' => $r->expected_driver_name,
                            'start' => $rStart,
                            'end' => $rEnd,
                            'status' => $r->status,
                            'type' => $r->planned_trip_type,
                            'id' => $r->id,
                        ];
                    }
                }
            }

            foreach ($adHocTrips->where('vehicle_id', $v->id) as $t) {
                $dow = (int) $t->started_at->dayOfWeekIso - 1;
                if ($dow < 0 || $dow > 6) continue;
                $cells[$dow][] = [
                    'kind' => 'trip',
                    'purpose' => $t->purpose,
                    'driver' => $t->display_driver_name,
                    'start' => $t->started_at,
                    'end' => $t->ended_at,
                    'status' => $t->status,
                    'type' => $t->trip_type,
                    'id' => $t->id,
                ];
            }

            return [$v->id => ['vehicle' => $v, 'cells' => $cells]];
        });
    }
}
