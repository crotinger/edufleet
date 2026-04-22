@php
    $vehicles = $this->eligibleVehicles;
    $vehiclesWithDepot = $vehicles->filter(fn ($v) => $v->hasDepot())->count();
    $vehiclesWithoutDepot = $vehicles->count() - $vehiclesWithDepot;
    $vehicleMarkers = $this->getVehicleMarkers();
    $studentMarkers = $this->getStudentMarkers();
    $centers = $this->attendanceCenters;
@endphp

<x-filament-panels::page>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <div
        x-data="routeOptimizer({
            vehicleMarkers: @js($vehicleMarkers),
            studentMarkers: @js($studentMarkers),
            initialSchoolLat: @js($schoolLat),
            initialSchoolLng: @js($schoolLng),
        })"
        x-init="init()"
        class="grid grid-cols-1 lg:grid-cols-[22rem_1fr] gap-4"
        style="height: calc(100vh - 14rem); min-height: 560px;"
    >
        {{-- Sidebar --}}
        <div class="space-y-3 overflow-y-auto pr-1">
            {{-- Vehicles --}}
            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Vehicles</div>
                @if ($vehiclesWithDepot === 0)
                    <div class="text-xs text-warning-700 dark:text-warning-300 rounded border border-warning-500/30 bg-warning-50 dark:bg-warning-500/10 p-2">
                        No vehicles have a depot set. Open any Vehicle → Default depot section, click on the map, save.
                    </div>
                @endif
                <ul class="space-y-1 max-h-64 overflow-y-auto">
                    @foreach ($vehicles as $v)
                        <li class="flex items-start gap-2 text-xs py-0.5">
                            <input type="checkbox"
                                   wire:model.live="selectedVehicleIds"
                                   value="{{ $v->id }}"
                                   @disabled(! $v->hasDepot())
                                   class="mt-0.5 rounded border-gray-300" />
                            <div class="flex-1 min-w-0">
                                <div class="font-medium">{{ $v->unit_number }}
                                    <span class="text-gray-500 dark:text-gray-400">· {{ $v->capacity_passengers ?? '?' }} seats</span>
                                </div>
                                <div class="text-[11px]">
                                    @if ($v->hasDepot())
                                        <span class="text-success-600 dark:text-success-400">depot set</span>
                                    @else
                                        <span class="text-danger-600 dark:text-danger-400">no depot — set on Vehicle edit page</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">
                    Selected: {{ count($selectedVehicleIds) }} of {{ $vehiclesWithDepot }} depot-ready.
                </div>
            </div>

            {{-- Student pool --}}
            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">Student pool</div>
                <label class="block text-[11px] text-gray-500 mb-1">Attendance center</label>
                <select wire:model.live="attendanceCenter"
                        class="w-full rounded border-gray-300 dark:border-white/10 dark:bg-white/5 text-xs px-2 py-1">
                    <option value="">— All active geocoded students —</option>
                    @foreach ($centers as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
                <div class="mt-2 text-xs">
                    Pool size: <span class="font-semibold">{{ $this->studentPool->count() }}</span> student{{ $this->studentPool->count() === 1 ? '' : 's' }}
                </div>
            </div>

            {{-- School --}}
            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">School drop-off</div>
                <div class="grid grid-cols-2 gap-2 mb-2">
                    <input type="number" step="0.000001" placeholder="Lat"
                           wire:model.live.debounce.500ms="schoolLat"
                           class="rounded border-gray-300 dark:border-white/10 dark:bg-white/5 text-xs px-2 py-1" />
                    <input type="number" step="0.000001" placeholder="Lng"
                           wire:model.live.debounce.500ms="schoolLng"
                           class="rounded border-gray-300 dark:border-white/10 dark:bg-white/5 text-xs px-2 py-1" />
                </div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400">
                    Click anywhere on the map to set or move the drop-off point.
                </div>
            </div>

            {{-- Action --}}
            <button type="button"
                    wire:click="solve"
                    class="w-full rounded-lg bg-primary-600 text-white px-3 py-2 text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                Solve
            </button>
        </div>

        {{-- Map --}}
        <div wire:ignore class="rounded-lg overflow-hidden border border-gray-200 dark:border-white/10 bg-gray-100" style="min-height: 560px;">
            <div x-ref="map" style="width:100%;height:100%;min-height:560px;"></div>
        </div>
    </div>

    <script>
    (function () {
        if (window.routeOptimizer) return;

        window.routeOptimizer = function (config) {
            return {
                vehicleMarkers: config.vehicleMarkers || [],
                studentMarkers: config.studentMarkers || [],
                schoolLat: config.initialSchoolLat,
                schoolLng: config.initialSchoolLng,
                map: null,
                _vehicleLayer: null,
                _studentLayer: null,
                _schoolMarker: null,

                init() {
                    if (typeof L === 'undefined') {
                        setTimeout(() => this.init(), 100);
                        return;
                    }
                    const allPoints = [
                        ...this.vehicleMarkers.map(v => [v.lat, v.lng]),
                        ...this.studentMarkers.map(s => [s.lat, s.lng]),
                    ];
                    if (this.schoolLat && this.schoolLng) {
                        allPoints.push([this.schoolLat, this.schoolLng]);
                    }

                    this.map = L.map(this.$refs.map);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(this.map);

                    if (allPoints.length > 0) {
                        this.map.fitBounds(L.latLngBounds(allPoints), { maxZoom: 13, padding: [30, 30] });
                    } else {
                        this.map.setView([39.8283, -98.5795], 4);
                    }

                    this.drawVehicles();
                    this.drawStudents();
                    this.drawSchool();

                    this.map.on('click', (e) => this.setSchool(e.latlng.lat, e.latlng.lng));

                    setTimeout(() => this.map?.invalidateSize(), 80);
                    setTimeout(() => this.map?.invalidateSize(), 400);

                    // When the attendance center filter changes, Livewire
                    // re-renders with a new student list — reload markers.
                    this.$wire.on('optimizer-filter-changed', () => {
                        this.$wire.$refresh();
                    });

                    // Let Livewire state sync into Alpine when server echoes back.
                    this.$watch('schoolLat', () => this.drawSchool());
                    this.$watch('schoolLng', () => this.drawSchool());
                },

                drawVehicles() {
                    if (this._vehicleLayer) this.map.removeLayer(this._vehicleLayer);
                    this._vehicleLayer = L.layerGroup().addTo(this.map);
                    this.vehicleMarkers.forEach(v => {
                        const icon = L.divIcon({
                            className: '',
                            html: `<div style="background:#2563eb;color:#fff;border-radius:6px;padding:3px 6px;font:600 11px sans-serif;box-shadow:0 1px 2px rgba(0,0,0,.3);white-space:nowrap">${v.unit}</div>`,
                            iconSize: null,
                            iconAnchor: [18, 10],
                        });
                        L.marker([v.lat, v.lng], { icon })
                            .bindTooltip(`Unit ${v.unit} · ${v.capacity} seats`, { direction: 'top' })
                            .addTo(this._vehicleLayer);
                    });
                },

                drawStudents() {
                    if (this._studentLayer) this.map.removeLayer(this._studentLayer);
                    this._studentLayer = L.layerGroup().addTo(this.map);
                    this.studentMarkers.forEach(s => {
                        L.circleMarker([s.lat, s.lng], {
                            radius: 4,
                            color: '#6b7280',
                            weight: 1,
                            fillColor: '#9ca3af',
                            fillOpacity: 0.6,
                        })
                            .bindTooltip(`${s.name}${s.center ? ' · ' + s.center : ''}`, { direction: 'top' })
                            .addTo(this._studentLayer);
                    });
                },

                drawSchool() {
                    if (this._schoolMarker) {
                        this.map.removeLayer(this._schoolMarker);
                        this._schoolMarker = null;
                    }
                    const lat = parseFloat(this.schoolLat);
                    const lng = parseFloat(this.schoolLng);
                    if (!isFinite(lat) || !isFinite(lng)) return;
                    const icon = L.divIcon({
                        className: '',
                        html: `<div style="background:#16a34a;color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font:700 13px sans-serif;box-shadow:0 1px 3px rgba(0,0,0,.4)">S</div>`,
                        iconSize: [24, 24],
                        iconAnchor: [12, 12],
                    });
                    this._schoolMarker = L.marker([lat, lng], { icon })
                        .bindTooltip('School drop-off', { direction: 'top' })
                        .addTo(this.map);
                },

                setSchool(lat, lng) {
                    this.schoolLat = +lat.toFixed(6);
                    this.schoolLng = +lng.toFixed(6);
                    this.$wire.set('schoolLat', this.schoolLat);
                    this.$wire.set('schoolLng', this.schoolLng);
                    this.drawSchool();
                },
            };
        };
    })();
    </script>
</x-filament-panels::page>
