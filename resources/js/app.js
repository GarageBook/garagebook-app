if (!localStorage.getItem('theme')) {
    localStorage.setItem('theme', 'light')
}

import './bootstrap';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

window.L = L;

window.tripRouteMap = (config) => ({
    map: null,
    initialized: false,
    init() {
        if (this.initialized || ! window.L || ! config?.mapId || ! config?.geojson) {
            return;
        }

        const container = document.getElementById(config.mapId);

        if (! container) {
            return;
        }

        const coordinates = config.geojson?.geometry?.coordinates;

        if (! Array.isArray(coordinates) || ! coordinates.length) {
            return;
        }

        this.initialized = true;

        this.map = window.L.map(container, {
            scrollWheelZoom: false,
        });

        window.L.tileLayer(config.tileUrl || 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: config.attribution,
            maxZoom: 19,
        }).addTo(this.map);

        const routeLayer = window.L.geoJSON(config.geojson, {
            style: {
                color: '#111827',
                weight: 4,
                opacity: 0.88,
            },
        }).addTo(this.map);

        window.requestAnimationFrame(() => {
            this.map.invalidateSize();

            const fitBounds = config.bounds;
            const south = Number(fitBounds?.south);
            const west = Number(fitBounds?.west);
            const north = Number(fitBounds?.north);
            const east = Number(fitBounds?.east);

            if ([south, west, north, east].every(Number.isFinite)) {
                this.map.fitBounds([
                    [south, west],
                    [north, east],
                ], {
                    padding: [24, 24],
                });

                return;
            }

            if (routeLayer.getLayers().length) {
                this.map.fitBounds(routeLayer.getBounds(), {
                    padding: [24, 24],
                });
            }
        });
    },
});
