@php
    $totalRequired = collect($components)->where('required', true)->count();
    $presentRequired = collect($components)
        ->where('required', true)
        ->keys()
        ->filter(fn ($k) => ($present[$k] ?? collect())->isNotEmpty())
        ->count();
    $pct = $totalRequired > 0 ? (int) round(($presentRequired / $totalRequired) * 100) : 0;
    $statusColor = match (true) {
        $pct === 100 => '#16a34a',
        $pct >= 70 => '#f59e0b',
        default => '#dc2626',
    };
@endphp

<div style="margin-top: 1.5rem; background: #fff; border: 1px solid rgb(229 231 235); border-radius: 0.5rem; padding: 1rem 1.25rem;"
     class="dark:!bg-gray-900 dark:!border-white/10">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.75rem;">
        <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b;">FMCSA Driver Qualification File</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a;" class="dark:!text-slate-100">
                {{ $presentRequired }} of {{ $totalRequired }} required components on file
                <span style="margin-left: 0.5rem; padding: 0.15rem 0.5rem; border-radius: 9999px; background: {{ $statusColor }}; color: #fff; font-size: 0.75rem; vertical-align: 0.1em;">{{ $pct }}%</span>
            </div>
        </div>
        <a href="{{ route('dqf.binder', $driver) }}" target="_blank"
           style="padding: 0.5rem 0.875rem; border-radius: 0.375rem; background: #1e40af; color: #fff; text-decoration: none; font-size: 0.875rem; font-weight: 500;">
            Download binder ZIP
        </a>
    </div>

    <ul style="list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: 1fr; gap: 0.375rem;">
        @foreach ($components as $key => $meta)
            @php
                $files = $present[$key] ?? collect();
                $hasFile = $files->isNotEmpty();
                $requiredClass = $meta['required'] ? '' : ' font-normal';
            @endphp
            <li style="display: flex; align-items: flex-start; gap: 0.625rem; padding: 0.5rem 0.625rem; border-radius: 0.375rem; background: {{ $hasFile ? 'rgb(220 252 231 / 0.5)' : ($meta['required'] ? 'rgb(254 226 226 / 0.3)' : 'rgb(248 250 252)') }};">
                <span aria-hidden="true" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; color: #fff; background: {{ $hasFile ? '#16a34a' : ($meta['required'] ? '#dc2626' : '#94a3b8') }};">
                    {{ $hasFile ? '✓' : ($meta['required'] ? '!' : '?') }}
                </span>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 600; font-size: 0.875rem; color: #0f172a;" class="dark:!text-slate-100">
                        {{ $meta['label'] }}
                        @if (! $meta['required'])
                            <span style="font-weight: 400; font-size: 0.75rem; color: #64748b;">(optional)</span>
                        @endif
                    </div>
                    <div style="font-size: 0.75rem; color: #64748b;">{{ $meta['cite'] }}</div>
                    @if ($hasFile)
                        <div style="font-size: 0.75rem; color: #15803d; margin-top: 0.125rem;">
                            @foreach ($files as $f)
                                <a href="{{ route('attachments.download', $f) }}" target="_blank" style="color: #15803d; text-decoration: underline;">{{ $f->original_name }}</a>{{ ! $loop->last ? ',' : '' }}
                            @endforeach
                        </div>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.75rem;">
        Add missing items via the <strong>Documents</strong> tab above — set the "Driver Qualification File (DQF) component" field when uploading.
    </div>
</div>
