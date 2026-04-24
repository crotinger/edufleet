<?php

namespace App\Filament\Pages;

use App\Models\Driver;
use App\Models\MaintenanceRecord;
use App\Models\PreTripInspection;
use App\Models\Registration;
use App\Models\Inspection;
use App\Models\Trip;
use App\Models\Vehicle;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BoardReport extends Page
{
    protected string $view = 'filament.pages.board-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $navigationLabel = 'Board report';

    protected static ?string $title = 'Board report';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 20;

    public string $period = 'school_year';

    public ?string $customStart = null;

    public ?string $customEnd = null;

    public ?string $reimbRate = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_board_report') ?? false;
    }

    public function mount(): void
    {
        [$s, $e] = $this->defaultSchoolYear();
        $this->customStart = $s->toDateString();
        $this->customEnd = $e->toDateString();
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function defaultSchoolYear(): array
    {
        // School year runs July 1 → June 30. "Current" means the one we're
        // currently inside.
        $now = CarbonImmutable::now();
        $start = $now->month >= 7
            ? CarbonImmutable::create($now->year, 7, 1)
            : CarbonImmutable::create($now->year - 1, 7, 1);
        $end = $start->addYear()->subDay();
        return [$start->startOfDay(), $end->endOfDay()];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string} */
    public function resolvePeriod(): array
    {
        $now = CarbonImmutable::now();
        [$syStart, $syEnd] = $this->defaultSchoolYear();

        switch ($this->period) {
            case 'last_school_year':
                $s = $syStart->subYear();
                $e = $syEnd->subYear();
                $label = "School year {$s->year}-{$e->year}";
                return [$s, $e, $label];
            case 'this_month':
                return [$now->startOfMonth(), $now->endOfMonth(), $now->format('F Y')];
            case 'last_month':
                $s = $now->subMonthNoOverflow()->startOfMonth();
                return [$s, $s->endOfMonth(), $s->format('F Y')];
            case 'this_quarter':
                $q = (int) ceil($now->month / 3);
                $s = CarbonImmutable::create($now->year, ($q - 1) * 3 + 1, 1)->startOfDay();
                $e = $s->addMonths(3)->subDay()->endOfDay();
                return [$s, $e, "Q{$q} {$now->year}"];
            case 'custom':
                $s = CarbonImmutable::parse($this->customStart ?: $syStart)->startOfDay();
                $e = CarbonImmutable::parse($this->customEnd ?: $syEnd)->endOfDay();
                return [$s, $e, "{$s->format('M j, Y')} – {$e->format('M j, Y')}"];
            case 'school_year':
            default:
                $label = "School year {$syStart->year}-{$syEnd->year}";
                return [$syStart, $syEnd, $label];
        }
    }

    public function getPeriodLabel(): string
    {
        return $this->resolvePeriod()[2];
    }

    public function getFleetMetrics(): array
    {
        $all = Vehicle::query()->whereIn('status', [Vehicle::STATUS_ACTIVE, Vehicle::STATUS_IN_SHOP])->count();
        $buses = Vehicle::query()->where('status', Vehicle::STATUS_ACTIVE)->where('type', Vehicle::TYPE_BUS)->count();
        $light = Vehicle::query()->where('status', Vehicle::STATUS_ACTIVE)->where('type', Vehicle::TYPE_LIGHT)->count();
        $retired = Vehicle::query()->where('status', Vehicle::STATUS_RETIRED)->count();
        return [
            'total_active' => Vehicle::query()->where('status', Vehicle::STATUS_ACTIVE)->count(),
            'buses' => $buses,
            'light' => $light,
            'in_shop' => Vehicle::query()->where('status', Vehicle::STATUS_IN_SHOP)->count(),
            'retired' => $retired,
        ];
    }

    public function getDriverMetrics(): array
    {
        $active = Driver::query()->where('status', Driver::STATUS_ACTIVE)->count();
        $cdlExpiring = Driver::query()
            ->where('status', Driver::STATUS_ACTIVE)
            ->whereNotNull('license_expires_on')
            ->whereBetween('license_expires_on', [now()->toDateString(), now()->addDays(60)->toDateString()])
            ->count();
        $dotExpiring = Driver::query()
            ->where('status', Driver::STATUS_ACTIVE)
            ->whereNotNull('dot_medical_expires_on')
            ->whereBetween('dot_medical_expires_on', [now()->toDateString(), now()->addDays(60)->toDateString()])
            ->count();
        $cdlExpired = Driver::query()
            ->where('status', Driver::STATUS_ACTIVE)
            ->whereNotNull('license_expires_on')
            ->where('license_expires_on', '<', now()->toDateString())
            ->count();
        return [
            'active' => $active,
            'cdl_expiring_60' => $cdlExpiring,
            'dot_expiring_60' => $dotExpiring,
            'cdl_expired' => $cdlExpired,
        ];
    }

    public function getMileageMetrics(): array
    {
        [$start, $end] = $this->resolvePeriod();
        $base = DB::table('trips')
            ->whereNull('deleted_at')
            ->whereNotNull('ended_at')
            ->where('status', Trip::STATUS_APPROVED)
            ->whereBetween('started_at', [$start, $end]);

        $all = (clone $base)->selectRaw('
            COUNT(*) as trips,
            COALESCE(SUM(end_odometer - start_odometer), 0) as miles
        ')->first();

        $reimb = (clone $base)
            ->where('trip_type', Trip::TYPE_DAILY_ROUTE)
            ->selectRaw('
                COUNT(*) as trips,
                COUNT(DISTINCT DATE(started_at)) as days,
                COALESCE(SUM(end_odometer - start_odometer), 0) as miles,
                COALESCE(SUM(riders_eligible), 0) as eligible,
                COALESCE(SUM((end_odometer - start_odometer) * riders_eligible), 0) as rider_miles
            ')->first();

        $rate = $this->reimbRate !== null && $this->reimbRate !== '' ? (float) $this->reimbRate : null;
        $estDollars = $rate !== null ? round(((int) $reimb->rider_miles) * $rate, 2) : null;

        return [
            'total_miles' => (int) $all->miles,
            'total_trips' => (int) $all->trips,
            'reimbursable_miles' => (int) $reimb->miles,
            'reimbursable_trips' => (int) $reimb->trips,
            'reimbursable_days' => (int) $reimb->days,
            'rider_miles' => (int) $reimb->rider_miles,
            'eligible_riders_sum' => (int) $reimb->eligible,
            'estimated_reimbursement' => $estDollars,
            'rate' => $rate,
        ];
    }

    public function getMaintenanceMetrics(): array
    {
        [$start, $end] = $this->resolvePeriod();
        $rows = MaintenanceRecord::query()
            ->whereBetween('performed_on', [$start->toDateString(), $end->toDateString()])
            ->get();

        $byType = $rows->groupBy('service_type')->map(fn (Collection $g) => [
            'count' => $g->count(),
            'cost_cents' => (int) $g->sum('cost_cents'),
        ])->sortByDesc('cost_cents');

        return [
            'record_count' => $rows->count(),
            'total_cost_cents' => (int) $rows->sum('cost_cents'),
            'top_types' => $byType->take(5)->map(fn ($v, $k) => [
                'service_type' => $k,
                'label' => MaintenanceRecord::serviceTypes()[$k] ?? $k,
                'count' => $v['count'],
                'cost_cents' => $v['cost_cents'],
            ])->values()->all(),
        ];
    }

    public function getSafetyMetrics(): array
    {
        [$start, $end] = $this->resolvePeriod();
        $pre = PreTripInspection::query()
            ->whereBetween('started_at', [$start, $end])
            ->whereNotNull('completed_at')
            ->get();

        $total = $pre->count();
        $passed = $pre->where('overall_result', PreTripInspection::RESULT_PASSED)->count();
        $withDefects = $pre->where('overall_result', PreTripInspection::RESULT_PASSED_WITH_DEFECTS)->count();
        $failed = $pre->where('overall_result', PreTripInspection::RESULT_FAILED)->count();
        $openDefects = PreTripInspection::query()
            ->where('defect_status', PreTripInspection::DEFECT_OPEN)
            ->count();

        $passRate = $total > 0
            ? round(($passed / max(1, $total)) * 100, 1)
            : null;

        return [
            'pre_trip_total' => $total,
            'pre_trip_passed' => $passed,
            'pre_trip_with_defects' => $withDefects,
            'pre_trip_failed' => $failed,
            'pre_trip_pass_rate' => $passRate,
            'open_defects' => $openDefects,
        ];
    }

    public function getComplianceMetrics(): array
    {
        $today = now()->toDateString();
        $soon = now()->addDays(60)->toDateString();

        $regExpiring = Registration::query()
            ->whereNotNull('expires_on')
            ->whereBetween('expires_on', [$today, $soon])
            ->count();
        $regExpired = Registration::query()
            ->whereNotNull('expires_on')
            ->where('expires_on', '<', $today)
            ->count();
        $inspExpiring = Inspection::query()
            ->whereNotNull('expires_on')
            ->whereBetween('expires_on', [$today, $soon])
            ->count();
        $inspExpired = Inspection::query()
            ->whereNotNull('expires_on')
            ->where('expires_on', '<', $today)
            ->count();

        return [
            'reg_expiring_60' => $regExpiring,
            'reg_expired' => $regExpired,
            'inspection_expiring_60' => $inspExpiring,
            'inspection_expired' => $inspExpired,
        ];
    }

    /** @return Collection<int, object> rows of {trip_type, count, miles, rider_miles} */
    public function getTripTypeBreakdown(): Collection
    {
        [$start, $end] = $this->resolvePeriod();
        return collect(DB::table('trips')
            ->whereNull('deleted_at')
            ->whereNotNull('ended_at')
            ->where('status', Trip::STATUS_APPROVED)
            ->whereBetween('started_at', [$start, $end])
            ->selectRaw('
                trip_type,
                COUNT(*) as trip_count,
                COALESCE(SUM(end_odometer - start_odometer), 0) as miles,
                COALESCE(SUM(passengers), 0) as passengers,
                COALESCE(SUM((end_odometer - start_odometer) * riders_eligible), 0) as rider_miles
            ')
            ->groupBy('trip_type')
            ->orderByDesc('miles')
            ->get());
    }
}
