<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div
    x-data="depotPicker()"
    x-init="init()"
    wire:ignore
>
    <div x-ref="map" style="width:100%;height:280px;min-height:280px;position:relative;z-index:0;isolation:isolate;"
         class="rounded border border-gray-200 dark:border-white/10 overflow-hidden"></div>
    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
        Click on the map to set the depot, or drag the marker to reposition.
        <span x-show="hasPoint">
            Current: <span x-text="lat?.toFixed(6)"></span>,
            <span x-text="lng?.toFixed(6)"></span>.
            <button type="button" @click="clear()" class="ml-2 text-danger-600 hover:underline">clear</button>
        </span>
    </div>
</div>

<script>
(function () {
    if (window.depotPicker) return;

    window.depotPicker = function () {
        return {
            map: null,
            marker: null,
            lat: null,
            lng: null,
            hasPoint: false,

            init() {
                if (typeof L === 'undefined') {
                    setTimeout(() => this.init(), 100);
                    return;
                }

                const lat = this.readField('default_depot_lat');
                const lng = this.readField('default_depot_lng');
                this.lat = lat;
                this.lng = lng;
                this.hasPoint = lat !== null && lng !== null;

                const center = this.hasPoint ? [lat, lng] : [39.8283, -98.5795];
                const zoom = this.hasPoint ? 15 : 4;

                this.map = L.map(this.$refs.map).setView(center, zoom);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19,
                }).addTo(this.map);

                if (this.hasPoint) this.placeMarker(lat, lng);

                this.map.on('click', (e) => this.place(e.latlng.lat, e.latlng.lng));

                setTimeout(() => this.map?.invalidateSize(), 80);
                setTimeout(() => this.map?.invalidateSize(), 400);
            },

            readField(name) {
                const v = @this.get('data.' + name);
                const f = parseFloat(v);
                return isFinite(f) ? f : null;
            },

            placeMarker(lat, lng) {
                if (this.marker) this.map.removeLayer(this.marker);
                this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
                this.marker.on('dragend', () => {
                    const ll = this.marker.getLatLng();
                    this.place(ll.lat, ll.lng);
                });
            },

            place(lat, lng) {
                this.lat = lat;
                this.lng = lng;
                this.hasPoint = true;
                this.placeMarker(lat, lng);
                @this.set('data.default_depot_lat', +lat.toFixed(6));
                @this.set('data.default_depot_lng', +lng.toFixed(6));
            },

            clear() {
                if (this.marker) this.map.removeLayer(this.marker);
                this.marker = null;
                this.lat = null;
                this.lng = null;
                this.hasPoint = false;
                @this.set('data.default_depot_lat', null);
                @this.set('data.default_depot_lng', null);
            },
        };
    };
})();
</script>
