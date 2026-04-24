@php
    $fleet = $this->getFleetMetrics();
    $drivers = $this->getDriverMetrics();
    $miles = $this->getMileageMetrics();
    $maint = $this->getMaintenanceMetrics();
    $safety = $this->getSafetyMetrics();
    $compliance = $this->getComplianceMetrics();
    $typeBreakdown = $this->getTripTypeBreakdown();
    $periodLabel = $this->getPeriodLabel();

    $fmtMoney = fn ($cents) => '$' . number_format(((int) $cents) / 100, 2);
    $fmtMiles = fn ($n) => number_format((int) $n);
@endphp

<x-filament-panels::page>
    {{-- Period picker + print button — hidden in print mode --}}
    <div class="br-controls" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: end; margin-bottom: 1.5rem;">
        <div>
            <label class="br-muted" style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem;">Period</label>
            <select wire:model.live="period"
                    style="padding: 0.4rem 0.75rem; border-radius: 0.375rem; border: 1px solid #cbd5e1; background: #fff; font-size: 0.875rem;">
                <option value="school_year">Current school year</option>
                <option value="last_school_year">Last school year</option>
                <option value="this_month">This month</option>
                <option value="last_month">Last month</option>
                <option value="this_quarter">This quarter</option>
                <option value="custom">Custom range</option>
            </select>
        </div>
        @if ($period === 'custom')
            <div>
                <label class="br-muted" style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem;">From</label>
                <input type="date" wire:model.live="customStart"
                       style="padding: 0.4rem 0.5rem; border-radius: 0.375rem; border: 1px solid #cbd5e1; background: #fff; font-size: 0.875rem;">
            </div>
            <div>
                <label class="br-muted" style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem;">To</label>
                <input type="date" wire:model.live="customEnd"
                       style="padding: 0.4rem 0.5rem; border-radius: 0.375rem; border: 1px solid #cbd5e1; background: #fff; font-size: 0.875rem;">
            </div>
        @endif
        <div>
            <label class="br-muted" style="display: block; font-size: 0.75rem; margin-bottom: 0.25rem;">Reimbursement rate ($/rider-mile)</label>
            <input type="number" step="0.0001" wire:model.live.debounce.500ms="reimbRate" placeholder="e.g. 0.90"
                   style="padding: 0.4rem 0.5rem; border-radius: 0.375rem; border: 1px solid #cbd5e1; background: #fff; font-size: 0.875rem; width: 10rem;">
        </div>
        <div style="margin-left: auto;">
            <button type="button" onclick="window.print()"
                    style="padding: 0.5rem 1rem; border-radius: 0.375rem; background: #1e293b; color: white; border: none; cursor: pointer; font-size: 0.875rem; font-weight: 500;">
                Print / PDF
            </button>
        </div>
    </div>

    <style>
        .br-hero { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        @media (min-width: 900px) { .br-hero { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        .br-card { background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.75rem; padding: 1.25rem; }
        .dark .br-card { background: #0f172a; border-color: rgb(255 255 255 / 0.08); color: #e5e7eb; }
        .br-hero .br-card { padding: 1.5rem 1.25rem; }
        .br-kicker { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; font-weight: 600; }
        .dark .br-kicker { color: #94a3b8; }
        .br-big { font-size: 2.75rem; font-weight: 800; line-height: 1.05; color: #0f172a; margin-top: 0.25rem; }
        .dark .br-big { color: #f1f5f9; }
        .br-unit { font-size: 1rem; font-weight: 500; color: #64748b; }
        .br-footer { font-size: 0.875rem; color: #475569; margin-top: 0.5rem; }
        .dark .br-footer { color: #94a3b8; }
        .br-grid-2 { display: grid; grid-template-columns: 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        @media (min-width: 900px) { .br-grid-2 { grid-template-columns: 1fr 1fr; } }
        .br-section-title { font-size: 1.125rem; font-weight: 700; margin: 0 0 0.75rem; color: #0f172a; }
        .dark .br-section-title { color: #f1f5f9; }
        .br-dl { margin: 0; display: grid; grid-template-columns: 1fr auto; gap: 0.25rem 1rem; font-size: 0.9375rem; }
        .br-dl dt { color: #475569; }
        .dark .br-dl dt { color: #94a3b8; }
        .br-dl dd { margin: 0; font-weight: 600; color: #0f172a; text-align: right; }
        .dark .br-dl dd { color: #f1f5f9; }
        .br-subtitle { font-size: 1rem; color: #475569; margin: 0 0 0.25rem; }
        .dark .br-subtitle { color: #94a3b8; }
        .br-muted { color: #64748b; }
        .dark .br-muted { color: #94a3b8; }
        .br-pill { display: inline-block; padding: 0.1rem 0.5rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; }
        .br-pill-red { background: rgb(254 226 226); color: rgb(153 27 27); }
        .br-pill-amber { background: rgb(254 243 199); color: rgb(146 64 14); }
        .br-pill-green { background: rgb(220 252 231); color: rgb(22 101 52); }

        /* Print styles — clean and paper-friendly */
        @media print {
            body { background: #fff !important; }
            .br-controls,
            .fi-topbar,
            .fi-sidebar,
            .fi-sidebar-open .fi-main-ctn,
            aside,
            nav { display: none !important; }
            .fi-main-ctn, .fi-main, .fi-page { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .br-card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; }
            .br-big { font-size: 2rem; }
            .br-hero { grid-template-columns: repeat(2, 1fr); gap: 0.5rem; }
            @page { margin: 0.5in; }
        }
    </style>

    <div class="br-card" style="margin-bottom: 1rem; padding: 1rem 1.25rem;">
        <div class="br-subtitle">Transportation report</div>
        <div style="font-size: 1.375rem; font-weight: 700;">{{ $periodLabel }}</div>
        <div class="br-muted" style="font-size: 0.8125rem; margin-top: 0.25rem;">
            As of {{ now()->format('F j, Y g:i a') }}
        </div>
    </div>

    {{-- Hero row --}}
    <div class="br-hero">
        <div class="br-card">
            <div class="br-kicker">Active fleet</div>
            <div class="br-big">{{ $fleet['total_active'] }}</div>
            <div class="br-footer">
                {{ $fleet['buses'] }} bus{{ $fleet['buses'] === 1 ? '' : 'es' }} ·
                {{ $fleet['light'] }} light vehicle{{ $fleet['light'] === 1 ? '' : 's' }}
                @if ($fleet['in_shop'] > 0)
                    · {{ $fleet['in_shop'] }} in shop
                @endif
            </div>
        </div>

        <div class="br-card">
            <div class="br-kicker">Active drivers</div>
            <div class="br-big">{{ $drivers['active'] }}</div>
            <div class="br-footer">
                @if ($drivers['cdl_expired'] > 0)
                    <span class="br-pill br-pill-red">{{ $drivers['cdl_expired'] }} CDL expired</span>
                @elseif ($drivers['cdl_expiring_60'] > 0)
                    <span class="br-pill br-pill-amber">{{ $drivers['cdl_expiring_60'] }} CDL expiring ≤ 60 days</span>
                @else
                    <span class="br-pill br-pill-green">all CDLs current</span>
                @endif
            </div>
        </div>

        <div class="br-card">
            <div class="br-kicker">Fleet miles</div>
            <div class="br-big">{{ $fmtMiles($miles['total_miles']) }}<span class="br-unit"> mi</span></div>
            <div class="br-footer">
                {{ number_format($miles['total_trips']) }} trip{{ $miles['total_trips'] === 1 ? '' : 's' }} ·
                {{ $fmtMiles($miles['reimbursable_miles']) }} mi reimbursable
            </div>
        </div>

        <div class="br-card">
            <div class="br-kicker">KSDE reimbursable</div>
            @if ($miles['estimated_reimbursement'] !== null)
                <div class="br-big">${{ number_format($miles['estimated_reimbursement'], 2) }}</div>
                <div class="br-footer">
                    {{ $fmtMiles($miles['rider_miles']) }} rider-miles @ ${{ number_format($miles['rate'], 4) }}
                </div>
            @else
                <div class="br-big">{{ $fmtMiles($miles['rider_miles']) }}<span class="br-unit"> rider-mi</span></div>
                <div class="br-footer">Enter a rate above to estimate dollars</div>
            @endif
        </div>
    </div>

    <div class="br-grid-2">
        {{-- Safety --}}
        <div class="br-card">
            <h3 class="br-section-title">Safety &amp; inspections</h3>
            <dl class="br-dl">
                <dt>Pre-trip inspections completed</dt>
                <dd>{{ number_format($safety['pre_trip_total']) }}</dd>

                <dt>Pass rate</dt>
                <dd>
                    @if ($safety['pre_trip_pass_rate'] !== null)
                        {{ $safety['pre_trip_pass_rate'] }}%
                    @else
                        —
                    @endif
                </dd>

                <dt>Passed with defects</dt>
                <dd>{{ $safety['pre_trip_with_defects'] }}</dd>

                <dt>Failed (do-not-operate)</dt>
                <dd>
                    @if ($safety['pre_trip_failed'] > 0)
                        <span class="br-pill br-pill-red">{{ $safety['pre_trip_failed'] }}</span>
                    @else
                        0
                    @endif
                </dd>

                <dt>Open defects right now</dt>
                <dd>
                    @if ($safety['open_defects'] > 0)
                        <span class="br-pill br-pill-amber">{{ $safety['open_defects'] }}</span>
                    @else
                        <span class="br-pill br-pill-green">0</span>
                    @endif
                </dd>
            </dl>
        </div>

        {{-- Maintenance --}}
        <div class="br-card">
            <h3 class="br-section-title">Maintenance spend</h3>
            <div style="display: flex; align-items: baseline; gap: 0.5rem; margin-bottom: 0.75rem;">
                <div style="font-size: 2rem; font-weight: 800;">{{ $fmtMoney($maint['total_cost_cents']) }}</div>
                <div class="br-muted">across {{ $maint['record_count'] }} record{{ $maint['record_count'] === 1 ? '' : 's' }}</div>
            </div>
            @if (! empty($maint['top_types']))
                <div class="br-muted" style="font-size: 0.8125rem; margin-bottom: 0.25rem;">Top services</div>
                <dl class="br-dl">
                    @foreach ($maint['top_types'] as $row)
                        <dt>{{ $row['label'] }} <span class="br-muted" style="font-weight: 400;">× {{ $row['count'] }}</span></dt>
                        <dd>{{ $fmtMoney($row['cost_cents']) }}</dd>
                    @endforeach
                </dl>
            @else
                <div class="br-muted">No maintenance records in period.</div>
            @endif
        </div>

        {{-- Compliance --}}
        <div class="br-card">
            <h3 class="br-section-title">Compliance — next 60 days</h3>
            <dl class="br-dl">
                <dt>CDLs expiring</dt>
                <dd>
                    {{ $drivers['cdl_expiring_60'] }}
                    @if ($drivers['cdl_expired'] > 0)
                        <span class="br-pill br-pill-red" style="margin-left: 0.25rem;">+ {{ $drivers['cdl_expired'] }} expired</span>
                    @endif
                </dd>
                <dt>DOT medicals expiring</dt>
                <dd>{{ $drivers['dot_expiring_60'] }}</dd>
                <dt>Vehicle registrations expiring</dt>
                <dd>
                    {{ $compliance['reg_expiring_60'] }}
                    @if ($compliance['reg_expired'] > 0)
                        <span class="br-pill br-pill-red" style="margin-left: 0.25rem;">+ {{ $compliance['reg_expired'] }} expired</span>
                    @endif
                </dd>
                <dt>Vehicle inspections expiring</dt>
                <dd>
                    {{ $compliance['inspection_expiring_60'] }}
                    @if ($compliance['inspection_expired'] > 0)
                        <span class="br-pill br-pill-red" style="margin-left: 0.25rem;">+ {{ $compliance['inspection_expired'] }} expired</span>
                    @endif
                </dd>
            </dl>
        </div>

        {{-- Trip-type breakdown --}}
        <div class="br-card">
            <h3 class="br-section-title">Fleet activity by trip type</h3>
            @if ($typeBreakdown->isEmpty())
                <div class="br-muted">No approved trips in period.</div>
            @else
                <table style="width: 100%; font-size: 0.9375rem; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 0.35rem 0.5rem 0.35rem 0; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Type</th>
                            <th style="padding: 0.35rem 0.5rem; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; text-align: right;">Trips</th>
                            <th style="padding: 0.35rem 0; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; text-align: right;">Miles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($typeBreakdown as $row)
                            <tr style="border-bottom: 1px solid rgb(229 231 235 / 0.5);">
                                <td style="padding: 0.35rem 0.5rem 0.35rem 0;">{{ \App\Models\Trip::types()[$row->trip_type] ?? $row->trip_type }}</td>
                                <td style="padding: 0.35rem 0.5rem; text-align: right;">{{ number_format((int) $row->trip_count) }}</td>
                                <td style="padding: 0.35rem 0; text-align: right; font-variant-numeric: tabular-nums;">{{ $fmtMiles($row->miles) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-filament-panels::page>
