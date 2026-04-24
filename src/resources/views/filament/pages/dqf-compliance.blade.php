@php
    $rows = $this->getDriverRows();
    $complete = $rows->where('pct', 100)->count();
    $partial = $rows->whereBetween('pct', [1, 99])->count();
    $empty = $rows->where('pct', 0)->count();
    $componentLabels = $rows->first()?->component_labels ?? [];
@endphp

<x-filament-panels::page>
    <div style="display: grid; grid-template-columns: 1fr; gap: 1rem; margin-bottom: 1rem;" class="md:!grid-cols-3">
        <div style="background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 1rem 1.25rem;" class="dark:!bg-gray-900 dark:!border-white/10">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;">Complete</div>
            <div style="font-size: 2rem; font-weight: 800; color: #16a34a; line-height: 1.1;">{{ $complete }}</div>
            <div style="font-size: 0.8125rem; color: #64748b;">drivers with all required DQF components</div>
        </div>
        <div style="background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 1rem 1.25rem;" class="dark:!bg-gray-900 dark:!border-white/10">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;">Partial</div>
            <div style="font-size: 2rem; font-weight: 800; color: #f59e0b; line-height: 1.1;">{{ $partial }}</div>
            <div style="font-size: 0.8125rem; color: #64748b;">missing at least one required item</div>
        </div>
        <div style="background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 1rem 1.25rem;" class="dark:!bg-gray-900 dark:!border-white/10">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;">Nothing on file</div>
            <div style="font-size: 2rem; font-weight: 800; color: #dc2626; line-height: 1.1;">{{ $empty }}</div>
            <div style="font-size: 0.8125rem; color: #64748b;">no DQF documents yet</div>
        </div>
    </div>

    <div style="background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; overflow-x: auto;" class="dark:!bg-gray-900 dark:!border-white/10">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
            <thead>
                <tr style="text-align: left; background: rgb(248 250 252);">
                    <th style="padding: 0.5rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Driver</th>
                    <th style="padding: 0.5rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Completion</th>
                    <th style="padding: 0.5rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Missing</th>
                    <th style="padding: 0.5rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Docs on file</th>
                    <th style="padding: 0.5rem 0.75rem;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $color = $row->pct === 100 ? '#16a34a' : ($row->pct >= 70 ? '#f59e0b' : '#dc2626');
                    @endphp
                    <tr style="border-top: 1px solid rgb(229 231 235 / 0.5);">
                        <td style="padding: 0.625rem 0.75rem;">
                            <div style="font-weight: 600;">{{ $row->driver->last_name }}, {{ $row->driver->first_name }}</div>
                            <div style="font-size: 0.75rem; color: #64748b;">
                                @if ($row->driver->license_class)
                                    Class {{ $row->driver->license_class }}
                                @endif
                                @if ($row->driver->employee_id)
                                    · {{ $row->driver->employee_id }}
                                @endif
                            </div>
                        </td>
                        <td style="padding: 0.625rem 0.75rem; min-width: 12rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="flex: 1; height: 0.375rem; background: rgb(229 231 235); border-radius: 9999px; overflow: hidden;">
                                    <div style="height: 100%; width: {{ $row->pct }}%; background: {{ $color }};"></div>
                                </div>
                                <div style="font-weight: 700; color: {{ $color }}; font-variant-numeric: tabular-nums; min-width: 3rem; text-align: right;">{{ $row->pct }}%</div>
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.125rem;">{{ $row->present_count }} of {{ $row->required_count }} required</div>
                        </td>
                        <td style="padding: 0.625rem 0.75rem; font-size: 0.75rem; color: #7f1d1d; max-width: 20rem;">
                            @if ($row->missing->isEmpty())
                                <span style="color: #16a34a;">None</span>
                            @else
                                {{ $row->missing->map(fn ($k) => $componentLabels[$k] ?? $k)->implode(', ') }}
                            @endif
                        </td>
                        <td style="padding: 0.625rem 0.75rem;">{{ $row->total_attachments }}</td>
                        <td style="padding: 0.625rem 0.75rem; white-space: nowrap; text-align: right;">
                            <a href="{{ $this->getDriverEditUrl($row->driver) }}"
                               style="padding: 0.35rem 0.625rem; border-radius: 0.25rem; background: #1e293b; color: #fff; text-decoration: none; font-size: 0.75rem; margin-right: 0.25rem;">Open</a>
                            @if ($row->total_attachments > 0)
                                <a href="{{ $this->getBinderUrl($row->driver) }}" target="_blank"
                                   style="padding: 0.35rem 0.625rem; border-radius: 0.25rem; background: #1e40af; color: #fff; text-decoration: none; font-size: 0.75rem;">ZIP</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 1rem 0.75rem; color: #64748b; font-style: italic;">No active CDL drivers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
