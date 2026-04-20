<?php

namespace App\Filament\Widgets;

use App\Models\Trip;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TripMileageOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Trips & mileage';

    public static function canView(): bool
    {
        $u = auth()->user();
        return $u !== null && $u->hasAnyRole(['super-admin', 'transportation-director', 'mechanic', 'viewer', 'driver']);
    }

    protected function getStats(): array
    {
        $weekStart = now()->startOfWeek();
        $weekEnd   = now()->endOfWeek();
        $monthStart = now()->startOfMonth();

        $weekTrips = Trip::completed()
            ->approved()
            ->whereBetween('started_at', [$weekStart, $weekEnd])
            ->count();

        $weekMiles = (int) Trip::completed()
            ->approved()
            ->whereBetween('started_at', [$weekStart, $weekEnd])
            ->toBase()
            ->sum(DB::raw('end_odometer - start_odometer'));

        $monthMiles = (int) Trip::completed()
            ->approved()
            ->where('started_at', '>=', $monthStart)
            ->toBase()
            ->sum(DB::raw('end_odometer - start_odometer'));

        $inProgress = Trip::inProgress()->count();
        $pending = Trip::pending()->count();

        return [
            Stat::make('Trips this week', $weekTrips)
                ->description(number_format($weekMiles) . ' miles')
                ->color('info'),

            Stat::make('Miles this month', number_format($monthMiles))
                ->description('month-to-date, completed trips only')
                ->color('success'),

            Stat::make('In progress', $inProgress)
                ->description($inProgress === 0 ? 'no active trips' : 'awaiting end-odometer')
                ->color($inProgress > 0 ? 'warning' : 'gray'),

            Stat::make('Pending approval', $pending)
                ->description($pending === 0 ? 'nothing to review' : 'quicktrip submissions')
                ->color($pending > 0 ? 'warning' : 'gray'),
        ];
    }

    public function getColumns(): int
    {
        return 4;
    }
}
