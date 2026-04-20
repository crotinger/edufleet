<x-filament-panels::page>
    @php
        $routes   = $this->getRouteRollup();
        $vehicles = $this->getVehicleRollup();
        $types    = $this->getTripTypeBreakdown();
        $totals   = $this->getTotals();
        $issues   = $this->getDataQualityIssues();
        $period   = $this->getFormattedPeriod();
        $fmt      = fn ($n) => number_format((int) $n);

        $tripTypeLabels = \App\Models\Trip::types();

        // Totals row for the route table
        $routeRowsWithActivity = $routes->filter(fn ($r) => ($r->trips_count ?? 0) > 0);
        $routeTotals = [
            'routes'    => $routeRowsWithActivity->count(),
            'trips'     => $routeRowsWithActivity->sum('trips_count'),
            'miles'     => $routeRowsWithActivity->sum('miles_sum'),
            'eligible'  => $routeRowsWithActivity->sum('eligible_sum'),
            'ineligible'=> $routeRowsWithActivity->sum('ineligible_sum'),
            'rider_mi'  => $routeRowsWithActivity->sum('rider_miles_sum'),
        ];

        $vehicleTotals = [
            'vehicles'       => $vehicles->count(),
            'trips'          => $vehicles->sum('trips_count'),
            'miles_all'      => $vehicles->sum('miles_sum'),
            'miles_route'    => $vehicles->sum('daily_route_miles'),
        ];

        $typeTotals = [
            'trips'      => $types->sum('trips'),
            'miles'      => $types->sum('miles'),
            'passengers' => $types->sum('passengers'),
            'eligible'   => $types->sum('eligible'),
            'ineligible' => $types->sum('ineligible'),
            'rider_mi'   => $types->sum('rider_miles'),
        ];
    @endphp

    {{-- Print CSS --}}
    <style>
        @media print {
            .fi-sidebar, .fi-topbar, .fi-page-header, .fi-main-ctn > .fi-section:first-of-type { display: none !important; }
            .fi-main { padding: 0 !important; }
            .ksde-print-only { display: block !important; }
            .ksde-no-print { display: none !important; }
            .ksde-claim-hero { break-after: avoid; }
            .ksde-table { break-inside: auto; font-size: 10pt; }
            .ksde-table thead { display: table-header-group; }
            .ksde-table tr { break-inside: avoid; }
            body { background: white !important; }
        }
        .ksde-claim-hero {
            background: linear-gradient(135deg, rgb(37 99 235 / 0.08), rgb(37 99 235 / 0.02));
            border: 1px solid rgb(37 99 235 / 0.2);
        }
        .dark .ksde-claim-hero {
            background: linear-gradient(135deg, rgb(59 130 246 / 0.12), rgb(59 130 246 / 0.04));
            border-color: rgb(59 130 246 / 0.3);
        }
        .ksde-table { border-collapse: collapse; width: 100%; }
        .ksde-table th { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; color: rgb(107 114 128); padding: 0.5rem 0.75rem; text-align: left; border-bottom: 1px solid rgb(229 231 235); }
        .dark .ksde-table th { color: rgb(156 163 175); border-bottom-color: rgb(55 65 81); }
        .ksde-table td { padding: 0.625rem 0.75rem; border-bottom: 1px solid rgb(243 244 246); font-variant-numeric: tabular-nums; }
        .dark .ksde-table td { border-bottom-color: rgb(31 41 55); }
        .ksde-table tbody tr:hover { background: rgb(249 250 251); }
        .dark .ksde-table tbody tr:hover { background: rgb(31 41 55 / 0.4); }
        .ksde-table tfoot td { font-weight: 600; border-top: 2px solid rgb(209 213 219); border-bottom: none; padding-top: 0.75rem; }
        .dark .ksde-table tfoot td { border-top-color: rgb(75 85 99); }
        .ksde-num { text-align: right; font-variant-numeric: tabular-nums; }
        .ksde-muted { color: rgb(107 114 128); }
        .dark .ksde-muted { color: rgb(156 163 175); }
        .ksde-sort-th { cursor: pointer; user-select: none; position: relative; padding-right: 1.25rem; transition: color 0.15s; }
        .ksde-sort-th:hover { color: rgb(55 65 81); }
        .dark .ksde-sort-th:hover { color: rgb(229 231 235); }
        .ksde-sort-th[data-active="true"] { color: rgb(37 99 235); }
        .dark .ksde-sort-th[data-active="true"] { color: rgb(96 165 250); }
        .ksde-sort-indicator::after {
            content: '';
            position: absolute;
            right: 0.25rem;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            opacity: 0.3;
            border-bottom: 4px solid currentColor;
            margin-top: -3px;
        }
        .ksde-sort-indicator[data-active="true"]::after { opacity: 1; }
        .ksde-sort-indicator[data-dir="desc"]::after {
            border-bottom: none;
            border-top: 4px solid currentColor;
            margin-top: 3px;
        }
        @media print { .ksde-sort-th { cursor: default; padding-right: 0.75rem; } .ksde-sort-indicator::after { display: none; } }
    </style>

    <script>
        // Lightweight click-to-sort for ksde-table. Reads data-sort-* attributes
        // on each <tr>, preserves <tfoot> at the bottom, toggles direction on
        // repeat clicks. No dependency beyond vanilla JS.
        window.ksdeInitSort = function (tableId, defaultKey, defaultDir) {
            const table = document.getElementById(tableId);
            if (!table) return;
            let sortKey = defaultKey, sortDir = defaultDir || 'desc';
            const tbody = table.querySelector('tbody');
            const headers = table.querySelectorAll('th[data-sort-key]');

            const apply = () => {
                headers.forEach(h => {
                    const isActive = h.dataset.sortKey === sortKey;
                    h.dataset.active = isActive ? 'true' : 'false';
                    h.dataset.dir = isActive ? sortDir : '';
                });
                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.sort((a, b) => {
                    const av = a.dataset['sort' + sortKey[0].toUpperCase() + sortKey.slice(1)] ?? '';
                    const bv = b.dataset['sort' + sortKey[0].toUpperCase() + sortKey.slice(1)] ?? '';
                    const an = parseFloat(av), bn = parseFloat(bv);
                    let cmp;
                    if (!isNaN(an) && !isNaN(bn)) cmp = an - bn;
                    else cmp = String(av).localeCompare(String(bv), undefined, { numeric: true, sensitivity: 'base' });
                    return sortDir === 'desc' ? -cmp : cmp;
                });
                rows.forEach(r => tbody.appendChild(r));
            };

            headers.forEach(h => {
                h.addEventListener('click', () => {
                    const key = h.dataset.sortKey;
                    if (sortKey === key) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                    else { sortKey = key; sortDir = h.dataset.sortDefault || 'desc'; }
                    apply();
                });
            });

            apply();
        };

        document.addEventListener('DOMContentLoaded', function () {
            ksdeInitSort('ksde-route-table', 'code', 'asc');
            ksdeInitSort('ksde-vehicle-table', 'routeMiles', 'desc');
            ksdeInitSort('ksde-type-table', 'miles', 'desc');
        });
        // Also run on Livewire updates (filter changes rebuild the DOM)
        document.addEventListener('livewire:navigated', function () {
            ksdeInitSort('ksde-route-table', 'code', 'asc');
            ksdeInitSort('ksde-vehicle-table', 'routeMiles', 'desc');
            ksdeInitSort('ksde-type-table', 'miles', 'desc');
        });
    </script>

    {{-- Print-only header (hidden on screen) --}}
    <div class="ksde-print-only" style="display: none;">
        <h1 style="font-size: 14pt; margin: 0 0 0.25rem 0;">USD444 — KSDE Transportation Mileage Report</h1>
        <p style="margin: 0 0 1rem 0; font-size: 10pt;">{{ $period }}</p>
    </div>

    {{-- Filter form --}}
    <div class="ksde-no-print">
        {{ $this->form }}
    </div>

    {{-- HERO: the bottom-line numbers that matter for KSDE --}}
    <div class="ksde-claim-hero rounded-xl p-6 md:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-300">Reporting period</div>
                <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $period }}</div>
                <div class="mt-1 text-sm ksde-muted">
                    {{ $totals['coverage_days'] }} coverage day{{ $totals['coverage_days'] === 1 ? '' : 's' }}
                    · {{ $totals['active_routes'] }} active route{{ $totals['active_routes'] === 1 ? '' : 's' }}
                    · {{ $totals['active_vehicles'] }} vehicle{{ $totals['active_vehicles'] === 1 ? '' : 's' }}
                    · {{ $totals['active_drivers'] }} driver{{ $totals['active_drivers'] === 1 ? '' : 's' }}
                </div>
            </div>

            @if ($totals['estimated_reimbursement'] !== null)
                <div class="text-right">
                    <div class="text-xs font-semibold uppercase tracking-wider text-success-700 dark:text-success-300">Estimated reimbursement</div>
                    <div class="mt-1 text-4xl font-extrabold text-success-700 dark:text-success-300">${{ number_format($totals['estimated_reimbursement'], 2) }}</div>
                    <div class="mt-1 text-xs ksde-muted">{{ $fmt($totals['reimbursable_miles']) }} mi × ${{ number_format($totals['reimbursement_rate'], 2) }}/mi <em>(rough estimate)</em></div>
                </div>
            @endif
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider ksde-muted">Reimbursable miles</div>
                <div class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">{{ $fmt($totals['reimbursable_miles']) }}</div>
                <div class="text-xs ksde-muted">{{ $totals['reimbursable_pct'] }}% of {{ $fmt($totals['all_miles']) }} total mi</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider ksde-muted">Rider-miles</div>
                <div class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">{{ $fmt($totals['reimbursable_rider_mi']) }}</div>
                <div class="text-xs ksde-muted">Σ miles × eligible / trip</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider ksde-muted">Eligible boardings</div>
                <div class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">{{ $fmt($totals['reimbursable_eligible']) }}</div>
                <div class="text-xs ksde-muted">avg {{ number_format($totals['avg_eligible_per_route_trip'], 1) }} per trip</div>
            </div>
            <div>
                <div class="text-xs font-semibold uppercase tracking-wider ksde-muted">Reimbursable trips</div>
                <div class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">{{ $fmt($totals['reimbursable_trips']) }}</div>
                <div class="text-xs ksde-muted">avg {{ number_format($totals['avg_reimb_miles_per_day'], 1) }} mi/day</div>
            </div>
        </div>
    </div>

    {{-- Data quality callout --}}
    @if (! empty($issues))
        <x-filament::section>
            <x-slot name="heading">Data quality checks ({{ count($issues) }})</x-slot>
            <x-slot name="description">Review these before filing the report — each one may affect the totals above.</x-slot>

            <ul class="space-y-2">
                @foreach ($issues as $issue)
                    @php
                        $colors = [
                            'danger'  => ['bg' => 'bg-danger-50 dark:bg-danger-500/10', 'text' => 'text-danger-900 dark:text-danger-200', 'dot' => 'bg-danger-500', 'border' => 'border-danger-500/30'],
                            'warning' => ['bg' => 'bg-warning-50 dark:bg-warning-500/10', 'text' => 'text-warning-900 dark:text-warning-200', 'dot' => 'bg-warning-500', 'border' => 'border-warning-500/30'],
                            'info'    => ['bg' => 'bg-info-50 dark:bg-info-500/10', 'text' => 'text-info-900 dark:text-info-200', 'dot' => 'bg-info-500', 'border' => 'border-info-500/30'],
                        ][$issue['severity']] ?? ['bg' => 'bg-gray-50', 'text' => 'text-gray-900', 'dot' => 'bg-gray-500', 'border' => 'border-gray-300'];
                    @endphp
                    <li class="flex items-start gap-3 rounded-lg border {{ $colors['border'] }} {{ $colors['bg'] }} p-3">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $colors['dot'] }}"></span>
                        <div class="text-sm {{ $colors['text'] }}">{{ $issue['message'] }}</div>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    {{-- Per-route detail --}}
    <x-filament::section>
        <x-slot name="heading">By route</x-slot>
        <x-slot name="description">Each row is a daily-route template for this reporting period. Rider-miles is the per-trip calc ({{ '{miles × eligible}' }}) summed across all runs.</x-slot>

        <div class="overflow-x-auto">
            <table class="ksde-table" id="ksde-route-table">
                <thead>
                    <tr>
                        <th class="ksde-sort-th ksde-sort-indicator" data-sort-key="code" data-sort-default="asc">Code</th>
                        <th class="ksde-sort-th ksde-sort-indicator" data-sort-key="name" data-sort-default="asc">Route</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="trips">Trips</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="miles">Miles</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="eligible">Eligible</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="ineligible">Ineligible</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="riderMiles">Rider-miles</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($routes as $r)
                        @php
                            $active = ($r->trips_count ?? 0) > 0;
                            $miles = (int) ($r->miles_sum ?? 0);
                            $elig  = (int) ($r->eligible_sum ?? 0);
                            $inelig = (int) ($r->ineligible_sum ?? 0);
                            $rmi   = (int) ($r->rider_miles_sum ?? 0);
                        @endphp
                        <tr class="{{ $active ? '' : 'ksde-muted' }}"
                            data-sort-code="{{ $r->code }}"
                            data-sort-name="{{ $r->name }}"
                            data-sort-trips="{{ (int) ($r->trips_count ?? 0) }}"
                            data-sort-miles="{{ $miles }}"
                            data-sort-eligible="{{ $elig }}"
                            data-sort-ineligible="{{ $inelig }}"
                            data-sort-rider-miles="{{ $rmi }}">
                            <td class="font-mono font-semibold">{{ $r->code }}</td>
                            <td>{{ $r->name }}</td>
                            <td class="ksde-num">{{ $active ? $fmt($r->trips_count) : '—' }}</td>
                            <td class="ksde-num">{{ $active ? $fmt($miles) : '—' }}</td>
                            <td class="ksde-num">{{ $active ? $fmt($elig) : '—' }}</td>
                            <td class="ksde-num">{{ $active ? $fmt($inelig) : '—' }}</td>
                            <td class="ksde-num font-semibold">{{ $active ? $fmt($rmi) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="ksde-muted text-center py-8">No routes defined.</td></tr>
                    @endforelse
                </tbody>
                @if ($routeRowsWithActivity->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="2">TOTAL · {{ $routeTotals['routes'] }} active route{{ $routeTotals['routes'] === 1 ? '' : 's' }}</td>
                            <td class="ksde-num">{{ $fmt($routeTotals['trips']) }}</td>
                            <td class="ksde-num">{{ $fmt($routeTotals['miles']) }}</td>
                            <td class="ksde-num">{{ $fmt($routeTotals['eligible']) }}</td>
                            <td class="ksde-num">{{ $fmt($routeTotals['ineligible']) }}</td>
                            <td class="ksde-num">{{ $fmt($routeTotals['rider_mi']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>

    {{-- Per-vehicle utilization --}}
    <x-filament::section>
        <x-slot name="heading">By vehicle</x-slot>
        <x-slot name="description">Daily-route miles are the reimbursable subset. The gap between "all miles" and "daily-route miles" is athletic / field / activity / maintenance use.</x-slot>

        <div class="overflow-x-auto">
            <table class="ksde-table" id="ksde-vehicle-table">
                <thead>
                    <tr>
                        <th class="ksde-sort-th ksde-sort-indicator" data-sort-key="unit" data-sort-default="asc">Unit</th>
                        <th class="ksde-sort-th ksde-sort-indicator" data-sort-key="vehType" data-sort-default="asc">Type</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="trips">Trips</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="allMiles">All miles</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="routeMiles">Daily-route miles</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="pctRoute">% on route</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($vehicles as $v)
                        @php
                            $all = (int) ($v->miles_sum ?? 0);
                            $route = (int) ($v->daily_route_miles ?? 0);
                            $pct = $all > 0 ? round(($route / $all) * 100, 1) : 0;
                        @endphp
                        <tr data-sort-unit="{{ $v->unit_number }}"
                            data-sort-veh-type="{{ $v->type }}"
                            data-sort-trips="{{ (int) ($v->trips_count ?? 0) }}"
                            data-sort-all-miles="{{ $all }}"
                            data-sort-route-miles="{{ $route }}"
                            data-sort-pct-route="{{ $pct }}">
                            <td class="font-mono font-semibold">{{ $v->unit_number }}</td>
                            <td>{{ \App\Models\Vehicle::types()[$v->type] ?? $v->type }}</td>
                            <td class="ksde-num">{{ $fmt($v->trips_count) }}</td>
                            <td class="ksde-num">{{ $fmt($all) }}</td>
                            <td class="ksde-num font-semibold">{{ $fmt($route) }}</td>
                            <td class="ksde-num ksde-muted">{{ $pct }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="ksde-muted text-center py-8">No vehicle activity in this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($vehicles->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="2">TOTAL · {{ $vehicleTotals['vehicles'] }} vehicle{{ $vehicleTotals['vehicles'] === 1 ? '' : 's' }}</td>
                            <td class="ksde-num">{{ $fmt($vehicleTotals['trips']) }}</td>
                            <td class="ksde-num">{{ $fmt($vehicleTotals['miles_all']) }}</td>
                            <td class="ksde-num">{{ $fmt($vehicleTotals['miles_route']) }}</td>
                            <td class="ksde-num ksde-muted">
                                {{ $vehicleTotals['miles_all'] > 0 ? round(($vehicleTotals['miles_route'] / $vehicleTotals['miles_all']) * 100, 1) : 0 }}%
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>

    {{-- All-activity context --}}
    <x-filament::section collapsible>
        <x-slot name="heading">By trip type — full activity</x-slot>
        <x-slot name="description">Everything the fleet did in this period, broken out by trip type. Click column headers to sort. Only <strong>Daily route</strong> feeds the KSDE claim; the rest is tracked for insurance and activity-fund accounting.</x-slot>

        <div class="overflow-x-auto">
            <table class="ksde-table" id="ksde-type-table">
                <thead>
                    <tr>
                        <th class="ksde-sort-th ksde-sort-indicator" data-sort-key="typeLabel" data-sort-default="asc">Type</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="trips">Trips</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="miles">Miles</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="passengers">Passengers</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="eligible">Eligible</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="ineligible">Ineligible</th>
                        <th class="ksde-sort-th ksde-sort-indicator ksde-num" data-sort-key="riderMiles">Rider-miles</th>
                        <th class="ksde-sort-th ksde-sort-indicator" data-sort-key="reimb" data-sort-default="desc">Reimbursable</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($types as $t)
                        @php
                            $label = $tripTypeLabels[$t->trip_type] ?? $t->trip_type;
                            $isReimb = $t->trip_type === \App\Models\Trip::TYPE_DAILY_ROUTE;
                            $badgeClass = match ($t->trip_type) {
                                \App\Models\Trip::TYPE_DAILY_ROUTE => 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-300',
                                \App\Models\Trip::TYPE_ATHLETIC    => 'bg-info-50 text-info-700 dark:bg-info-500/15 dark:text-info-300',
                                \App\Models\Trip::TYPE_ACTIVITY    => 'bg-info-50 text-info-700 dark:bg-info-500/15 dark:text-info-300',
                                \App\Models\Trip::TYPE_FIELD_TRIP  => 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300',
                                \App\Models\Trip::TYPE_MAINTENANCE => 'bg-gray-100 text-gray-700 dark:bg-gray-500/15 dark:text-gray-300',
                                default => 'bg-gray-100 text-gray-700 dark:bg-gray-500/15 dark:text-gray-300',
                            };
                        @endphp
                        <tr data-sort-type-label="{{ $label }}"
                            data-sort-trips="{{ (int) $t->trips }}"
                            data-sort-miles="{{ (int) $t->miles }}"
                            data-sort-passengers="{{ (int) $t->passengers }}"
                            data-sort-eligible="{{ (int) $t->eligible }}"
                            data-sort-ineligible="{{ (int) $t->ineligible }}"
                            data-sort-rider-miles="{{ (int) $t->rider_miles }}"
                            data-sort-reimb="{{ $isReimb ? 1 : 0 }}">
                            <td>
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">{{ $label }}</span>
                            </td>
                            <td class="ksde-num">{{ $fmt($t->trips) }}</td>
                            <td class="ksde-num">{{ $fmt($t->miles) }}</td>
                            <td class="ksde-num">{{ $fmt($t->passengers) }}</td>
                            <td class="ksde-num">{{ $fmt($t->eligible) }}</td>
                            <td class="ksde-num">{{ $fmt($t->ineligible) }}</td>
                            <td class="ksde-num">{{ $fmt($t->rider_miles) }}</td>
                            <td>
                                @if ($isReimb)
                                    <span class="text-success-700 dark:text-success-300 text-xs font-semibold">yes (KSDE)</span>
                                @else
                                    <span class="ksde-muted text-xs">no</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="ksde-muted text-center py-8">No trip activity in this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($types->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td>TOTAL · {{ $types->count() }} type{{ $types->count() === 1 ? '' : 's' }}</td>
                            <td class="ksde-num">{{ $fmt($typeTotals['trips']) }}</td>
                            <td class="ksde-num">{{ $fmt($typeTotals['miles']) }}</td>
                            <td class="ksde-num">{{ $fmt($typeTotals['passengers']) }}</td>
                            <td class="ksde-num">{{ $fmt($typeTotals['eligible']) }}</td>
                            <td class="ksde-num">{{ $fmt($typeTotals['ineligible']) }}</td>
                            <td class="ksde-num">{{ $fmt($typeTotals['rider_mi']) }}</td>
                            <td class="ksde-muted text-xs">
                                @php
                                    $pct = $typeTotals['miles'] > 0 ? round(($totals['reimbursable_miles'] / $typeTotals['miles']) * 100, 1) : 0;
                                @endphp
                                {{ $pct }}% reimb
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <p class="mt-4 text-xs ksde-muted">
            Miles are bus-odometer distance. Boardings count a student per trip — an AM + PM rider counts twice.
            "Reimbursable" = Kansas reimburses only Daily route miles × eligible riders.
            Non-reimbursable trips are paid from activity funds, gate receipts, field-trip fees, or the general fund.
        </p>
    </x-filament::section>

    <div class="ksde-no-print text-xs ksde-muted text-right">
        Tip: use <kbd class="px-1.5 py-0.5 border rounded text-[10px]">Ctrl/⌘ + P</kbd> to print or save as PDF — the filter form and sidebar drop out automatically.
    </div>
</x-filament-panels::page>
