<x-filament-panels::page>
    @php
        $vehicle = $this->getSelectedVehicle();
        $options = $this->getVehicleOptions();
        $timeline = $this->getTimeline();
        $urgencyStyle = [
            'overdue'  => ['bg' => 'bg-danger-50 dark:bg-danger-500/10', 'border' => 'border-danger-500/30', 'text' => 'text-danger-700 dark:text-danger-300', 'dot' => 'bg-danger-500'],
            'soon'     => ['bg' => 'bg-warning-50 dark:bg-warning-500/10', 'border' => 'border-warning-500/30', 'text' => 'text-warning-700 dark:text-warning-300', 'dot' => 'bg-warning-500'],
            'upcoming' => ['bg' => 'bg-info-50 dark:bg-info-500/10', 'border' => 'border-info-500/30', 'text' => 'text-info-700 dark:text-info-300', 'dot' => 'bg-info-500'],
            'ok'       => ['bg' => 'bg-success-50 dark:bg-success-500/10', 'border' => 'border-success-500/30', 'text' => 'text-success-700 dark:text-success-300', 'dot' => 'bg-success-500'],
        ];
    @endphp

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

        {{-- Upcoming / projected from schedule --}}
        @if ($timeline['future']->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">Projected — next due</x-slot>
                <x-slot name="description">Based on the configured schedule + the most recent service of each type.</x-slot>
                <div class="grid gap-3 md:grid-cols-2">
                    @foreach ($timeline['future'] as $row)
                        @php $style = $urgencyStyle[$row->urgency]; @endphp
                        <div class="rounded-lg border {{ $style['border'] }} {{ $style['bg'] }} p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold">{{ $row->service_label }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $row->interval }}</div>
                                </div>
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $style['text'] }} uppercase tracking-wider">
                                    <span class="inline-block h-2 w-2 rounded-full {{ $style['dot'] }}"></span>
                                    {{ $row->urgency }}
                                </span>
                            </div>

                            @if ($row->last_record)
                                <div class="mt-3 pt-3 border-t {{ $style['border'] }} text-sm">
                                    <div><strong>Next due:</strong>
                                        @if ($row->next_due_on) {{ $row->next_due_on->format('M j, Y') }}@endif
                                        @if ($row->next_due_miles !== null) @if ($row->next_due_on) · @endif{{ number_format($row->next_due_miles) }} mi@endif
                                    </div>
                                    <div class="text-xs mt-1 {{ $style['text'] }}">
                                        @if ($row->days_remaining !== null)
                                            @if ($row->days_remaining < 0)
                                                {{ abs($row->days_remaining) }} days overdue
                                            @else
                                                in {{ $row->days_remaining }} day{{ $row->days_remaining === 1 ? '' : 's' }}
                                            @endif
                                        @endif
                                        @if ($row->miles_remaining !== null)
                                            @if ($row->days_remaining !== null) · @endif
                                            @if ($row->miles_remaining < 0)
                                                {{ number_format(abs($row->miles_remaining)) }} mi overdue
                                            @else
                                                {{ number_format($row->miles_remaining) }} mi to go
                                            @endif
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                        Last: {{ $row->last_record->performed_on->format('M j, Y') }}
                                        @if ($row->last_record->odometer_at_service !== null)
                                            · {{ number_format($row->last_record->odometer_at_service) }} mi
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="mt-3 pt-3 border-t {{ $style['border'] }} text-xs text-gray-500 dark:text-gray-400 italic">
                                    No history for this service yet — log the first one and the projection will appear here.
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        {{-- Past service history --}}
        <x-filament::section collapsible>
            <x-slot name="heading">Service history ({{ $timeline['past']->count() }} most recent)</x-slot>
            <x-slot name="description">Chronological log of completed maintenance work.</x-slot>
            @if ($timeline['past']->isEmpty())
                <div class="text-center text-gray-500 py-6">No maintenance records for this vehicle yet.</div>
            @else
                <ol class="relative border-l border-gray-200 dark:border-white/10 ml-2 space-y-4">
                    @foreach ($timeline['past'] as $rec)
                        <li class="ml-4">
                            <span class="absolute -left-1.5 mt-1 h-3 w-3 rounded-full bg-primary-500 border-2 border-white dark:border-gray-900"></span>
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                <span class="font-semibold">{{ \App\Models\MaintenanceRecord::serviceTypes()[$rec->service_type] ?? $rec->service_type }}</span>
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $rec->performed_on->format('M j, Y') }}</span>
                                @if ($rec->odometer_at_service !== null)
                                    <span class="text-xs text-gray-500 font-mono">@ {{ number_format($rec->odometer_at_service) }} mi</span>
                                @endif
                                @if ($rec->cost_cents)
                                    <span class="text-xs text-gray-500">${{ number_format($rec->cost_cents / 100, 2) }}</span>
                                @endif
                            </div>
                            @if ($rec->performed_by)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">by {{ $rec->performed_by }}</div>
                            @endif
                            @if ($rec->notes)
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 italic">{{ $rec->notes }}</div>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
