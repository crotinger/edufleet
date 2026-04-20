<x-filament-panels::page>
    @php
        $board = $this->getBoard();
        $sum = $this->getSummary();
        $stateBadge = [
            'available' => ['bg' => 'bg-success-50 dark:bg-success-500/15', 'text' => 'text-success-700 dark:text-success-300', 'dot' => 'bg-success-500', 'rank' => 1],
            'reserved'  => ['bg' => 'bg-warning-50 dark:bg-warning-500/15', 'text' => 'text-warning-700 dark:text-warning-300', 'dot' => 'bg-warning-500', 'rank' => 2],
            'in_use'    => ['bg' => 'bg-info-50 dark:bg-info-500/15',       'text' => 'text-info-700 dark:text-info-300',       'dot' => 'bg-info-500',    'rank' => 3],
        ];
    @endphp

    <style>
        .va-table { border-collapse: collapse; width: 100%; }
        .va-table th { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; color: rgb(107 114 128); padding: 0.5rem 0.75rem; text-align: left; border-bottom: 1px solid rgb(229 231 235); }
        .dark .va-table th { color: rgb(156 163 175); border-bottom-color: rgb(55 65 81); }
        .va-table td { padding: 0.625rem 0.75rem; border-bottom: 1px solid rgb(243 244 246); font-variant-numeric: tabular-nums; vertical-align: top; }
        .dark .va-table td { border-bottom-color: rgb(31 41 55); }
        .va-table tbody tr:hover { background: rgb(249 250 251); }
        .dark .va-table tbody tr:hover { background: rgb(31 41 55 / 0.4); }
        .va-num { text-align: right; font-variant-numeric: tabular-nums; }
        .va-muted { color: rgb(107 114 128); }
        .dark .va-muted { color: rgb(156 163 175); }
        .va-sort-th { cursor: pointer; user-select: none; position: relative; padding-right: 1.25rem; transition: color 0.15s; }
        .va-sort-th:hover { color: rgb(55 65 81); }
        .dark .va-sort-th:hover { color: rgb(229 231 235); }
        .va-sort-th[data-active="true"] { color: rgb(37 99 235); }
        .dark .va-sort-th[data-active="true"] { color: rgb(96 165 250); }
        .va-sort-indicator::after {
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
        .va-sort-indicator[data-active="true"]::after { opacity: 1; }
        .va-sort-indicator[data-dir="desc"]::after {
            border-bottom: none;
            border-top: 4px solid currentColor;
            margin-top: 3px;
        }
        .va-badge { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.125rem 0.5rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.375rem; }
        .va-dot { display: inline-block; width: 0.5rem; height: 0.5rem; border-radius: 9999px; }
    </style>

    <script>
        window.vaInitSort = function (tableId, defaultKey, defaultDir) {
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
        document.addEventListener('DOMContentLoaded', () => vaInitSort('va-table', 'statusRank', 'desc'));
        document.addEventListener('livewire:navigated', () => vaInitSort('va-table', 'statusRank', 'desc'));
    </script>

    {{-- Summary counters --}}
    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-lg border border-gray-200 dark:border-white/10 p-4">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Active vehicles</div>
            <div class="mt-1 text-3xl font-bold">{{ $sum['total'] }}</div>
        </div>
        <div class="rounded-lg border border-success-500/30 bg-success-50 dark:bg-success-500/10 p-4">
            <div class="text-xs font-semibold uppercase tracking-wider text-success-700 dark:text-success-300">Available now</div>
            <div class="mt-1 text-3xl font-bold text-success-700 dark:text-success-300">{{ $sum['available'] }}</div>
        </div>
        <div class="rounded-lg border border-info-500/30 bg-info-50 dark:bg-info-500/10 p-4">
            <div class="text-xs font-semibold uppercase tracking-wider text-info-700 dark:text-info-300">In use</div>
            <div class="mt-1 text-3xl font-bold text-info-700 dark:text-info-300">{{ $sum['in_use'] }}</div>
        </div>
        <div class="rounded-lg border border-warning-500/30 bg-warning-50 dark:bg-warning-500/10 p-4">
            <div class="text-xs font-semibold uppercase tracking-wider text-warning-700 dark:text-warning-300">Reserved</div>
            <div class="mt-1 text-3xl font-bold text-warning-700 dark:text-warning-300">{{ $sum['reserved'] }}</div>
        </div>
    </div>

    <x-filament::section>
        <x-slot name="heading">Fleet status</x-slot>
        <x-slot name="description">Click any column header to sort. Default: most-constrained first (in-use → reserved → available). Click a unit number to edit the vehicle.</x-slot>

        <div class="overflow-x-auto">
            <table class="va-table" id="va-table">
                <thead>
                    <tr>
                        <th class="va-sort-th va-sort-indicator" data-sort-key="unit" data-sort-default="asc">Unit</th>
                        <th class="va-sort-th va-sort-indicator" data-sort-key="type" data-sort-default="asc">Type</th>
                        <th class="va-sort-th va-sort-indicator" data-sort-key="model" data-sort-default="asc">Make &amp; model</th>
                        <th class="va-sort-th va-sort-indicator" data-sort-key="statusRank" data-sort-default="desc">Status</th>
                        <th>Currently</th>
                        <th class="va-sort-th va-sort-indicator" data-sort-key="nextDate" data-sort-default="asc">Next reservation</th>
                        <th class="va-sort-th va-sort-indicator va-num" data-sort-key="upcomingCount" data-sort-default="desc">14-day count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($board as $row)
                        @php
                            $v = $row['vehicle'];
                            $cfg = $stateBadge[$row['state']];
                            $typeLabel = \App\Models\Vehicle::types()[$v->type] ?? $v->type;
                            $modelParts = array_filter([$v->year, $v->make, $v->model]);
                            $modelStr = trim(implode(' ', $modelParts));
                            $next = $row['upcoming']->first();
                            $nextDate = $next?->desired_start_at ?: $next?->issued_at;
                            $nextDateStr = $nextDate ? $nextDate->format('M j · g:i a') : '—';
                            $nextSort = $nextDate ? $nextDate->timestamp : 99999999999;
                        @endphp
                        <tr data-sort-unit="{{ $v->unit_number }}"
                            data-sort-type="{{ $typeLabel }}"
                            data-sort-model="{{ $modelStr }}"
                            data-sort-status-rank="{{ $cfg['rank'] }}"
                            data-sort-next-date="{{ $nextSort }}"
                            data-sort-upcoming-count="{{ $row['upcoming']->count() }}">
                            <td>
                                <a href="{{ \App\Filament\Resources\Vehicles\VehicleResource::getUrl('edit', ['record' => $v]) }}"
                                   class="font-mono font-bold hover:underline text-primary-600 dark:text-primary-400">{{ $v->unit_number }}</a>
                            </td>
                            <td>{{ $typeLabel }}</td>
                            <td class="va-muted">{{ $modelStr ?: '—' }}</td>
                            <td>
                                <span class="va-badge {{ $cfg['bg'] }} {{ $cfg['text'] }}">
                                    <span class="va-dot {{ $cfg['dot'] }}"></span>
                                    {{ $row['label'] }}
                                </span>
                            </td>
                            <td>
                                @if ($row['current'])
                                    <div style="font-size: 0.8125rem;">{{ $row['current']->purpose }}</div>
                                    <div class="va-muted" style="font-size: 0.75rem;">
                                        {{ $row['current']->expected_driver_name ?? 'driver TBD' }}@if ($row['current']->expected_return_at) · back {{ $row['current']->expected_return_at->format('g:i a') }}@endif
                                    </div>
                                @else
                                    <span class="va-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($next)
                                    <div style="font-size: 0.8125rem;">{{ $next->purpose }}</div>
                                    <div class="va-muted" style="font-size: 0.75rem;">{{ $nextDateStr }}@if ($next->expected_driver_name) · {{ $next->expected_driver_name }}@endif</div>
                                @else
                                    <span class="va-muted">none</span>
                                @endif
                            </td>
                            <td class="va-num">
                                @if ($row['upcoming']->count() > 0)
                                    {{ $row['upcoming']->count() }}
                                @else
                                    <span class="va-muted">0</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="va-muted" style="text-align: center; padding: 2rem;">No active vehicles in the fleet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <p class="text-xs va-muted">
        "In use" = a trip is in progress OR a reservation is claimed. "Reserved now" = approved reservation covering the current moment. "Available" = nothing on the books right now.
        Default sort: status (most-constrained first) so you can see the busy vehicles at the top.
    </p>
</x-filament-panels::page>
