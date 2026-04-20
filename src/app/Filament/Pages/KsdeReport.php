<?php

namespace App\Filament\Pages;

use App\Models\Route;
use App\Models\Trip;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KsdeReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.ksde-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'KSDE mileage report';

    protected static ?string $title = 'KSDE mileage report';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'reimbursement_rate' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Reporting period')
                    ->description('KSDE transportation reimbursement is claimed for daily-route miles with eligible riders (students living 2.5+ miles from school).')
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('start_date')
                                ->label('Start date')
                                ->required()
                                ->live(),
                            DatePicker::make('end_date')
                                ->label('End date')
                                ->required()
                                ->after('start_date')
                                ->live(),
                            TextInput::make('reimbursement_rate')
                                ->label('Rate per reimbursable mile')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->prefix('$')
                                ->suffix('/ mi')
                                ->placeholder('e.g. 1.85')
                                ->helperText('Optional. Back-of-envelope estimate only — KSDE uses a weighted formula.')
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('KSDE\'s real formula is weighted-pupil-count × a state transportation cost index, plus capital-outlay contributions — not a flat rate. Use this field for rough budgeting; pull the actual figure from your KSDE notice of allocation.')
                                ->live(onBlur: true),
                        ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('success')
                ->action(fn () => $this->exportCsv()),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function dateRange(): array
    {
        $start = Carbon::parse($this->data['start_date'] ?? now()->startOfMonth())->startOfDay();
        $end   = Carbon::parse($this->data['end_date']   ?? now()->endOfMonth())->endOfDay();
        return [$start, $end];
    }

    public function getRouteRollup(): Collection
    {
        [$start, $end] = $this->dateRange();
        $scope = fn ($q) => $q->whereNotNull('ended_at')->where('status', Trip::STATUS_APPROVED)->whereBetween('started_at', [$start, $end]);

        return Route::query()
            ->withCount(['trips as trips_count' => $scope])
            ->withCount(['trips as coverage_days_count' => fn ($q) => $scope($q)->select(DB::raw('COUNT(DISTINCT DATE(started_at))'))])
            ->withSum(['trips as miles_sum' => $scope], DB::raw('end_odometer - start_odometer'))
            ->withSum(['trips as eligible_sum' => $scope], 'riders_eligible')
            ->withSum(['trips as ineligible_sum' => $scope], 'riders_ineligible')
            ->withSum(['trips as rider_miles_sum' => $scope], DB::raw('(end_odometer - start_odometer) * riders_eligible'))
            ->orderBy('code')
            ->get();
    }

    public function getTripTypeBreakdown(): Collection
    {
        [$start, $end] = $this->dateRange();

        return collect(DB::table('trips')
            ->selectRaw('
                trip_type,
                COUNT(*) as trips,
                COALESCE(SUM(end_odometer - start_odometer), 0) as miles,
                COALESCE(SUM(passengers), 0) as passengers,
                COALESCE(SUM(riders_eligible), 0) as eligible,
                COALESCE(SUM(riders_ineligible), 0) as ineligible,
                COALESCE(SUM((end_odometer - start_odometer) * riders_eligible), 0) as rider_miles
            ')
            ->whereNotNull('ended_at')
            ->whereNull('deleted_at')
            ->where('status', Trip::STATUS_APPROVED)
            ->whereBetween('started_at', [$start, $end])
            ->groupBy('trip_type')
            ->get());
    }

    public function getVehicleRollup(): Collection
    {
        [$start, $end] = $this->dateRange();
        $scope = fn ($q) => $q->whereNotNull('ended_at')->where('status', Trip::STATUS_APPROVED)->whereBetween('started_at', [$start, $end]);

        return Vehicle::query()
            ->withCount(['trips as trips_count' => $scope])
            ->withSum(['trips as miles_sum' => $scope], DB::raw('end_odometer - start_odometer'))
            ->withSum(['trips as daily_route_miles' => fn ($q) => $scope($q)->where('trip_type', Trip::TYPE_DAILY_ROUTE)], DB::raw('end_odometer - start_odometer'))
            ->orderBy('type')
            ->orderBy('unit_number')
            ->get()
            ->filter(fn ($v) => ($v->trips_count ?? 0) > 0)
            ->values();
    }

    /** @return array<string, int|float> */
    public function getTotals(): array
    {
        [$start, $end] = $this->dateRange();

        $sub = DB::table('trips')
            ->whereNotNull('ended_at')
            ->where('status', Trip::STATUS_APPROVED)
            ->whereBetween('started_at', [$start, $end])
            ->whereNull('deleted_at');

        $all = (clone $sub)->selectRaw('
            COUNT(*) as trips,
            COUNT(DISTINCT vehicle_id) as active_vehicles,
            COUNT(DISTINCT driver_id) as active_drivers,
            COALESCE(SUM(end_odometer - start_odometer), 0) as miles,
            COALESCE(SUM(passengers), 0) as passengers,
            COALESCE(SUM(riders_eligible), 0) as eligible,
            COALESCE(SUM(riders_ineligible), 0) as ineligible
        ')->first();

        $reimb = (clone $sub)
            ->where('trip_type', Trip::TYPE_DAILY_ROUTE)
            ->selectRaw('
                COUNT(*) as trips,
                COUNT(DISTINCT DATE(started_at)) as coverage_days,
                COUNT(DISTINCT route_id) as active_routes,
                COALESCE(SUM(end_odometer - start_odometer), 0) as miles,
                COALESCE(SUM(riders_eligible), 0) as eligible,
                COALESCE(SUM((end_odometer - start_odometer) * riders_eligible), 0) as rider_miles
            ')->first();

        $allMiles   = (int) $all->miles;
        $reimbMiles = (int) $reimb->miles;
        $reimbTrips = (int) $reimb->trips;
        $coverage   = (int) $reimb->coverage_days;

        $rate = filled($this->data['reimbursement_rate'] ?? null)
            ? (float) $this->data['reimbursement_rate']
            : null;
        $estimatedReimb = $rate !== null ? round($reimbMiles * $rate, 2) : null;

        return [
            // reimbursable subset (daily_route only)
            'reimbursable_trips'     => $reimbTrips,
            'reimbursable_miles'     => $reimbMiles,
            'reimbursable_eligible'  => (int) $reimb->eligible,
            'reimbursable_rider_mi'  => (int) $reimb->rider_miles,
            'reimbursable_pct'       => $allMiles > 0 ? round(($reimbMiles / $allMiles) * 100, 1) : 0.0,
            'coverage_days'          => $coverage,
            'active_routes'          => (int) $reimb->active_routes,
            'avg_reimb_miles_per_day' => $coverage > 0 ? round($reimbMiles / $coverage, 1) : 0.0,
            'avg_eligible_per_route_trip' => $reimbTrips > 0 ? round(((int) $reimb->eligible) / $reimbTrips, 1) : 0.0,
            'avg_rider_mi_per_day'   => $coverage > 0 ? round(((int) $reimb->rider_miles) / $coverage) : 0,

            // full activity (for context)
            'all_trips'              => (int) $all->trips,
            'all_miles'              => $allMiles,
            'all_passengers'         => (int) $all->passengers,
            'all_eligible'           => (int) $all->eligible,
            'all_ineligible'         => (int) $all->ineligible,
            'active_vehicles'        => (int) $all->active_vehicles,
            'active_drivers'         => (int) $all->active_drivers,

            // optional estimate (requires rate input)
            'reimbursement_rate'     => $rate,
            'estimated_reimbursement' => $estimatedReimb,
        ];
    }

    /** @return array<int, array{severity: string, message: string, count?: int}> */
    public function getDataQualityIssues(): array
    {
        [$start, $end] = $this->dateRange();
        $issues = [];

        // Trips started in period but still in progress
        $inProgress = Trip::whereBetween('started_at', [$start, $end])
            ->whereNull('ended_at')
            ->count();
        if ($inProgress > 0) {
            $issues[] = [
                'severity' => 'warning',
                'message'  => "{$inProgress} trip" . ($inProgress === 1 ? '' : 's') . ' started in this period but never completed (missing end odometer). These miles are not in the totals.',
            ];
        }

        // Daily-route trips with ridership completely missing
        $missingRidership = Trip::whereBetween('started_at', [$start, $end])
            ->whereNotNull('ended_at')
            ->approved()
            ->where('trip_type', Trip::TYPE_DAILY_ROUTE)
            ->where(function ($q) {
                $q->whereNull('riders_eligible')->orWhere('riders_eligible', 0);
            })
            ->where(function ($q) {
                $q->whereNull('riders_ineligible')->orWhere('riders_ineligible', 0);
            })
            ->count();
        if ($missingRidership > 0) {
            $issues[] = [
                'severity' => 'warning',
                'message'  => "{$missingRidership} daily-route trip" . ($missingRidership === 1 ? '' : 's') . ' completed with no ridership logged. Rider-miles undercounted — KSDE reimbursement may be understated.',
            ];
        }

        // Active routes with zero activity
        $silentRoutes = Route::query()
            ->where('status', Route::STATUS_ACTIVE)
            ->whereDoesntHave('trips', fn ($q) => $q->whereNotNull('ended_at')->approved()->whereBetween('started_at', [$start, $end]))
            ->get(['code']);
        if ($silentRoutes->isNotEmpty()) {
            $codes = $silentRoutes->pluck('code')->implode(', ');
            $issues[] = [
                'severity' => 'info',
                'message'  => 'Active route' . ($silentRoutes->count() === 1 ? '' : 's') . " with no trips in this period: {$codes}. Expected coverage gap (holiday / cancellation) or missing dispatch logs.",
            ];
        }

        // Trips with odometer regression (end < start)
        $badOdo = Trip::whereBetween('started_at', [$start, $end])
            ->whereNotNull('end_odometer')
            ->approved()
            ->whereColumn('end_odometer', '<', 'start_odometer')
            ->count();

        // Trips awaiting approval
        $pending = Trip::pending()->whereBetween('started_at', [$start, $end])->count();
        if ($pending > 0) {
            $issues[] = [
                'severity' => 'info',
                'message'  => "{$pending} trip" . ($pending === 1 ? '' : 's') . ' awaiting approval. Not yet counted in totals above.',
            ];
        }
        if ($badOdo > 0) {
            $issues[] = [
                'severity' => 'danger',
                'message'  => "{$badOdo} trip" . ($badOdo === 1 ? '' : 's') . ' with end odometer less than start odometer — data entry error; miles for those trips are zeroed in totals.',
            ];
        }

        return $issues;
    }

    public function getFormattedPeriod(): string
    {
        [$start, $end] = $this->dateRange();
        if ($start->isSameMonth($end) && $start->isSameYear($end)) {
            return $start->format('F j') . ' – ' . $end->format('j, Y');
        }
        if ($start->isSameYear($end)) {
            return $start->format('F j') . ' – ' . $end->format('F j, Y');
        }
        return $start->format('M j, Y') . ' – ' . $end->format('M j, Y');
    }

    public function exportCsv(): StreamedResponse
    {
        [$start, $end] = $this->dateRange();
        $filename = sprintf('ksde-report-%s-to-%s.csv', $start->toDateString(), $end->toDateString());
        $routes = $this->getRouteRollup();
        $vehicles = $this->getVehicleRollup();
        $totals = $this->getTotals();

        return response()->streamDownload(function () use ($start, $end, $routes, $vehicles, $totals): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['edufleet — KSDE Transportation Mileage Report']);
            fputcsv($out, ['Period', $start->toDateString(), 'to', $end->toDateString()]);
            fputcsv($out, []);

            fputcsv($out, ['-- Reimbursable totals (daily_route trips only) --']);
            fputcsv($out, ['Trips', 'Miles', 'Eligible boardings', 'Rider-miles', 'Coverage days', 'Active routes', 'Avg miles/day', 'Avg eligible/trip', 'Avg rider-mi/day']);
            fputcsv($out, [
                $totals['reimbursable_trips'],
                $totals['reimbursable_miles'],
                $totals['reimbursable_eligible'],
                $totals['reimbursable_rider_mi'],
                $totals['coverage_days'],
                $totals['active_routes'],
                $totals['avg_reimb_miles_per_day'],
                $totals['avg_eligible_per_route_trip'],
                $totals['avg_rider_mi_per_day'],
            ]);
            if ($totals['estimated_reimbursement'] !== null) {
                fputcsv($out, []);
                fputcsv($out, ['-- Estimated reimbursement (back-of-envelope, not KSDE\'s actual formula) --']);
                fputcsv($out, ['Rate per mile', 'Reimbursable miles', 'Estimated $']);
                fputcsv($out, [
                    number_format($totals['reimbursement_rate'], 2),
                    $totals['reimbursable_miles'],
                    number_format($totals['estimated_reimbursement'], 2),
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['-- Per-route rollup --']);
            fputcsv($out, ['Code', 'Name', 'Trips', 'Miles', 'Eligible', 'Ineligible', 'Rider-miles']);
            foreach ($routes as $r) {
                fputcsv($out, [
                    $r->code,
                    $r->name,
                    (int) ($r->trips_count ?? 0),
                    (int) ($r->miles_sum ?? 0),
                    (int) ($r->eligible_sum ?? 0),
                    (int) ($r->ineligible_sum ?? 0),
                    (int) ($r->rider_miles_sum ?? 0),
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['-- Per-vehicle rollup --']);
            fputcsv($out, ['Unit', 'Type', 'Trips (all)', 'Miles (all)', 'Miles (daily route only)']);
            foreach ($vehicles as $v) {
                fputcsv($out, [
                    $v->unit_number,
                    Vehicle::types()[$v->type] ?? $v->type,
                    (int) ($v->trips_count ?? 0),
                    (int) ($v->miles_sum ?? 0),
                    (int) ($v->daily_route_miles ?? 0),
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['-- All trip activity (for context, includes non-reimbursable) --']);
            fputcsv($out, ['Trips', 'Total miles driven', 'Total passengers', 'Eligible boardings', 'Ineligible boardings', 'Active vehicles', 'Active drivers', 'Reimbursable miles %']);
            fputcsv($out, [
                $totals['all_trips'],
                $totals['all_miles'],
                $totals['all_passengers'],
                $totals['all_eligible'],
                $totals['all_ineligible'],
                $totals['active_vehicles'],
                $totals['active_drivers'],
                $totals['reimbursable_pct'],
            ]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_route') ?? false;
    }
}
