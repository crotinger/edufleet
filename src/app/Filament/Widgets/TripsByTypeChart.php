<?php

namespace App\Filament\Widgets;

use App\Models\Trip;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TripsByTypeChart extends ChartWidget
{
    protected ?string $heading = 'Trips by type — this month';

    public static function canView(): bool
    {
        $u = auth()->user();
        return $u !== null && $u->hasAnyRole(['super-admin', 'transportation-director', 'viewer']);
    }

    protected function getData(): array
    {
        $rows = DB::table('trips')
            ->selectRaw('trip_type, COUNT(*) as c')
            ->whereNotNull('ended_at')
            ->whereNull('deleted_at')
            ->where('status', Trip::STATUS_APPROVED)
            ->whereBetween('started_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->groupBy('trip_type')
            ->get();

        $labels = [];
        $data = [];
        $colors = [];

        $palette = [
            Trip::TYPE_DAILY_ROUTE => '#2563eb',
            Trip::TYPE_ATHLETIC => '#f97316',
            Trip::TYPE_FIELD_TRIP => '#eab308',
            Trip::TYPE_ACTIVITY => '#14b8a6',
            Trip::TYPE_MAINTENANCE => '#6b7280',
            Trip::TYPE_OTHER => '#9ca3af',
        ];

        foreach ($rows as $r) {
            $labels[] = Trip::types()[$r->trip_type] ?? $r->trip_type;
            $data[]   = (int) $r->c;
            $colors[] = $palette[$r->trip_type] ?? '#9ca3af';
        }

        return [
            'datasets' => [[
                'label' => 'Trips',
                'data' => $data,
                'backgroundColor' => $colors,
                'borderWidth' => 0,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
