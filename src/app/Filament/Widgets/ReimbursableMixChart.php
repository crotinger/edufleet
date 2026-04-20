<?php

namespace App\Filament\Widgets;

use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ReimbursableMixChart extends ChartWidget
{
    protected ?string $heading = 'Reimbursable vs non-reimbursable miles — last 6 months';

    protected ?string $description = 'Stacked by month. Daily-route miles are KSDE-reimbursable; other trip types are paid from activity/athletic/general funds.';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();
        return $u !== null && $u->hasAnyRole(['super-admin', 'transportation-director', 'viewer']);
    }

    protected function getData(): array
    {
        $months = 6;
        $end    = CarbonImmutable::now()->endOfMonth();
        $start  = $end->subMonths($months - 1)->startOfMonth();

        $rows = DB::table('trips')
            ->selectRaw("
                date_trunc('month', started_at) as mo,
                SUM(CASE WHEN trip_type = 'daily_route' THEN (end_odometer - start_odometer) ELSE 0 END) as reimb,
                SUM(CASE WHEN trip_type <> 'daily_route' THEN (end_odometer - start_odometer) ELSE 0 END) as other
            ")
            ->whereNotNull('ended_at')
            ->whereNull('deleted_at')
            ->where('status', \App\Models\Trip::STATUS_APPROVED)
            ->whereBetween('started_at', [$start, $end])
            ->groupBy('mo')
            ->orderBy('mo')
            ->get()
            ->keyBy(fn ($r) => CarbonImmutable::parse($r->mo)->format('Y-m'));

        $labels = [];
        $reimb = [];
        $other = [];
        for ($i = 0; $i < $months; $i++) {
            $moStart = $start->addMonths($i);
            $key = $moStart->format('Y-m');
            $labels[] = $moStart->format('M Y');
            $reimb[] = (int) ($rows->get($key)->reimb ?? 0);
            $other[] = (int) ($rows->get($key)->other ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Reimbursable (daily route)',
                    'data' => $reimb,
                    'backgroundColor' => '#2563eb',
                    'stack' => 'miles',
                ],
                [
                    'label' => 'Non-reimbursable',
                    'data' => $other,
                    'backgroundColor' => '#f97316',
                    'stack' => 'miles',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true, 'beginAtZero' => true],
            ],
        ];
    }
}
