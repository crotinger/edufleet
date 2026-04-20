<?php

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MilesPerWeekChart extends ChartWidget
{
    protected ?string $heading = 'Miles per week (last 12 weeks)';

    protected ?string $description = 'Daily-route miles in blue; all other trip types combined in grey.';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();
        return $u !== null && $u->hasAnyRole(['super-admin', 'transportation-director', 'mechanic', 'viewer']);
    }

    protected function getData(): array
    {
        $weeks = 12;
        $end   = CarbonImmutable::now()->endOfWeek();
        $start = $end->subWeeks($weeks - 1)->startOfWeek();

        // Build per-week buckets: reimbursable vs other
        $rows = DB::table('trips')
            ->selectRaw("
                date_trunc('week', started_at) as wk,
                SUM(CASE WHEN trip_type = 'daily_route' THEN (end_odometer - start_odometer) ELSE 0 END) as reimb_miles,
                SUM(CASE WHEN trip_type <> 'daily_route' THEN (end_odometer - start_odometer) ELSE 0 END) as other_miles
            ")
            ->whereNotNull('ended_at')
            ->whereNull('deleted_at')
            ->where('status', \App\Models\Trip::STATUS_APPROVED)
            ->whereBetween('started_at', [$start, $end])
            ->groupBy('wk')
            ->orderBy('wk')
            ->get()
            ->keyBy(fn ($r) => CarbonImmutable::parse($r->wk)->format('Y-W'));

        $labels = [];
        $reimb = [];
        $other = [];
        for ($i = 0; $i < $weeks; $i++) {
            $wkStart = $start->addWeeks($i);
            $key = $wkStart->format('Y-W');
            $labels[] = $wkStart->format('M j');
            $reimb[] = (int) ($rows->get($key)->reimb_miles ?? 0);
            $other[] = (int) ($rows->get($key)->other_miles ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Daily-route miles (reimbursable)',
                    'data' => $reimb,
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Other trip miles',
                    'data' => $other,
                    'borderColor' => '#6b7280',
                    'backgroundColor' => 'rgba(107, 114, 128, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
