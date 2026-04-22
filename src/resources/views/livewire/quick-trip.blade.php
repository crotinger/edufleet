<div>
    <div class="qt-vehicle">
        <div class="unit">Unit {{ $vehicle->unit_number }}</div>
        <div class="meta">
            {{ \App\Models\Vehicle::types()[$vehicle->type] ?? $vehicle->type }}
            @if ($vehicle->make) · {{ $vehicle->make }} {{ $vehicle->model }}@endif
            @if ($vehicle->year) · {{ $vehicle->year }}@endif
        </div>
    </div>

    @if ($flash)
        <div class="qt-flash qt-flash-{{ $flashKind }}">{{ $flash }}</div>
    @endif

    @if ($step === 'done')
        <div class="qt-card" style="text-align: center; padding: 2rem 1.25rem;">
            <div style="font-size: 3rem; line-height: 1;" aria-hidden="true">&check;</div>
            <h2 style="margin: 0.5rem 0 0.25rem; font-size: 1.25rem;">Trip submitted</h2>
            <p style="color: #64748b; font-size: 0.875rem; margin: 0 0 1.25rem;">
                The transportation director will review and approve your entry. You can close this tab.
            </p>
            <a href="{{ url()->current() }}" class="qt-btn qt-btn-primary" style="text-decoration: none; display: inline-block; padding: 0.75rem 1.5rem; width: auto;">Log another trip</a>
        </div>

    @elseif ($step === 'disown' && $openTrip)
        <div class="qt-card" style="border-color: #fcd34d; background: #fffbeb;">
            <div style="margin-bottom: 0.875rem;">
                <span class="qt-badge qt-badge-orange">Not your trip</span>
            </div>
            <p style="margin: 0 0 1rem; font-size: 0.9375rem; color: #78350f;">
                <strong>{{ $openTrip->driver_name_override ?? 'A previous driver' }}</strong> started a trip
                ({{ $openTrip->purpose }}) {{ $openTrip->started_at->diffForHumans() }} and didn't log the end.
            </p>
            <p style="margin: 0 0 1rem; font-size: 0.8125rem; color: #78350f;">
                Enter the <strong>current odometer reading</strong> and PIN — we'll close their trip out, flag it for the transportation director to review, and then let you start your own trip.
            </p>

            <form wire:submit="disownTrip">
                <div class="qt-field">
                    <label class="qt-label" for="disown_odometer">Current odometer</label>
                    <input id="disown_odometer" class="qt-input" type="number" inputmode="numeric" pattern="[0-9]*"
                           wire:model="disown_odometer" min="{{ $openTrip->start_odometer }}" required>
                    <div class="qt-helper">Their trip started at {{ number_format($openTrip->start_odometer) }} mi.</div>
                    @error('disown_odometer')<div class="qt-error">{{ $message }}</div>@enderror
                </div>

                <div class="qt-field">
                    <label class="qt-label" for="disown_pin">PIN</label>
                    <input id="disown_pin" class="qt-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                           wire:model="pin" required maxlength="16">
                    @error('pin')<div class="qt-error">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="qt-btn qt-btn-primary" wire:loading.attr="disabled" style="background: #d97706;">
                    <span wire:loading.remove wire:target="disownTrip">Close their trip &amp; start mine</span>
                    <span wire:loading wire:target="disownTrip">Working…</span>
                </button>
                <button type="button" class="qt-btn" style="background: transparent; color: #475569; margin-top: 0.5rem; padding: 0.625rem;"
                        wire:click="cancelDisown">
                    Cancel — this actually is my trip
                </button>
            </form>
        </div>

    @elseif ($step === 'failed_critical')
        <div class="qt-card" style="border-color: #fca5a5; background: #fef2f2;">
            <div style="margin-bottom: 0.875rem;">
                <span class="qt-badge" style="background: #dc2626; color: #fff;">Do not operate</span>
            </div>
            <h2 style="margin: 0 0 0.5rem; font-size: 1.125rem; color: #7f1d1d;">Safety-critical items failed inspection</h2>
            <p style="margin: 0 0 1rem; font-size: 0.9375rem; color: #7f1d1d;">
                Report the vehicle as out-of-service to the transportation director. Do not start a trip in this vehicle.
            </p>
            <ul style="margin: 0 0 1rem; padding-left: 1.25rem; font-size: 0.875rem; color: #991b1b;">
                @foreach ($failedCriticalItems as $fail)
                    <li style="margin-bottom: 0.35rem;">
                        <strong>{{ $fail['category'] }}</strong> — {{ $fail['description'] }}
                        @if ($fail['comment'])
                            <div style="color: #991b1b; font-style: italic; font-size: 0.8125rem;">{{ $fail['comment'] }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
            <p style="margin: 0; font-size: 0.8125rem; color: #64748b;">
                The inspection has been logged. An admin will triage the defects and dispatch maintenance.
            </p>
        </div>

    @elseif ($step === 'inspection')
        <div class="qt-card">
            <div style="margin-bottom: 0.875rem;">
                <span class="qt-badge">Pre-trip inspection</span>
            </div>
            <p style="margin: 0 0 1rem; font-size: 0.875rem; color: #475569;">
                Walk around and verify each item. Tap <strong>Pass</strong>, <strong>Fail</strong>, or <strong>N/A</strong>.
                A failed critical item blocks trip start.
            </p>

            @error('inspection')
                <div class="qt-error" style="margin-bottom: 0.75rem;">{{ $message }}</div>
            @enderror

            <form wire:submit="submitInspection">
                <div class="qt-field">
                    <label class="qt-label" for="driver_name_ins">Your name</label>
                    <input id="driver_name_ins" class="qt-input" type="text" autocomplete="name"
                           wire:model="driver_name" required maxlength="128">
                    @error('driver_name')<div class="qt-error">{{ $message }}</div>@enderror
                </div>

                <div class="qt-field">
                    <label class="qt-label" for="start_odometer_ins">Current odometer</label>
                    <input id="start_odometer_ins" class="qt-input" type="number" inputmode="numeric" pattern="[0-9]*"
                           wire:model="start_odometer" min="0" required>
                    @error('start_odometer')<div class="qt-error">{{ $message }}</div>@enderror
                </div>

                @php
                    $grouped = collect($inspectionItems)->groupBy('category');
                @endphp
                @foreach ($grouped as $category => $rows)
                    <div style="margin-top: 1rem; padding-top: 0.5rem; border-top: 1px solid #e2e8f0;">
                        <div style="font-weight: 600; font-size: 0.8125rem; color: #475569; text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 0.5rem;">
                            {{ $category }}
                        </div>
                        @foreach ($rows as $itemId => $item)
                            @php $current = $inspectionResults[$itemId]['result'] ?? null; @endphp
                            <div style="padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9;">
                                <div style="font-size: 0.9375rem; line-height: 1.3; margin-bottom: 0.5rem;">
                                    {{ $item['description'] }}
                                    @if ($item['is_critical'])
                                        <span style="margin-left: 0.25rem; font-size: 0.6875rem; padding: 0.1rem 0.35rem; background: #dc2626; color: #fff; border-radius: 0.25rem; vertical-align: 0.15em;">critical</span>
                                    @endif
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.25rem;">
                                    <button type="button" wire:click="setInspectionResult({{ $itemId }}, 'pass')"
                                            class="qt-btn" style="padding: 0.5rem; font-size: 0.875rem; {{ $current === 'pass' ? 'background: #16a34a; color: #fff; border-color: #16a34a;' : 'background: #fff; color: #334155; border: 1px solid #cbd5e1;' }}">
                                        Pass
                                    </button>
                                    <button type="button" wire:click="setInspectionResult({{ $itemId }}, 'fail')"
                                            class="qt-btn" style="padding: 0.5rem; font-size: 0.875rem; {{ $current === 'fail' ? 'background: #dc2626; color: #fff; border-color: #dc2626;' : 'background: #fff; color: #334155; border: 1px solid #cbd5e1;' }}">
                                        Fail
                                    </button>
                                    <button type="button" wire:click="setInspectionResult({{ $itemId }}, 'na')"
                                            class="qt-btn" style="padding: 0.5rem; font-size: 0.875rem; {{ $current === 'na' ? 'background: #64748b; color: #fff; border-color: #64748b;' : 'background: #fff; color: #334155; border: 1px solid #cbd5e1;' }}">
                                        N/A
                                    </button>
                                </div>
                                @if ($current === 'fail')
                                    <input type="text" placeholder="Describe the defect (optional)"
                                           wire:model="inspectionResults.{{ $itemId }}.comment"
                                           class="qt-input" style="margin-top: 0.4rem; font-size: 0.875rem;">
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div class="qt-field" style="margin-top: 1rem;">
                    <label class="qt-label" for="pin_ins">PIN (from the label)</label>
                    <input id="pin_ins" class="qt-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                           wire:model="pin" required maxlength="16">
                    @error('pin')<div class="qt-error">{{ $message }}</div>@enderror
                </div>

                <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.875rem; padding: 0.75rem; background: #f8fafc; border-radius: 0.375rem; margin-top: 0.5rem; cursor: pointer;">
                    <input type="checkbox" wire:model="inspectionAffirmed" style="margin-top: 0.125rem;">
                    <span>
                        I affirm I personally performed this inspection and the results above are accurate.
                    </span>
                </label>
                @error('inspectionAffirmed')<div class="qt-error">{{ $message }}</div>@enderror

                <button type="submit" class="qt-btn qt-btn-primary" wire:loading.attr="disabled" style="margin-top: 0.875rem;">
                    <span wire:loading.remove wire:target="submitInspection">Submit inspection</span>
                    <span wire:loading wire:target="submitInspection">Submitting…</span>
                </button>
            </form>
        </div>

    @elseif ($step === 'end' && $openTrip)
        <div class="qt-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.875rem;">
                <span class="qt-badge qt-badge-orange">Trip in progress</span>
                <span style="font-size: 0.75rem; color: #64748b;">Started {{ $openTrip->started_at->diffForHumans() }}</span>
            </div>

            <dl class="qt-summary">
                <dt>Driver</dt><dd>{{ $openTrip->driver_name_override ?? 'Guest' }}</dd>
                <dt>Purpose</dt><dd>{{ $openTrip->purpose }}</dd>
                <dt>Started at odometer</dt><dd>{{ number_format($openTrip->start_odometer) }} mi</dd>
            </dl>

            <form wire:submit="endTrip">
                <div class="qt-field">
                    <label class="qt-label" for="end_odometer">End odometer (required)</label>
                    <input id="end_odometer" class="qt-input" type="number" inputmode="numeric" pattern="[0-9]*"
                           wire:model="end_odometer" min="{{ $openTrip->start_odometer }}" required>
                    @error('end_odometer')<div class="qt-error">{{ $message }}</div>@enderror
                </div>

                <div class="qt-row-3">
                    <div class="qt-field">
                        <label class="qt-label" for="passengers">On board</label>
                        <input id="passengers" class="qt-input" type="number" inputmode="numeric" pattern="[0-9]*"
                               wire:model="passengers" min="0" max="120" placeholder="0">
                        @error('passengers')<div class="qt-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="qt-field">
                        <label class="qt-label" for="riders_eligible">Eligible</label>
                        <input id="riders_eligible" class="qt-input" type="number" inputmode="numeric" pattern="[0-9]*"
                               wire:model="riders_eligible" min="0" max="120" placeholder="0">
                        @error('riders_eligible')<div class="qt-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="qt-field">
                        <label class="qt-label" for="riders_ineligible">Ineligible</label>
                        <input id="riders_ineligible" class="qt-input" type="number" inputmode="numeric" pattern="[0-9]*"
                               wire:model="riders_ineligible" min="0" max="120" placeholder="0">
                        @error('riders_ineligible')<div class="qt-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="qt-helper" style="margin-top: -0.5rem; margin-bottom: 0.875rem;">
                    Eligible = students 2.5+ miles from school · Ineligible = under 2.5 miles. Leave blank if not applicable.
                </div>

                <div class="qt-field">
                    <label class="qt-label" for="pin">PIN (from the label)</label>
                    <input id="pin" class="qt-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                           wire:model="pin" required maxlength="16">
                    @error('pin')<div class="qt-error">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="qt-btn qt-btn-success" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="endTrip">End trip & submit</span>
                    <span wire:loading wire:target="endTrip">Submitting…</span>
                </button>
            </form>

            <button type="button" class="qt-btn" style="background: transparent; color: #b45309; margin-top: 0.75rem; padding: 0.625rem; border: 1px dashed #fbbf24; font-size: 0.9375rem; font-weight: 500;"
                    wire:click="enterDisownFlow">
                Not my trip — the last driver forgot to close out
            </button>
        </div>

    @else
        <div class="qt-card">
            @if ($reservation)
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.875rem;">
                    <span class="qt-badge qt-badge-green">Reservation found</span>
                    <span style="font-size: 0.75rem; color: #64748b;">Issued {{ $reservation->issued_at->diffForHumans() }}</span>
                </div>
                <p style="margin: 0 0 1rem; font-size: 0.875rem; color: #475569;">
                    Confirm the details below. If anything's wrong, adjust — otherwise enter the odometer + PIN and hit start.
                </p>
            @else
                <div style="margin-bottom: 0.875rem;">
                    <span class="qt-badge">Quick trip</span>
                </div>
                <p style="margin: 0 0 1rem; font-size: 0.875rem; color: #475569;">
                    No reservation on file for this vehicle. Fill in your name + purpose and we'll log the trip. Transportation director will review it.
                </p>
            @endif

            <form wire:submit="startTrip">
                <div class="qt-field">
                    <label class="qt-label" for="driver_name">Your name</label>
                    <input id="driver_name" class="qt-input" type="text" autocomplete="name"
                           wire:model="driver_name" required maxlength="128">
                    @error('driver_name')<div class="qt-error">{{ $message }}</div>@enderror
                </div>

                <div class="qt-field">
                    <label class="qt-label" for="purpose">Purpose</label>
                    <input id="purpose" class="qt-input" type="text"
                           wire:model="purpose" placeholder="e.g. Parts pickup — McPherson" required maxlength="191">
                    @error('purpose')<div class="qt-error">{{ $message }}</div>@enderror
                </div>

                <div class="qt-field">
                    <label class="qt-label" for="trip_type">Trip type</label>
                    <select id="trip_type" class="qt-select" wire:model="trip_type" required>
                        @foreach (\App\Models\Trip::types() as $v => $l)
                            @if ($v !== \App\Models\Trip::TYPE_DAILY_ROUTE)
                                <option value="{{ $v }}">{{ $l }}</option>
                            @endif
                        @endforeach
                    </select>
                    @error('trip_type')<div class="qt-error">{{ $message }}</div>@enderror
                </div>

                <div class="qt-row">
                    <div class="qt-field">
                        <label class="qt-label" for="start_odometer">Start odometer</label>
                        <input id="start_odometer" class="qt-input" type="number" inputmode="numeric" pattern="[0-9]*"
                               wire:model="start_odometer" min="0" required>
                        @error('start_odometer')<div class="qt-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="qt-field">
                        <label class="qt-label" for="passengers">Passengers</label>
                        <input id="passengers" class="qt-input" type="number" inputmode="numeric" pattern="[0-9]*"
                               wire:model="passengers" min="0" max="120" placeholder="0">
                        @error('passengers')<div class="qt-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="qt-field">
                    <label class="qt-label" for="pin">PIN (from the label)</label>
                    <input id="pin" class="qt-input" type="text" inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                           wire:model="pin" required maxlength="16">
                    @error('pin')<div class="qt-error">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="qt-btn qt-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="startTrip">Start trip</span>
                    <span wire:loading wire:target="startTrip">Starting…</span>
                </button>
            </form>
        </div>
    @endif
</div>
