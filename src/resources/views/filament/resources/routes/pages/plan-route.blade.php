@php
    $center = $this->getCenterCoordinates();
    $initialVersions = $this->getVersions();
    $loadedId = $this->currentPathId;
    $loaded = $loadedId
        ? collect($initialVersions)->firstWhere('id', $loadedId)
        : null;
    $activeId = collect($initialVersions)->firstWhere('is_active', true)['id'] ?? null;
    $initialPath = $loadedId ? \App\Models\RoutePath::find($loadedId) : null;
    $initialStops = $initialPath?->stops ?? [];
    $initialGeometry = $initialPath?->geometry;
    $initialVersionName = $initialPath?->version_name ?? 'v1';
    $initialDistanceM = $initialPath?->distance_meters;
    $initialDurationS = $initialPath?->duration_seconds;
    $routeStudents = $this->getRouteStudents();
@endphp

<x-filament-panels::page>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    {{-- Define the planner factory BEFORE the x-data consumer to avoid Alpine
         evaluating routePlanner(...) before the definition script has run. --}}
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
                routeStudents: config.routeStudents || [],
                versions: config.initialVersions || [],
                currentPathId: config.initialCurrentPathId ?? null,
                activePathId: config.initialActivePathId ?? null,
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
                busy: false,
                busyLabel: '',

                init() {
                    if (typeof L === 'undefined') {
                        console.error('[route-planner] Leaflet did not load');
                        return;
                    }
                    this.map = L.map(this.$refs.map).setView([config.centerLat, config.centerLng], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(this.map);

                    this.map.on('click', (e) => this.addStop(e.latlng.lat, e.latlng.lng));

                    this.redrawMarkers();
                    this.drawGeometry();
                    this.fitToStops();

                    // Force a size recalc once layout has settled — Filament's
                    // grid/calc heights aren't guaranteed to be ready on first init.
                    setTimeout(() => this.map?.invalidateSize(), 80);
                    setTimeout(() => this.map?.invalidateSize(), 400);
                },

                /** Apply a full server-side state snapshot (replaces everything). */
                applyState(state) {
                    if (!state) return;
                    this.stops = (state.stops || []).map((s, i) => ({
                        id: s.id || ('s' + Date.now() + '-' + i),
                        name: s.name || ('Stop ' + (i + 1)),
                        lat: parseFloat(s.lat),
                        lng: parseFloat(s.lng),
                        order: i,
                        student_id: s.student_id ?? null,
                    }));
                    this.geometry = state.geometry || null;
                    this._distanceMeters = state.distance_meters ?? null;
                    this._durationSeconds = state.duration_seconds ?? null;
                    this.distanceMiles = this._distanceMeters != null
                        ? Math.round((this._distanceMeters / 1609.344) * 100) / 100
                        : null;
                    this.durationMinutes = this._durationSeconds != null
                        ? Math.round(this._durationSeconds / 60)
                        : null;
                    this.versionName = state.version_name || 'v1';
                    this.currentPathId = state.current_path_id ?? null;
                    this.activePathId = state.active_path_id ?? null;
                    this.versions = state.versions || [];
                    this.redrawMarkers();
                    this.drawGeometry();
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
                    this.invalidateRouting();
                },

                removeStop(i) {
                    this.stops.splice(i, 1);
                    this.redrawMarkers();
                    this.invalidateRouting();
                },

                clearStops() {
                    if (this.stops.length === 0) return;
                    if (!confirm('Remove all stops from this version?')) return;
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
                    if (!this.map) return;
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
                    if (!this.map) return;
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
                    if (!this.map || this.stops.length === 0) return;
                    const bounds = L.latLngBounds(this.stops.map(s => [s.lat, s.lng]));
                    this.map.fitBounds(bounds, { maxZoom: 15, padding: [40, 40] });
                },

                isStudentAdded(student) {
                    return this.stops.some(s => s.student_id === student.id);
                },

                unaddedStudentCount() {
                    const added = new Set(this.stops.map(s => s.student_id).filter(id => id != null));
                    return this.routeStudents.filter(s => !added.has(s.id)).length;
                },

                addStudent(student) {
                    if (this.isStudentAdded(student)) return;
                    this.stops.push({
                        id: 'student-' + student.id + '-' + Date.now(),
                        name: student.name,
                        lat: student.lat,
                        lng: student.lng,
                        order: this.stops.length,
                        student_id: student.id,
                    });
                    this.redrawMarkers();
                    this.invalidateRouting();
                },

                addAllStudents() {
                    const added = new Set(this.stops.map(s => s.student_id).filter(id => id != null));
                    let n = 0;
                    this.routeStudents.forEach(student => {
                        if (added.has(student.id)) return;
                        this.stops.push({
                            id: 'student-' + student.id + '-' + Date.now() + '-' + n,
                            name: student.name,
                            lat: student.lat,
                            lng: student.lng,
                            order: this.stops.length,
                            student_id: student.id,
                        });
                        n++;
                    });
                    if (n > 0) {
                        this.redrawMarkers();
                        this.fitToStops();
                        this.invalidateRouting();
                    }
                },

                _flashCircle: null,

                flashStudent(student) {
                    if (!this.map) return;
                    if (this._flashCircle) this.map.removeLayer(this._flashCircle);
                    this._flashCircle = L.circleMarker([student.lat, student.lng], {
                        radius: 8,
                        color: '#f59e0b',
                        weight: 3,
                        fillColor: '#fbbf24',
                        fillOpacity: 0.5,
                    }).addTo(this.map);
                    clearTimeout(this._flashTimer);
                    this._flashTimer = setTimeout(() => {
                        if (this._flashCircle) {
                            this.map.removeLayer(this._flashCircle);
                            this._flashCircle = null;
                        }
                    }, 1200);
                },

                serializeStops() {
                    return this.stops.map((s, i) => ({
                        id: s.id,
                        name: s.name,
                        lat: s.lat,
                        lng: s.lng,
                        order: i,
                        student_id: s.student_id,
                    }));
                },

                _payload() {
                    return {
                        version_name: this.versionName,
                        stops: this.serializeStops(),
                        geometry: this.geometry,
                        distance_meters: this._distanceMeters,
                        duration_seconds: this._durationSeconds,
                        profile: 'driving',
                    };
                },

                applyRoutingResult(result) {
                    if (!result) return;
                    this.geometry = result.geometry || null;
                    this._distanceMeters = result.distance_meters ?? null;
                    this._durationSeconds = result.duration_seconds ?? null;
                    this.distanceMiles = this._distanceMeters != null
                        ? Math.round((this._distanceMeters / 1609.344) * 100) / 100
                        : null;
                    this.durationMinutes = this._durationSeconds != null
                        ? Math.round(this._durationSeconds / 60)
                        : null;
                    this.drawGeometry();
                },

                async recalculate() {
                    if (this.stops.length < 2) return;
                    this.busy = true;
                    this.busyLabel = 'Recalculating';
                    try {
                        const result = await @this.call('recalculate', this.serializeStops());
                        this.applyRoutingResult(result);
                    } finally {
                        this.busy = false;
                        this.busyLabel = '';
                    }
                },

                async optimize() {
                    if (this.stops.length < 3) return;
                    this.busy = true;
                    this.busyLabel = 'Optimizing';
                    try {
                        const result = await @this.call('optimize', this.serializeStops());
                        if (result && Array.isArray(result.stops) && result.stops.length > 0) {
                            this.stops = result.stops.map(s => ({
                                id: s.id,
                                name: s.name,
                                lat: parseFloat(s.lat),
                                lng: parseFloat(s.lng),
                                order: s.order,
                                student_id: s.student_id ?? null,
                            }));
                            this.redrawMarkers();
                        }
                        this.applyRoutingResult(result);
                    } finally {
                        this.busy = false;
                        this.busyLabel = '';
                    }
                },

                async save() {
                    this.busy = true;
                    this.busyLabel = 'Saving';
                    try {
                        const state = await @this.call('save', this._payload());
                        this.applyState(state);
                    } finally {
                        this.busy = false;
                        this.busyLabel = '';
                    }
                },

                async saveAsNew() {
                    if (this.stops.length === 0) return;
                    this.busy = true;
                    this.busyLabel = 'SavingAsNew';
                    const payload = this._payload();
                    payload.version_name = '';
                    try {
                        const state = await @this.call('saveAsNewVersion', payload);
                        this.applyState(state);
                    } finally {
                        this.busy = false;
                        this.busyLabel = '';
                    }
                },

                async loadVersion(id) {
                    if (id === this.currentPathId) return;
                    if (this.stops.length > 0 && !confirm('Discard current edits and load this version?')) return;
                    this.busy = true;
                    this.busyLabel = 'Loading';
                    try {
                        const state = await @this.call('loadVersion', id);
                        this.applyState(state);
                        this.fitToStops();
                    } finally {
                        this.busy = false;
                        this.busyLabel = '';
                    }
                },

                async activateVersion(id) {
                    this.busy = true;
                    this.busyLabel = 'Activating';
                    try {
                        const state = await @this.call('activateVersion', id);
                        this.applyState(state);
                    } finally {
                        this.busy = false;
                        this.busyLabel = '';
                    }
                },

                async deleteVersionConfirm(v) {
                    if (v.is_active) return;
                    if (!confirm('Delete version "' + v.version_name + '"?')) return;
                    this.busy = true;
                    this.busyLabel = 'Deleting';
                    try {
                        const state = await @this.call('deleteVersion', v.id);
                        this.applyState(state);
                    } finally {
                        this.busy = false;
                        this.busyLabel = '';
                    }
                },

                async renameVersionPrompt(v) {
                    const next = prompt('Rename version:', v.version_name);
                    if (next === null) return;
                    const trimmed = next.trim();
                    if (trimmed === '' || trimmed === v.version_name) return;
                    this.busy = true;
                    this.busyLabel = 'Renaming';
                    try {
                        const state = await @this.call('renameVersion', v.id, trimmed);
                        this.applyState(state);
                    } finally {
                        this.busy = false;
                        this.busyLabel = '';
                    }
                },
            };
        };
    </script>

    <div
        x-data="routePlanner({
            initialStops: @js($initialStops),
            initialGeometry: @js($initialGeometry),
            initialVersion: @js($initialVersionName),
            initialDistanceM: @js($initialDistanceM),
            initialDurationS: @js($initialDurationS),
            initialCurrentPathId: @js($loadedId),
            initialActivePathId: @js($activeId),
            initialVersions: @js($initialVersions),
            routeStudents: @js($routeStudents),
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

            {{-- Editing label + version name --}}
            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
                <label class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 block mb-1">
                    <span x-show="currentPathId">Editing</span>
                    <span x-show="!currentPathId">New version</span>
                </label>
                <input type="text" x-model="versionName"
                       class="fi-input block w-full rounded-md border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm px-3 py-1.5" />
            </div>

            {{-- Versions list --}}
            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                    Versions (<span x-text="versions.length"></span>)
                </div>
                <template x-if="versions.length === 0">
                    <div class="text-xs text-gray-500 dark:text-gray-400 italic">Save to create the first version.</div>
                </template>
                <ul class="space-y-1" x-show="versions.length > 0">
                    <template x-for="v in versions" :key="v.id">
                        <li class="rounded p-1.5 flex items-start gap-1.5"
                            :class="v.id === currentPathId
                                ? 'bg-primary-50 dark:bg-primary-500/10 border border-primary-300 dark:border-primary-500/30'
                                : 'border border-transparent hover:bg-gray-50 dark:hover:bg-white/5'">
                            <button type="button" @click="loadVersion(v.id)"
                                    class="flex-1 text-left min-w-0">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="text-xs font-medium text-gray-900 dark:text-white truncate" x-text="v.version_name"></span>
                                    <span x-show="v.is_active" class="text-[10px] bg-success-500 text-white px-1 rounded leading-tight">active</span>
                                    <span x-show="v.id === currentPathId && !v.is_active" class="text-[10px] bg-primary-500 text-white px-1 rounded leading-tight">editing</span>
                                </div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                    <span x-text="v.stops_count"></span> stop<span x-text="v.stops_count === 1 ? '' : 's'"></span>
                                    <span x-show="v.distance_miles !== null"> · <span x-text="v.distance_miles"></span> mi</span>
                                    <span x-show="v.updated_at"> · <span x-text="v.updated_at"></span></span>
                                </div>
                            </button>
                            <div class="flex flex-col gap-0.5 shrink-0">
                                <button type="button" x-show="!v.is_active" @click="activateVersion(v.id)"
                                        title="Make active"
                                        class="text-success-600 hover:text-success-800 text-xs leading-none px-1">&#10003;</button>
                                <button type="button" @click="renameVersionPrompt(v)"
                                        title="Rename"
                                        class="text-gray-500 hover:text-gray-700 text-xs leading-none px-1">&#9998;</button>
                                <button type="button" x-show="!v.is_active" @click="deleteVersionConfirm(v)"
                                        title="Delete"
                                        class="text-danger-500 hover:text-danger-700 text-sm leading-none px-1">&times;</button>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Stops --}}
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

            {{-- Roster --}}
            <div class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-3">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Roster (<span x-text="routeStudents.length"></span>)
                    </div>
                    <button type="button" @click="addAllStudents()"
                            x-show="routeStudents.length > 0 && unaddedStudentCount() > 0"
                            class="text-xs text-primary-600 hover:text-primary-700">add all</button>
                </div>
                <template x-if="routeStudents.length === 0">
                    <div class="text-xs text-gray-500 dark:text-gray-400 italic">
                        No geocoded students assigned to this route. Attach students on the route's Roster tab and geocode their addresses first.
                    </div>
                </template>
                <ul class="space-y-0.5 max-h-52 overflow-y-auto" x-show="routeStudents.length > 0">
                    <template x-for="student in routeStudents" :key="student.id">
                        <li>
                            <button type="button"
                                    @click="addStudent(student)"
                                    @mouseenter="flashStudent(student)"
                                    :disabled="isStudentAdded(student)"
                                    :class="isStudentAdded(student)
                                        ? 'text-gray-400 cursor-default'
                                        : 'text-gray-700 dark:text-gray-300 hover:bg-primary-50 dark:hover:bg-primary-500/10 hover:text-primary-700'"
                                    class="w-full text-left text-xs py-1 px-2 rounded flex items-center gap-2">
                                <span class="truncate flex-1" x-text="student.name"></span>
                                <span x-show="isStudentAdded(student)" class="text-success-500">&#10003;</span>
                                <span x-show="!isStudentAdded(student)" class="text-gray-400">+</span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Totals --}}
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
                <div class="mt-2 text-xs text-gray-400 italic" x-show="distanceMiles === null && stops.length >= 2">
                    Click Recalculate to trace the route.
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col gap-2">
                <button type="button" @click="recalculate()"
                        :disabled="busy || stops.length < 2"
                        class="rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 px-3 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 disabled:opacity-50">
                    <span x-show="!busy || busyLabel !== 'Recalculating'">Recalculate</span>
                    <span x-show="busy && busyLabel === 'Recalculating'">Recalculating…</span>
                </button>
                <button type="button" @click="optimize()"
                        :disabled="busy || stops.length < 3"
                        class="rounded-lg border border-info-300 dark:border-info-500/30 bg-info-50 dark:bg-info-500/10 text-info-700 dark:text-info-300 px-3 py-2 text-sm font-medium hover:bg-info-100 dark:hover:bg-info-500/20 disabled:opacity-50">
                    <span x-show="!busy || busyLabel !== 'Optimizing'">Optimize order</span>
                    <span x-show="busy && busyLabel === 'Optimizing'">Optimizing…</span>
                </button>
                <button type="button" @click="save()"
                        :disabled="busy"
                        class="rounded-lg bg-primary-600 text-white px-3 py-2 text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                    <span x-show="!busy || busyLabel !== 'Saving'">
                        <span x-show="currentPathId">Save version</span>
                        <span x-show="!currentPathId">Save</span>
                    </span>
                    <span x-show="busy && busyLabel === 'Saving'">Saving…</span>
                </button>
                <button type="button" @click="saveAsNew()"
                        :disabled="busy || stops.length === 0"
                        class="rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 px-3 py-2 text-sm font-medium hover:bg-gray-50 dark:hover:bg-white/5 disabled:opacity-50">
                    <span x-show="!busy || busyLabel !== 'SavingAsNew'">Save as new version</span>
                    <span x-show="busy && busyLabel === 'SavingAsNew'">Saving…</span>
                </button>
            </div>
        </div>

        {{-- Map --}}
        <div wire:ignore class="rounded-lg overflow-hidden border border-gray-200 dark:border-white/10 bg-gray-100"
             style="min-height: 520px; position:relative; z-index:0; isolation:isolate;">
            <div x-ref="map" style="width:100%;height:100%;min-height:520px;"></div>
        </div>
    </div>
</x-filament-panels::page>
