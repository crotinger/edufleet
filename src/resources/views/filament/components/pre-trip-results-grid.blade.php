@php
    $record = $getRecord();
    $results = $record?->results()->orderBy('id')->get() ?? collect();
    $byCategory = $results->groupBy('category_snapshot');
@endphp

@if ($results->isEmpty())
    <div style="color: rgb(107 114 128); font-style: italic; font-size: 0.875rem;">No items recorded.</div>
@else
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        @foreach ($byCategory as $category => $items)
            <div style="border: 1px solid rgb(229 231 235 / 0.3); border-radius: 0.5rem; padding: 0.75rem;">
                <div style="font-weight: 600; font-size: 0.8125rem; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.02em; color: rgb(107 114 128);">
                    {{ $category }}
                </div>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.25rem;">
                    @foreach ($items as $item)
                        @php
                            $color = match ($item->result) {
                                'pass' => 'rgb(22 163 74)',
                                'fail' => 'rgb(220 38 38)',
                                'na' => 'rgb(107 114 128)',
                                default => 'rgb(107 114 128)',
                            };
                            $bg = match ($item->result) {
                                'fail' => 'rgb(254 226 226 / 0.3)',
                                default => 'transparent',
                            };
                            $label = match ($item->result) {
                                'pass' => '✓ PASS',
                                'fail' => '✗ FAIL',
                                'na' => 'N/A',
                                default => strtoupper($item->result),
                            };
                        @endphp
                        <li style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.375rem 0.5rem; background: {{ $bg }}; border-radius: 0.25rem; font-size: 0.875rem;">
                            <span style="min-width: 4.5rem; color: {{ $color }}; font-weight: 700; font-size: 0.75rem; padding-top: 0.1rem;">
                                {{ $label }}
                            </span>
                            <div style="flex: 1; min-width: 0;">
                                <div>
                                    {{ $item->description_snapshot }}
                                    @if ($item->was_critical)
                                        <span style="margin-left: 0.25rem; font-size: 0.6875rem; padding: 0.05rem 0.3rem; background: rgb(220 38 38); color: white; border-radius: 0.25rem; vertical-align: 0.15em;">critical</span>
                                    @endif
                                </div>
                                @if ($item->comment)
                                    <div style="font-size: 0.75rem; color: rgb(107 114 128); margin-top: 0.125rem; font-style: italic;">
                                        {{ $item->comment }}
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@endif
