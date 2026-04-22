@php
    $vehicles = $this->eligibleVehicles;
    $vehiclesWithDepot = $vehicles->filter(fn ($v) => $v->hasDepot())->count();
    $vehicleMarkers = $this->getVehicleMarkers();
    $studentMarkers = $this->getStudentMarkers();
    $centers = $this->attendanceCenters;
    $poolCount = $this->studentPool->count();
    $availableRoutes = $this->availableRoutes;
    $canSaveRoutes = auth()->user()?->can('update_route') ?? false;
@endphp

<x-filament-panels::page>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        .ro-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            height: calc(100vh - 14rem);
            min-height: 560px;
        }
        @media (min-width: 1024px) {
            .ro-grid { grid-template-columns: 22rem 1fr; }
        }
        .ro-sidebar {
            overflow-y: auto;
            padding-right: 0.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .ro-map-shell {
            border-radius: 0.5rem;
            overflow: hidden;
            border: 1px solid rgb(229 231 235 / 0.6);
            background: #f3f4f6;
            min-height: 560px;
            /* Isolate Leaflet's internal z-indexes (panes up to 700,
               controls up to 800) so Filament modals/popovers stay on top. */
            position: relative;
            z-index: 0;
            isolation: isolate;
        }
        .dark .ro-map-shell { border-color: rgb(255 255 255 / 0.1); }
        .ro-vlist { max-height: 20rem; overflow-y: auto; margin: 0; padding: 0; list-style: none; }
        .ro-vlist li { display: flex; gap: 0.5rem; padding: 0.25rem 0; align-items: flex-start; font-size: 0.75rem; }
        .ro-muted { font-size: 0.6875rem; color: rgb(107 114 128); }
        .dark .ro-muted { color: rgb(156 163 175); }
        .ro-err { color: rgb(220 38 38); }
        .dark .ro-err { color: rgb(248 113 113); }
        .ro-ok { color: rgb(22 163 74); }
        .dark .ro-ok { color: rgb(74 222 128); }
        .ro-input { width: 100%; font-size: 0.8125rem; padding: 0.375rem 0.5rem; border-radius: 0.375rem;
                    border: 1px solid rgb(209 213 219); background: #fff; color: inherit; }
        .dark .ro-input { background: rgb(255 255 255 / 0.05); border-color: rgb(255 255 255 / 0.1); color: #e5e7eb; }
        .ro-solve-btn { width: 100%; padding: 0.5rem 0.75rem; font-size: 0.875rem; font-weight: 500;
                        border-radius: 0.5rem; background: rgb(37 99 235); color: white; border: none; cursor: pointer; }
        .ro-solve-btn:hover { background: rgb(29 78 216); }
        .ro-solve-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    </style>

    @if ($result)
        @php
            $totalMi = array_sum(array_column($result['routes'], 'distance_miles'));
            $totalMin = array_sum(array_column($result['routes'], 'duration_minutes'));
            $totalStudents = array_sum(array_column($result['routes'], 'students'));
        @endphp
        <x-filament::section>
            <x-slot name="heading">Result</x-slot>
            <x-slot name="description">
                Solved in {{ $result['solve_time_ms'] }} ms ·
                {{ count($result['routes']) }} route{{ count($result['routes']) === 1 ? '' : 's' }} ·
                {{ $totalStudents }} students ·
                {{ number_format($totalMi, 2) }} mi ·
                {{ $totalMin }} min total
            </x-slot>

            @php
                $palette = ['#ef4444','#3b82f6','#10b981','#f59e0b','#a855f7','#ec4899','#06b6d4','#f97316','#65a30d','#6366f1'];
            @endphp
            <div style="overflow-x: auto;">
                <table style="width:100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="text-align: left;">
                            <th style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.3);"></th>
                            <th style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.3);">Unit</th>
                            <th style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.3);">Seats</th>
                            <th style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.3);">Students</th>
                            <th style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.3);">Distance</th>
                            <th style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.3);">Duration</th>
                            <th style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.3);">Order</th>
                            @if ($canSaveRoutes)
                                <th style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.3);">Save</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['routes'] as $idx => $r)
                            @php $color = $palette[$idx % count($palette)]; @endphp
                            <tr>
                                <td style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.15); width: 1.25rem;">
                                    <span style="display:inline-block; width:14px; height:14px; border-radius:3px; background:{{ $color }};"></span>
                                </td>
                                <td style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.15); font-weight: 600;">{{ $r['unit'] }}</td>
                                <td style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.15);">{{ $r['capacity'] }}</td>
                                <td style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.15);">
                                    <strong>{{ $r['students'] }}</strong>
                                    @if ($r['capacity'] > 0)
                                        <span class="ro-muted">/ {{ $r['capacity'] }}</span>
                                    @endif
                                </td>
                                <td style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.15);">{{ number_format($r['distance_miles'], 2) }} mi</td>
                                <td style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.15);">{{ $r['duration_minutes'] }} min</td>
                                <td style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.15); font-size: 0.75rem;" class="ro-muted">
                                    @php
                                        $names = collect($r['stops'])
                                            ->where('type', 'job')
                                            ->pluck('student_name')
                                            ->filter()
                                            ->values()
                                            ->all();
                                    @endphp
                                    {{ $names ? implode(' → ', array_slice($names, 0, 3)) . (count($names) > 3 ? ' … (' . (count($names) - 3) . ' more)' : '') : '—' }}
                                </td>
                                @if ($canSaveRoutes)
                                    <td style="padding: 0.5rem; border-bottom: 1px solid rgb(229 231 235 / 0.15);">
                                        @if (isset($savedPathBadges[$idx]))
                                            @php $b = $savedPathBadges[$idx]; @endphp
                                            <span class="ro-ok" style="font-size: 0.75rem;">
                                                ✓ saved to <strong>{{ $b['route_code'] }}</strong>
                                                as <em>{{ $b['version_name'] }}</em>
                                            </span>
                                            <a href="{{ $b['planner_url'] }}" style="margin-left: 0.25rem; font-size: 0.75rem; color: rgb(37 99 235);">open planner</a>
                                        @else
                                            <div style="display:flex; gap: 0.25rem; align-items: center;">
                                                <select wire:model="saveRouteTargets.{{ $idx }}"
                                                        class="ro-input"
                                                        style="font-size: 0.75rem; padding: 0.25rem 0.375rem; width: auto; min-width: 10rem;">
                                                    <option value="">Pick Route…</option>
                                                    @foreach ($availableRoutes as $route)
                                                        <option value="{{ $route->id }}">{{ $route->code }} — {{ $route->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="button"
                                                        wire:click="saveToRoute({{ $idx }})"
                                                        style="font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; background: rgb(22 163 74); color: white; border: none; cursor: pointer;">
                                                    Save
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (! empty($result['unassigned']))
                <div style="margin-top: 0.75rem; padding: 0.5rem; border-left: 3px solid rgb(220 38 38); background: rgb(254 226 226 / 0.3); font-size: 0.75rem;">
                    <strong class="ro-err">{{ count($result['unassigned']) }} unassigned student{{ count($result['unassigned']) === 1 ? '' : 's' }}</strong>
                    — capacity exceeded or coord outside the routing graph.
                    <ul style="margin-top: 0.25rem; padding-left: 1.25rem; list-style: disc;">
                        @foreach (array_slice($result['unassigned'], 0, 10) as $u)
                            <li>{{ $u['student_name'] }} @if ($u['reason']) <span class="ro-muted">— {{ $u['reason'] }}</span> @endif</li>
                        @endforeach
                        @if (count($result['unassigned']) > 10)
                            <li>… and {{ count($result['unassigned']) - 10 }} more</li>
                        @endif
                    </ul>
                </div>
            @endif

            <div style="margin-top: 0.5rem;" class="ro-muted">
                Saves land as <em>inactive</em> versions. Open the planner, review, then Activate to use for reporting.
            </div>
        </x-filament::section>
    @endif

    <div
        x-data="routeOptimizer({
            vehicleMarkers: @js($vehicleMarkers),
            studentMarkers: @js($studentMarkers),
            initialSchoolLat: @js($schoolLat),
            initialSchoolLng: @js($schoolLng),
            initialResult: @js($result),
        })"
        x-init="init()"
        class="ro-grid"
    >
        {{-- Sidebar --}}
        <div class="ro-sidebar">
            <x-filament::section>
                <x-slot name="heading">Vehicles</x-slot>
                <x-slot name="description">Only vehicles with a depot set are selectable. Open a vehicle → Default depot section to add.</x-slot>

                @if ($vehiclesWithDepot === 0)
                    <div style="padding: 0.5rem; border-left: 3px solid rgb(245 158 11); background: rgb(254 243 199 / 0.5); font-size: 0.75rem;">
                        No vehicles have a depot set yet — go to any Vehicle and click the map in the Default depot section.
                    </div>
                @endif

                <ul class="ro-vlist">
                    @foreach ($vehicles as $v)
                        <li>
                            <input type="checkbox"
                                   wire:model.live="selectedVehicleIds"
                                   value="{{ $v->id }}"
                                   @disabled(! $v->hasDepot())
                                   style="margin-top: 0.125rem;" />
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 500;">{{ $v->unit_number }}
                                    <span class="ro-muted">· {{ $v->capacity_passengers ?? '?' }} seats</span>
                                </div>
                                <div class="ro-muted">
                                    @if ($v->hasDepot())
                                        <span class="ro-ok">depot set</span>
                                    @else
                                        <span class="ro-err">no depot</span>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="ro-muted" style="margin-top: 0.5rem;">
                    Selected: {{ count($selectedVehicleIds) }} of {{ $vehiclesWithDepot }} depot-ready
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Student pool</x-slot>
                <div style="margin-bottom: 0.5rem;">
                    <label class="ro-muted" style="display:block; margin-bottom: 0.25rem;">Attendance center</label>
                    <select wire:model.live="attendanceCenter" class="ro-input">
                        <option value="">— All active geocoded students —</option>
                        @foreach ($centers as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="font-size: 0.875rem;">
                    Pool: <strong>{{ $poolCount }}</strong> student{{ $poolCount === 1 ? '' : 's' }}
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">School drop-off</x-slot>
                <x-slot name="description">Click the map or type coordinates.</x-slot>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <input type="number" step="0.000001" placeholder="Lat"
                           wire:model.live.debounce.500ms="schoolLat"
                           class="ro-input" />
                    <input type="number" step="0.000001" placeholder="Lng"
                           wire:model.live.debounce.500ms="schoolLng"
                           class="ro-input" />
                </div>
            </x-filament::section>

            <button type="button" wire:click="solve" class="ro-solve-btn">Solve</button>
        </div>

        {{-- Map --}}
        <div wire:ignore class="ro-map-shell">
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
                initialResult: config.initialResult || null,
                map: null,
                _vehicleLayer: null,
                _studentLayer: null,
                _schoolMarker: null,
                _routeLayer: null,
                _unitMarkers: [],       // {marker, unit}
                _stopMarkers: [],       // {marker, color, number}

                _palette: [
                    '#ef4444', '#3b82f6', '#10b981', '#f59e0b',
                    '#a855f7', '#ec4899', '#06b6d4', '#f97316',
                    '#65a30d', '#6366f1',
                ],

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

                    // Rescale divIcon markers when the user zooms — by default
                    // they stay at a fixed pixel size regardless of zoom.
                    this.map.on('zoomend', () => this.restyleMarkers());

                    this.$watch('schoolLat', () => this.drawSchool());
                    this.$watch('schoolLng', () => this.drawSchool());

                    // Draw any pre-existing result (e.g. after Livewire
                    // re-renders the page from a filter change post-solve).
                    if (this.initialResult) this.drawResult(this.initialResult);

                    // New solves arrive via a Livewire-dispatched event.
                    this.$wire.on('route-optimizer-result', (payload) => {
                        const result = payload?.result ?? payload?.[0]?.result ?? null;
                        this.drawResult(result);
                    });
                },

                decodePolyline(str, precision) {
                    precision = precision || 5;
                    let index = 0, lat = 0, lng = 0, byte;
                    const factor = Math.pow(10, precision);
                    const coords = [];
                    while (index < str.length) {
                        let shift = 0, result = 0;
                        do {
                            byte = str.charCodeAt(index++) - 63;
                            result |= (byte & 0x1f) << shift;
                            shift += 5;
                        } while (byte >= 0x20);
                        lat += (result & 1) ? ~(result >> 1) : (result >> 1);

                        shift = 0; result = 0;
                        do {
                            byte = str.charCodeAt(index++) - 63;
                            result |= (byte & 0x1f) << shift;
                            shift += 5;
                        } while (byte >= 0x20);
                        lng += (result & 1) ? ~(result >> 1) : (result >> 1);

                        coords.push([lat / factor, lng / factor]);
                    }
                    return coords;
                },

                drawResult(result) {
                    if (this._routeLayer) {
                        this.map.removeLayer(this._routeLayer);
                        this._routeLayer = null;
                    }
                    this._stopMarkers = [];
                    if (!result || !Array.isArray(result.routes) || result.routes.length === 0) return;

                    this._routeLayer = L.layerGroup().addTo(this.map);
                    const bounds = [];

                    result.routes.forEach((route, idx) => {
                        const color = this._palette[idx % this._palette.length];

                        if (typeof route.geometry === 'string' && route.geometry.length > 0) {
                            try {
                                const coords = this.decodePolyline(route.geometry);
                                if (coords.length > 0) {
                                    L.polyline(coords, { color, weight: 4, opacity: 0.85 }).addTo(this._routeLayer);
                                    coords.forEach(c => bounds.push(c));
                                }
                            } catch (e) {
                                console.warn('failed to decode polyline for route', idx, e);
                            }
                        }

                        let n = 1;
                        (route.stops || []).forEach(stop => {
                            if (stop.type !== 'job') return;
                            const lat = parseFloat(stop.lat);
                            const lng = parseFloat(stop.lng);
                            if (!isFinite(lat) || !isFinite(lng)) return;

                            const marker = L.marker([lat, lng], { icon: this._stopIcon(color, n) })
                                .bindTooltip(`#${n} Unit ${route.unit} — ${stop.student_name || ''}`, { direction: 'top' })
                                .addTo(this._routeLayer);
                            this._stopMarkers.push({ marker, color, number: n });
                            bounds.push([lat, lng]);
                            n++;
                        });
                    });

                    // Dim the raw student pool dots so route markers pop.
                    if (this._studentLayer) {
                        this._studentLayer.eachLayer(m => {
                            m.setStyle && m.setStyle({ fillOpacity: 0.2, opacity: 0.3 });
                        });
                    }

                    if (bounds.length > 0) {
                        this.map.fitBounds(L.latLngBounds(bounds), { padding: [30, 30], maxZoom: 14 });
                    }
                },

                _scale() {
                    if (!this.map) return 1;
                    // Base zoom 12 → scale 1.0. Grow 15 % per extra zoom level,
                    // clamped so we don't make markers tiny or absurd.
                    const z = this.map.getZoom();
                    return Math.max(0.7, Math.min(2.5, 1 + (z - 12) * 0.15));
                },

                _unitIcon(unit) {
                    const s = this._scale();
                    const padV = Math.round(3 * s);
                    const padH = Math.round(6 * s);
                    const font = Math.max(10, Math.round(11 * s));
                    const radius = Math.round(6 * s);
                    return L.divIcon({
                        className: '',
                        html: `<div style="background:#2563eb;color:#fff;border-radius:${radius}px;padding:${padV}px ${padH}px;font:600 ${font}px sans-serif;box-shadow:0 1px 2px rgba(0,0,0,.3);white-space:nowrap">${unit}</div>`,
                        iconSize: null,
                        iconAnchor: [Math.round(18 * s), Math.round(10 * s)],
                    });
                },

                _schoolIcon() {
                    const s = this._scale();
                    const size = Math.round(24 * s);
                    const font = Math.max(11, Math.round(13 * s));
                    return L.divIcon({
                        className: '',
                        html: `<div style="background:#16a34a;color:#fff;border-radius:50%;width:${size}px;height:${size}px;display:flex;align-items:center;justify-content:center;font:700 ${font}px sans-serif;box-shadow:0 1px 3px rgba(0,0,0,.4)">S</div>`,
                        iconSize: [size, size],
                        iconAnchor: [size / 2, size / 2],
                    });
                },

                _stopIcon(color, number) {
                    const s = this._scale();
                    const size = Math.round(22 * s);
                    const font = Math.max(9, Math.round(11 * s));
                    return L.divIcon({
                        className: '',
                        html: `<div style="background:${color};color:#fff;border-radius:50%;width:${size}px;height:${size}px;display:flex;align-items:center;justify-content:center;font:700 ${font}px sans-serif;box-shadow:0 1px 2px rgba(0,0,0,.4);border:2px solid white">${number}</div>`,
                        iconSize: [size, size],
                        iconAnchor: [size / 2, size / 2],
                    });
                },

                restyleMarkers() {
                    this._unitMarkers.forEach(({ marker, unit }) => marker.setIcon(this._unitIcon(unit)));
                    this._stopMarkers.forEach(({ marker, color, number }) => marker.setIcon(this._stopIcon(color, number)));
                    if (this._schoolMarker) this._schoolMarker.setIcon(this._schoolIcon());
                },

                drawVehicles() {
                    if (this._vehicleLayer) this.map.removeLayer(this._vehicleLayer);
                    this._vehicleLayer = L.layerGroup().addTo(this.map);
                    this._unitMarkers = [];
                    this.vehicleMarkers.forEach(v => {
                        const marker = L.marker([v.lat, v.lng], { icon: this._unitIcon(v.unit) })
                            .bindTooltip(`Unit ${v.unit} · ${v.capacity} seats`, { direction: 'top' })
                            .addTo(this._vehicleLayer);
                        this._unitMarkers.push({ marker, unit: v.unit });
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
                    this._schoolMarker = L.marker([lat, lng], { icon: this._schoolIcon() })
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
