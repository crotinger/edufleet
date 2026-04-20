<x-filament-panels::page>
    @php
        $vehicle = $this->getSelectedVehicle();
        $options = $this->getVehicleOptions();
        $timeline = $this->getTimeline();
        $fmt = fn ($n) => number_format((int) $n);

        $urgencyStyle = [
            'overdue'  => ['bg' => 'bg-danger-50 dark:bg-danger-500/15',   'text' => 'text-danger-700 dark:text-danger-300',   'dot' => 'bg-danger-500',  'rank' => 0],
            'soon'     => ['bg' => 'bg-warning-50 dark:bg-warning-500/15', 'text' => 'text-warning-700 dark:text-warning-300', 'dot' => 'bg-warning-500', 'rank' => 1],
            'upcoming' => ['bg' => 'bg-info-50 dark:bg-info-500/15',       'text' => 'text-info-700 dark:text-info-300',       'dot' => 'bg-info-500',    'rank' => 2],
            'ok'       => ['bg' => 'bg-success-50 dark:bg-success-500/15', 'text' => 'text-success-700 dark:text-success-300', 'dot' => 'bg-success-500', 'rank' => 3],
        ];
    @endphp

    <style>
        .mt-table { border-collapse: collapse; width: 100%; }
        .mt-table th { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; color: rgb(107 114 128); padding: 0.5rem 0.75rem; text-align: left; border-bottom: 1px solid rgb(229 231 235); }
        .dark .mt-table th { color: rgb(156 163 175); border-bottom-color: rgb(55 65 81); }
        .mt-table td { padding: 0.625rem 0.75rem; border-bottom: 1px solid rgb(243 244 246); font-variant-numeric: tabular-nums; vertical-align: top; }
        .dark .mt-table td { border-bottom-color: rgb(31 41 55); }
        .mt-table tbody tr:hover { background: rgb(249 250 251); }
        .dark .mt-table tbody tr:hover { background: rgb(31 41 55 / 0.4); }
        .mt-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .mt-muted { color: rgb(107 114 128); }
        .dark .mt-muted { color: rgb(156 163 175); }
        .mt-sort-th { cursor: pointer; user-select: none; position: relative; padding-right: 1.25rem; transition: color 0.15s; }
        .mt-sort-th:hover { color: rgb(55 65 81); }
        .dark .mt-sort-th:hover { color: rgb(229 231 235); }
        .mt-sort-th[data-active="true"] { color: rgb(37 99 235); }
        .dark .mt-sort-th[data-active="true"] { color: rgb(96 165 250); }
        .mt-sort-indicator::after {
            content: '';
            position: absolute;
            right: 0.25rem;
            top: 50%;
            transform: translateY(-50%);
            width: 0; height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-bottom: 4px solid currentColor;
            opacity: 0.3;
            margin-top: -3px;
        }
        .mt-sort-indicator[data-active="true"]::after { opacity: 1; }
        .mt-sort-indicator[data-dir="desc"]::after {
            border-bottom: none;
            border-top: 4px solid currentColor;
            margin-top: 3px;
        }
        .mt-badge { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.125rem 0.5rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.375rem; }
        .mt-dot { display: inline-block; width: 0.5rem; height: 0.5rem; border-radius: 9999px; }
    </style>

    <script>
        window.mtInitSort = function (tableId, defaultKey, defaultDir) {
            const table = document.getElementById(tableId);
            if (!table) return;
            let sortKey = defaultKey, sortDir = defaultDir || 'asc';
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
                    const prop = 'sort' + sortKey[0].toUpperCase() + sortKey.slice(1);
                    const av = a.dataset[prop] ?? '';
                    const bv = b.dataset[prop] ?? '';
                    const an = parseFloat(av), bn = parseFloat(bv);
                    let cmp;
                    if (!isNaN(an) && !isNaN(bn) && av !== '' && bv !== '') cmp = an - bn;
                    else cmp = String(av).localeCompare(String(bv), undefined, { numeric: true, sensitivity: 'base' });
                    return sortDir === 'desc' ? -cmp : cmp;
                });
                rows.forEach(r => tbody.appendChild(r));
            };

            headers.forEach(h => {
                h.addEventListener('click', () => {
                    const key = h.dataset.sortKey;
                    if (sortKey === key) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                    else { sortKey = key; sortDir = h.dataset.sortDefault || 'asc'; }
                    apply();
                });
            });
            apply();
        };
        document.addEventListener('DOMContentLoaded', () => {
            mtInitSort('mt-future-table', 'urgencyRank', 'asc');
            mtInitSort('mt-past-table', 'date', 'desc');
        });
        document.addEventListener('livewire:navigated', () => {
            mtInitSort('mt-future-table', 'urgencyRank', 'asc');
            mtInitSort('mt-past-table', 'date', 'desc');
        });
    </script>

    <div class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-64">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vehicle</label>
            <select wire:model.live="vehicle_id" class="fi-input fi-select block w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 px-3 py-2 text-sm">
                <option value="">— pick a vehicle —</option>
                @foreach ($options as $v)
                    <option value="{{ $v->id }}">
                        Unit {{ $v->unit_number }} · {{ \App\Models\Vehicle::types()[$v->type] ?? $v->type }}
                        @if ($v->odometer_miles !== null) · {{ number_format($v->odometer_miles) }} mi @endif
                    </option>
                @endforeach
            </select>
        </div>

        @if ($vehicle)
            <div>
                <a href="{{ \App\Filament\Resources\Vehicles\VehicleResource::getUrl('edit', ['record' => $vehicle]) }}"
                   class="fi-btn fi-btn-color-gray inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-white/10 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-white/5">
                    Edit vehicle / schedule →
                </a>
            </div>
        @endif
    </div>

    @if (! $vehicle)
        <x-filament::section>
            <div class="text-center text-gray-500 py-8">Pick a vehicle to see its maintenance timeline.</div>
        </x-filament::section>
    @else
        {{-- Vehicle summary --}}
        <x-filament::section>
            <x-slot name="heading">Unit {{ $vehicle->unit_number }} — {{ trim(($vehicle->year ? $vehicle->year . ' ' : '') . $vehicle->make . ' ' . $vehicle->model) ?: 'no model info' }}</x-slot>
            <x-slot name="description">
                Current odometer: <strong>{{ $vehicle->odometer_miles !== null ? number_format($vehicle->odometer_miles) . ' mi' : 'unknown' }}</strong>
                @if ($vehicle->fuel_type) · {{ ucfirst($vehicle->fuel_type) }}@endif
                @if ($vehicle->capacity_passengers) · seats {{ $vehicle->capacity_passengers }}@endif
                @if ($timeline['schedules']->isEmpty())
                    · <span class="text-warning-600 dark:text-warning-400">No schedule defined yet — set one on the vehicle edit page.</span>
                @endif
            </x-slot>
        </x-filament::section>

        {{-- Projected schedule — sortable sheet --}}
        @if ($timeline['future']->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">Projected — next due</x-slot>
                <x-slot name="description">Click any column header to sort. Default: urgency (overdue first). Based on the configured schedule + the most recent service of each type.</x-slot>

                <div class="overflow-x-auto">
                    <table class="mt-table" id="mt-future-table">
                        <thead>
                            <tr>
                                <th class="mt-sort-th mt-sort-indicator" data-sort-key="service" data-sort-default="asc">Service</th>
                                <th class="mt-sort-th mt-sort-indicator" data-sort-key="cadence" data-sort-default="asc">Cadence</th>
                                <th class="mt-sort-th mt-sort-indicator" data-sort-key="last" data-sort-default="desc">Last performed</th>
                                <th class="mt-sort-th mt-sort-indicator" data-sort-key="nextDate" data-sort-default="asc">Next due date</th>
                                <th class="mt-sort-th mt-sort-indicator mt-num" data-sort-key="nextMiles" data-sort-default="asc">Next due mi</th>
                                <th class="mt-sort-th mt-sort-indicator mt-num" data-sort-key="daysLeft" data-sort-default="asc">Days left</th>
                                <th class="mt-sort-th mt-sort-indicator mt-num" data-sort-key="milesLeft" data-sort-default="asc">Miles left</th>
                                <th class="mt-sort-th mt-sort-indicator" data-sort-key="urgencyRank" data-sort-default="asc">Urgency</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($timeline['future'] as $row)
                                @php
                                    $style = $urgencyStyle[$row->urgency];
                                    $hasHistory = (bool) $row->last_record;
                                    $lastStr = $hasHistory
                                        ? $row->last_record->performed_on->format('M j, Y')
                                            . ($row->last_record->odometer_at_service !== null ? ' @ ' . $fmt($row->last_record->odometer_at_service) . ' mi' : '')
                                        : '—';
                                    $nextDateStr = $row->next_due_on ? $row->next_due_on->format('M j, Y') : '—';
                                    $nextMilesStr = $row->next_due_miles !== null ? $fmt($row->next_due_miles) : '—';
                                    $daysLeftStr = $row->days_remaining !== null
                                        ? ($row->days_remaining < 0 ? '-' . abs($row->days_remaining) : $row->days_remaining)
                                        : '—';
                                    $milesLeftStr = $row->miles_remaining !== null
                                        ? ($row->miles_remaining < 0 ? '-' . $fmt(abs($row->miles_remaining)) : $fmt($row->miles_remaining))
                                        : '—';
                                @endphp
                                <tr
                                    data-sort-service="{{ $row->service_label }}"
                                    data-sort-cadence="{{ $row->interval }}"
                                    data-sort-last="{{ $hasHistory ? $row->last_record->performed_on->timestamp : 0 }}"
                                    data-sort-next-date="{{ $row->next_due_on ? $row->next_due_on->timestamp : 99999999999 }}"
                                    data-sort-next-miles="{{ $row->next_due_miles ?? 99999999999 }}"
                                    data-sort-days-left="{{ $row->days_remaining ?? 99999999 }}"
                                    data-sort-miles-left="{{ $row->miles_remaining ?? 99999999 }}"
                                    data-sort-urgency-rank="{{ $style['rank'] }}"
                                >
                                    <td><strong>{{ $row->service_label }}</strong></td>
                                    <td class="mt-muted">{{ $row->interval }}</td>
                                    <td class="{{ $hasHistory ? '' : 'mt-muted italic' }}">
                                        @if ($hasHistory)
                                            {{ $row->last_record->performed_on->format('M j, Y') }}
                                            @if ($row->last_record->odometer_at_service !== null)
                                                <div class="mt-muted text-xs">@ {{ $fmt($row->last_record->odometer_at_service) }} mi</div>
                                            @endif
                                        @else
                                            no history yet
                                        @endif
                                    </td>
                                    <td>{{ $nextDateStr }}</td>
                                    <td class="mt-num">{{ $nextMilesStr }}</td>
                                    <td class="mt-num {{ $row->days_remaining !== null && $row->days_remaining < 0 ? 'text-danger-600 font-semibold' : '' }}">
                                        {{ $daysLeftStr }}
                                    </td>
                                    <td class="mt-num {{ $row->miles_remaining !== null && $row->miles_remaining < 0 ? 'text-danger-600 font-semibold' : '' }}">
                                        {{ $milesLeftStr }}
                                    </td>
                                    <td>
                                        <span class="mt-badge {{ $style['bg'] }} {{ $style['text'] }}">
                                            <span class="mt-dot {{ $style['dot'] }}"></span>
                                            {{ $row->urgency }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- Past service history — sortable sheet --}}
        <x-filament::section collapsible>
            <x-slot name="heading">Service history ({{ $timeline['past']->count() }} most recent)</x-slot>
            <x-slot name="description">Click any column header to sort. Default: most recent first.</x-slot>

            @if ($timeline['past']->isEmpty())
                <div class="text-center text-gray-500 py-6">No maintenance records for this vehicle yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="mt-table" id="mt-past-table">
                        <thead>
                            <tr>
                                <th class="mt-sort-th mt-sort-indicator" data-sort-key="date" data-sort-default="desc">Date</th>
                                <th class="mt-sort-th mt-sort-indicator" data-sort-key="service" data-sort-default="asc">Service</th>
                                <th class="mt-sort-th mt-sort-indicator mt-num" data-sort-key="odometer" data-sort-default="desc">Odometer</th>
                                <th class="mt-sort-th mt-sort-indicator mt-num" data-sort-key="cost" data-sort-default="desc">Cost</th>
                                <th class="mt-sort-th mt-sort-indicator" data-sort-key="performedBy" data-sort-default="asc">Performed by</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($timeline['past'] as $rec)
                                <tr
                                    data-sort-date="{{ $rec->performed_on->timestamp }}"
                                    data-sort-service="{{ \App\Models\MaintenanceRecord::serviceTypes()[$rec->service_type] ?? $rec->service_type }}"
                                    data-sort-odometer="{{ $rec->odometer_at_service ?? 0 }}"
                                    data-sort-cost="{{ $rec->cost_cents ?? 0 }}"
                                    data-sort-performed-by="{{ $rec->performed_by ?? '' }}"
                                >
                                    <td>{{ $rec->performed_on->format('M j, Y') }}</td>
                                    <td><strong>{{ \App\Models\MaintenanceRecord::serviceTypes()[$rec->service_type] ?? $rec->service_type }}</strong></td>
                                    <td class="mt-num">
                                        {{ $rec->odometer_at_service !== null ? $fmt($rec->odometer_at_service) . ' mi' : '—' }}
                                    </td>
                                    <td class="mt-num">
                                        {{ $rec->cost_cents ? '$' . number_format($rec->cost_cents / 100, 2) : '—' }}
                                    </td>
                                    <td class="{{ $rec->performed_by ? '' : 'mt-muted' }}">
                                        {{ $rec->performed_by ?: '—' }}
                                    </td>
                                    <td class="mt-muted text-xs" style="max-width: 24rem;">
                                        {{ $rec->notes ?: '' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
