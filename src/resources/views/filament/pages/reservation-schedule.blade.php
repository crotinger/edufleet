<x-filament-panels::page>
    @php
        $week = $this->getWeek();
        $grid = $this->getGrid();
        $today = now()->startOfDay();

        $cellBg = function (string $type, string $status): array {
            // bg / border / text
            if (in_array($status, ['denied', 'cancelled', 'expired'])) return ['#f3f4f6', '#d1d5db', '#6b7280'];
            return match ($type) {
                \App\Models\Trip::TYPE_DAILY_ROUTE => ['#dcfce7', '#86efac', '#166534'],
                \App\Models\Trip::TYPE_ATHLETIC    => ['#dbeafe', '#93c5fd', '#1e40af'],
                \App\Models\Trip::TYPE_FIELD_TRIP  => ['#fef3c7', '#fcd34d', '#92400e'],
                \App\Models\Trip::TYPE_ACTIVITY    => ['#cffafe', '#67e8f9', '#0e7490'],
                \App\Models\Trip::TYPE_MAINTENANCE => ['#e5e7eb', '#9ca3af', '#374151'],
                default                            => ['#e0e7ff', '#a5b4fc', '#3730a3'],
            };
        };
    @endphp

    <style>
        .rs-wrap { overflow-x: auto; }
        .rs-grid { border-collapse: separate; border-spacing: 0; min-width: 960px; width: 100%; }
        .rs-grid th, .rs-grid td { border-right: 1px solid #e5e7eb; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .dark .rs-grid th, .dark .rs-grid td { border-right-color: rgba(255,255,255,0.08); border-bottom-color: rgba(255,255,255,0.08); }
        .rs-grid th:last-child, .rs-grid td:last-child { border-right: none; }
        .rs-grid thead th {
            position: sticky; top: 0;
            background: #f9fafb; z-index: 1;
            padding: 0.625rem 0.75rem;
            font-size: 0.75rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.04em;
            color: #6b7280;
            text-align: left;
        }
        .dark .rs-grid thead th { background: rgb(24 24 27); color: #9ca3af; }
        .rs-grid td { padding: 0.375rem; min-height: 5rem; }
        .rs-unit-th {
            position: sticky; left: 0;
            background: inherit; z-index: 2;
            min-width: 10rem;
        }
        .rs-today { background: rgba(253, 224, 71, 0.12); }
        .dark .rs-today { background: rgba(253, 224, 71, 0.06); }
        .rs-weekend { background: rgba(249, 250, 251, 0.5); }
        .dark .rs-weekend { background: rgba(0, 0, 0, 0.2); }
        .rs-empty { color: #d1d5db; font-size: 0.75rem; text-align: center; padding: 1rem 0; }
        .dark .rs-empty { color: rgba(255,255,255,0.15); }
        .rs-event {
            display: block;
            padding: 0.375rem 0.5rem;
            border-radius: 0.375rem;
            border: 1px solid;
            margin-bottom: 0.25rem;
            font-size: 0.75rem;
            line-height: 1.3;
        }
        .rs-event:last-child { margin-bottom: 0; }
        .rs-event-time { font-variant-numeric: tabular-nums; font-weight: 600; font-size: 0.6875rem; opacity: 0.85; }
        .rs-event-title { font-weight: 600; margin-top: 0.125rem; overflow-wrap: break-word; }
        .rs-event-driver { opacity: 0.75; font-size: 0.6875rem; margin-top: 0.125rem; }
        .rs-event-pending { box-shadow: inset 0 0 0 2px rgba(234, 179, 8, 0.4); }
        .rs-nav button { padding: 0.4rem 0.8rem; }
    </style>

    {{-- Week navigator --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                Week of {{ $week['start']->format('M j') }} – {{ $week['end']->format('M j, Y') }}
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Shows every approved, claimed, pending, or recently-returned reservation for active vehicles.
            </p>
        </div>
        <div class="rs-nav flex items-center gap-2">
            <x-filament::button wire:click="prevWeek" color="gray" size="sm" icon="heroicon-o-chevron-left">Prev</x-filament::button>
            <x-filament::button wire:click="thisWeek" color="primary" size="sm">This week</x-filament::button>
            <x-filament::button wire:click="nextWeek" color="gray" size="sm" icon="heroicon-o-chevron-right" icon-position="after">Next</x-filament::button>
        </div>
    </div>

    {{-- Color legend --}}
    <div class="flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-400">
        @foreach ([
            'Daily route' => ['#dcfce7', '#166534'],
            'Athletic'    => ['#dbeafe', '#1e40af'],
            'Field trip'  => ['#fef3c7', '#92400e'],
            'Activity'    => ['#cffafe', '#0e7490'],
            'Maintenance' => ['#e5e7eb', '#374151'],
        ] as $label => $c)
            <span class="inline-flex items-center gap-1.5">
                <span style="background: {{ $c[0] }}; color: {{ $c[1] }}; padding: 1px 6px; border-radius: 3px; font-weight: 600;">●</span>
                {{ $label }}
            </span>
        @endforeach
        <span class="inline-flex items-center gap-1.5 text-gray-500">
            <span style="box-shadow: inset 0 0 0 2px rgba(234, 179, 8, 0.6); padding: 1px 8px; border-radius: 3px;"></span>
            Pending approval
        </span>
    </div>

    <div class="rs-wrap rounded-lg border border-gray-200 dark:border-white/10">
        <table class="rs-grid">
            <thead>
                <tr>
                    <th class="rs-unit-th">Vehicle</th>
                    @foreach ($week['days'] as $day)
                        @php
                            $isToday = $day->isSameDay($today);
                            $isWeekend = $day->isWeekend();
                        @endphp
                        <th class="{{ $isToday ? 'rs-today' : '' }} {{ $isWeekend ? 'rs-weekend' : '' }}">
                            <div>{{ $day->format('D') }}</div>
                            <div style="font-size: 0.875rem; font-weight: 700; color: inherit; text-transform: none; letter-spacing: 0;">{{ $day->format('M j') }}</div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($grid as $row)
                    @php $v = $row['vehicle']; @endphp
                    <tr>
                        <td class="rs-unit-th" style="padding: 0.625rem 0.75rem; background: #f9fafb;">
                            <div class="flex flex-col gap-0.5">
                                <a href="{{ \App\Filament\Resources\Vehicles\VehicleResource::getUrl('edit', ['record' => $v]) }}"
                                   class="font-mono font-bold text-primary-600 hover:underline">Unit {{ $v->unit_number }}</a>
                                <span class="text-xs text-gray-500">{{ \App\Models\Vehicle::types()[$v->type] ?? $v->type }}</span>
                                @if ($v->capacity_passengers)
                                    <span class="text-[10px] text-gray-400">seats {{ $v->capacity_passengers }}</span>
                                @endif
                            </div>
                        </td>
                        @for ($i = 0; $i < 7; $i++)
                            @php
                                $day = $week['days'][$i];
                                $events = $row['cells'][$i];
                                $isToday = $day->isSameDay($today);
                                $isWeekend = $day->isWeekend();
                            @endphp
                            <td class="{{ $isToday ? 'rs-today' : '' }} {{ $isWeekend && empty($events) ? 'rs-weekend' : '' }}">
                                @if (empty($events))
                                    <div class="rs-empty">—</div>
                                @else
                                    @foreach ($events as $e)
                                        @php
                                            [$bg, $border, $text] = $cellBg($e['type'], $e['status']);
                                            $pendingRing = in_array($e['status'], ['requested', 'pending']);
                                            $timeStr = $e['start'] && $e['start']->isSameDay($day)
                                                ? $e['start']->format('g:ia')
                                                : '↦';
                                            if ($e['end'] && $e['end']->isSameDay($day)) {
                                                $timeStr .= '–' . $e['end']->format('g:ia');
                                            } elseif ($e['end'] && $e['end']->gt($day->endOfDay())) {
                                                $timeStr .= '→';
                                            }
                                        @endphp
                                        <span class="rs-event {{ $pendingRing ? 'rs-event-pending' : '' }}"
                                              style="background: {{ $bg }}; border-color: {{ $border }}; color: {{ $text }};"
                                              title="{{ $e['purpose'] }} · {{ ucfirst(str_replace('_', ' ', $e['status'])) }}">
                                            <span class="rs-event-time">{{ $timeStr }}</span>
                                            <div class="rs-event-title">{{ $e['purpose'] }}</div>
                                            @if (! empty($e['driver']))
                                                <div class="rs-event-driver">{{ $e['driver'] }}</div>
                                            @endif
                                        </span>
                                    @endforeach
                                @endif
                            </td>
                        @endfor
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-gray-500 py-8">No active vehicles.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Today's column is shaded amber. Weekends are faintly shaded when empty. Events with a yellow ring are still awaiting admin approval.
    </p>
</x-filament-panels::page>
