@php
    $s = $this->getSummary();
    $rows = $this->getDriverRows();
@endphp

<x-filament-panels::page>
    <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; margin-bottom: 1rem;">
        <div>
            <label style="display: block; font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem;">Year</label>
            <input type="number" step="1" wire:model.live.debounce.500ms="year" style="padding: 0.4rem 0.5rem; border-radius: 0.375rem; border: 1px solid #cbd5e1; font-size: 0.875rem; width: 7rem;">
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem;">Drug rate (§382.305)</label>
            <input type="number" step="0.01" min="0" max="1" wire:model.live.debounce.500ms="drugRate" style="padding: 0.4rem 0.5rem; border-radius: 0.375rem; border: 1px solid #cbd5e1; font-size: 0.875rem; width: 7rem;">
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; color: #64748b; margin-bottom: 0.25rem;">Alcohol rate</label>
            <input type="number" step="0.01" min="0" max="1" wire:model.live.debounce.500ms="alcoholRate" style="padding: 0.4rem 0.5rem; border-radius: 0.375rem; border: 1px solid #cbd5e1; font-size: 0.875rem; width: 7rem;">
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr; gap: 1rem; margin-bottom: 1rem;" class="md:!grid-cols-4">
        <div style="background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 1rem 1.25rem;" class="dark:!bg-gray-900 dark:!border-white/10">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;">Testing pool</div>
            <div style="font-size: 2rem; font-weight: 800; line-height: 1.1;">{{ $s['pool_count'] }}</div>
            <div style="font-size: 0.8125rem; color: #64748b;">active CDL drivers</div>
        </div>
        @php
            $drugOnTarget = $s['actual_drug'] >= $s['required_drug'];
            $drugColor = $drugOnTarget ? '#16a34a' : '#f59e0b';
        @endphp
        <div style="background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 1rem 1.25rem;" class="dark:!bg-gray-900 dark:!border-white/10">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;">Random drug (YTD)</div>
            <div style="font-size: 2rem; font-weight: 800; line-height: 1.1; color: {{ $drugColor }};">
                {{ $s['actual_drug'] }} <span style="font-size: 1rem; color: #64748b; font-weight: 500;">/ {{ $s['required_drug'] }} target</span>
            </div>
            <div style="font-size: 0.8125rem; color: #64748b;">
                {{ $s['drug_rate_pct'] !== null ? $s['drug_rate_pct'] . '% of pool' : '—' }}
                @if ($drugOnTarget) · <span style="color: #16a34a; font-weight: 600;">on target</span> @else · <span style="color: #b45309; font-weight: 600;">need {{ max(0, $s['required_drug'] - $s['actual_drug']) }} more</span> @endif
            </div>
        </div>
        @php
            $alcoholOnTarget = $s['actual_alcohol'] >= $s['required_alcohol'];
            $alcoholColor = $alcoholOnTarget ? '#16a34a' : '#f59e0b';
        @endphp
        <div style="background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 1rem 1.25rem;" class="dark:!bg-gray-900 dark:!border-white/10">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;">Random alcohol (YTD)</div>
            <div style="font-size: 2rem; font-weight: 800; line-height: 1.1; color: {{ $alcoholColor }};">
                {{ $s['actual_alcohol'] }} <span style="font-size: 1rem; color: #64748b; font-weight: 500;">/ {{ $s['required_alcohol'] }} target</span>
            </div>
            <div style="font-size: 0.8125rem; color: #64748b;">
                {{ $s['alcohol_rate_pct'] !== null ? $s['alcohol_rate_pct'] . '% of pool' : '—' }}
                @if ($alcoholOnTarget) · <span style="color: #16a34a; font-weight: 600;">on target</span> @else · <span style="color: #b45309; font-weight: 600;">need {{ max(0, $s['required_alcohol'] - $s['actual_alcohol']) }} more</span> @endif
            </div>
        </div>
        <div style="background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 1rem 1.25rem;" class="dark:!bg-gray-900 dark:!border-white/10">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;">Violations / Open</div>
            <div style="font-size: 2rem; font-weight: 800; line-height: 1.1; color: {{ $s['violations'] > 0 ? '#dc2626' : '#16a34a' }};">{{ $s['violations'] }}</div>
            <div style="font-size: 0.8125rem; color: #64748b;">
                violating results this year
                @if ($s['open_selections'] > 0)
                    · <span style="color: #b45309;">{{ $s['open_selections'] }} open selection{{ $s['open_selections'] === 1 ? '' : 's' }}</span>
                @endif
            </div>
        </div>
    </div>

    <div style="background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; overflow-x: auto;" class="dark:!bg-gray-900 dark:!border-white/10">
        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
            <thead>
                <tr style="text-align: left; background: rgb(248 250 252);">
                    <th style="padding: 0.5rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Driver</th>
                    <th style="padding: 0.5rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Random drug</th>
                    <th style="padding: 0.5rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Random alcohol</th>
                    <th style="padding: 0.5rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Last random</th>
                    <th style="padding: 0.5rem 0.75rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Flags</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr style="border-top: 1px solid rgb(229 231 235 / 0.5);">
                        <td style="padding: 0.625rem 0.75rem;">
                            <div style="font-weight: 600;">{{ $row->driver->last_name }}, {{ $row->driver->first_name }}</div>
                            <div style="font-size: 0.75rem; color: #64748b;">
                                @if ($row->driver->license_class) Class {{ $row->driver->license_class }} @endif
                                @if ($row->driver->employee_id) · {{ $row->driver->employee_id }} @endif
                            </div>
                        </td>
                        <td style="padding: 0.625rem 0.75rem; font-variant-numeric: tabular-nums;">{{ $row->random_drug }}</td>
                        <td style="padding: 0.625rem 0.75rem; font-variant-numeric: tabular-nums;">{{ $row->random_alcohol }}</td>
                        <td style="padding: 0.625rem 0.75rem; font-size: 0.8125rem; color: #475569;">
                            {{ $row->last_random ? \Carbon\Carbon::parse($row->last_random)->format('Y-m-d') : '—' }}
                        </td>
                        <td style="padding: 0.625rem 0.75rem;">
                            @if ($row->has_violation)
                                <span style="display: inline-block; padding: 0.1rem 0.5rem; border-radius: 9999px; background: rgb(254 226 226); color: rgb(153 27 27); font-size: 0.75rem; font-weight: 600;">violation</span>
                            @endif
                            @if ($row->has_open)
                                <span style="display: inline-block; padding: 0.1rem 0.5rem; border-radius: 9999px; background: rgb(254 243 199); color: rgb(146 64 14); font-size: 0.75rem; font-weight: 600;">open</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: 1rem 0.75rem; color: #64748b; font-style: italic;">No CDL drivers in the pool.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
