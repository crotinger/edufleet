@php
    $center = $this->getCenterCoordinates();
    $initialStops = $activePath?->stops ?? [];
    $initialGeometry = $activePath?->geometry ?? null;
    $initialVersion = $activePath?->version_name ?? 'v1';
    $initialDistanceM = $activePath?->distance_meters;
    $initialDurationS = $activePath?->duration_seconds;
@endphp

<x-filament-panels::page>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <div
        x-data="routePlanner({
            initialStops: @js($initialStops),
            initialGeometry: @js($initialGeometry),
            initialVersion: @js($initialVersion),
            initialDistanceM: @js($initialDistanceM),
            initialDurationS: @js($initialDurationS),
            centerLat: {{ $center[0] }},
            centerLng: {{ $center[1] }},
        })"
        x-init="init()"
        class="grid grid-cols-1 lg:grid-cols-[20rem_1fr] gap-4"
        style="height: calc(100vh - 14rem); min-height: 520px;"
    >
        {{-- Sidebar --}}
        <div class="space-y-3 overflow-y-auto pr-1">
            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Route</div>
                <div class="font-semibold text-gray-900 dark:text-white">{{ $record->code }} — {{ $record->name }}</div>
                @if($record->starting_location)
                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">From: {{ $record->starting_location }}</div>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
                <label class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 block mb-1">Version</label>
                <input type="text" x-model="versionName"
                       class="fi-input block w-full rounded-md border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm px-3 py-1.5" />
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Stops (<span x-text="stops.length"></span>)
                    </div>
                    <button type="button" @click="clearStops()" x-show="stops.length > 0"
                            class="text-xs text-danger-600 hover:text-danger-700">clear all</button>
                </div>

                <template x-if="stops.length === 0">
                    <div class="text-xs text-gray-500 dark:text-gray-400 italic">Click on the map to add a stop.</div>
                </template>

                <ul class="space-y-1" x-show="stops.length > 0">
                    <template x-for="(stop, i) in stops" :key="stop.id">
                        <li class="flex items-center gap-2 py-1">
                            <span class="w-5 shrink-0 text-xs text-gray-500 text-right" x-text="(i + 1) + '.'"></span>
                            <input type="text" x-model="stop.name" @input.debounce.300ms="redrawMarkers()"
                                   class="fi-input flex-1 rounded border-gray-300 dark:border-white/10 dark:bg-white/5 text-xs px-2 py-1" />
                            <button type="button" @click="removeStop(i)"
                                    class="text-danger-500 hover:text-danger-700 text-sm shrink-0">&times;</button>
                        </li>
                    </template>
                </ul>
            </div>

            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Totals</div>
                <dl class="text-sm space-y-0.5">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Distance</dt>
                        <dd class="font-medium" x-text="distanceMiles !== null ? distanceMiles + ' mi' : '—'"></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Duration</dt>
                        <dd class="font-medium" x-text="durationMinutes !== null ? durationMinutes + ' min' : '—'"></dd>
                    </div>
                </dl>
                <div class="mt-2 text-xs text-gray-400 italic" x-show="distanceMiles === null">
                    Save to compute distance in the next step (routing).
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <button type="button" @click="save()"
                        :disabled="saving"
                        class="fi-btn fi-btn-color-primary rounded-lg bg-primary-600 text-white px-3 py-2 text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                    <span x-show="!saving">Save</span>
                    <span x-show="saving">Saving…</span>
                </button>
            </div>
        </div>

        {{-- Map --}}
        <div wire:ignore class="rounded-lg overflow-hidden border border-gray-200 dark:border-white/10 bg-gray-100">
            <div x-ref="map" style="width:100%;height:100%"></div>
        </div>
    </div>

    <script>
        window.routePlanner = function (config) {
            return {
                stops: (config.initialStops || []).map((s, i) => ({
                    id: s.id || ('s' + Date.now() + '-' + i),
                    name: s.name || ('Stop ' + (i + 1)),
                    lat: parseFloat(s.lat),
                    lng: parseFloat(s.lng),
                    order: i,
                    student_id: s.student_id ?? null,
                })),
                geometry: config.initialGeometry,
                versionName: config.initialVersion || 'v1',
                distanceMiles: config.initialDistanceM != null
                    ? Math.round((config.initialDistanceM / 1609.344) * 100) / 100
                    : null,
                durationMinutes: config.initialDurationS != null
                    ? Math.round(config.initialDurationS / 60)
                    : null,
                _distanceMeters: config.initialDistanceM ?? null,
                _durationSeconds: config.initialDurationS ?? null,
                map: null,
                markers: [],
                polyline: null,
                saving: false,

                init() {
                    this.map = L.map(this.$refs.map).setView([config.centerLat, config.centerLng], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(this.map);

                    this.map.on('click', (e) => this.addStop(e.latlng.lat, e.latlng.lng));

                    this.redrawMarkers();
                    this.drawGeometry();

                    // Fit if we have existing stops
                    this.fitToStops();
                },

                addStop(lat, lng) {
                    const id = 's' + Date.now() + '-' + Math.random().toString(36).substr(2, 4);
                    this.stops.push({
                        id,
                        name: 'Stop ' + (this.stops.length + 1),
                        lat: lat,
                        lng: lng,
                        order: this.stops.length,
                        student_id: null,
                    });
                    this.redrawMarkers();
                    // Changes invalidate cached geometry
                    this.invalidateRouting();
                },

                removeStop(i) {
                    this.stops.splice(i, 1);
                    this.redrawMarkers();
                    this.invalidateRouting();
                },

                clearStops() {
                    if (this.stops.length === 0) return;
                    if (!confirm('Remove all stops?')) return;
                    this.stops = [];
                    this.invalidateRouting();
                    this.redrawMarkers();
                },

                invalidateRouting() {
                    this.geometry = null;
                    this.distanceMiles = null;
                    this.durationMinutes = null;
                    this._distanceMeters = null;
                    this._durationSeconds = null;
                    this.drawGeometry();
                },

                redrawMarkers() {
                    this.markers.forEach(m => this.map.removeLayer(m));
                    this.markers = [];
                    this.stops.forEach((stop, i) => {
                        const m = L.marker([stop.lat, stop.lng], { draggable: true })
                            .addTo(this.map)
                            .bindTooltip((i + 1) + '. ' + stop.name, { permanent: true, direction: 'right' });
                        m.on('dragend', (e) => {
                            const ll = e.target.getLatLng();
                            stop.lat = ll.lat;
                            stop.lng = ll.lng;
                            this.invalidateRouting();
                        });
                        m.on('click', () => {
                            if (confirm('Remove ' + stop.name + '?')) this.removeStop(i);
                        });
                        this.markers.push(m);
                    });
                },

                drawGeometry() {
                    if (this.polyline) {
                        this.map.removeLayer(this.polyline);
                        this.polyline = null;
                    }
                    if (this.geometry && Array.isArray(this.geometry.coordinates)) {
                        const coords = this.geometry.coordinates.map(c => [c[1], c[0]]);
                        this.polyline = L.polyline(coords, { color: '#3b82f6', weight: 4, opacity: 0.8 }).addTo(this.map);
                    }
                },

                fitToStops() {
                    if (this.stops.length === 0) return;
                    const bounds = L.latLngBounds(this.stops.map(s => [s.lat, s.lng]));
                    this.map.fitBounds(bounds, { maxZoom: 15, padding: [40, 40] });
                },

                save() {
                    this.saving = true;
                    const payload = {
                        version_name: this.versionName,
                        stops: this.stops.map((s, i) => ({
                            id: s.id,
                            name: s.name,
                            lat: s.lat,
                            lng: s.lng,
                            order: i,
                            student_id: s.student_id,
                        })),
                        geometry: this.geometry,
                        distance_meters: this._distanceMeters,
                        duration_seconds: this._durationSeconds,
                        profile: 'driving',
                    };
                    @this.call('save', payload).finally(() => this.saving = false);
                },
            };
        };
    </script>
</x-filament-panels::page>
