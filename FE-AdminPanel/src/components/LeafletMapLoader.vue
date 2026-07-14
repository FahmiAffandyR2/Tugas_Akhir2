<template>
  <div>
    <div ref="map" class="leaflet-map"></div>
    <v-btn v-if="markers.length > 1" depressed color="success" @click="fitBounds" class="ma-1 float-right my-2">
      Fit
      <v-icon right dark>
        mdi-fit-to-screen-outline
      </v-icon>
    </v-btn>
  </div>
</template>

<script>
const leafletCssUrl = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
const leafletScriptUrl = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";

function loadLeaflet() {
  if (window.L) return Promise.resolve(window.L);
  if (window.__leafletLoader) return window.__leafletLoader;

  window.__leafletLoader = new Promise((resolve, reject) => {
    if (!document.querySelector(`link[href="${leafletCssUrl}"]`)) {
      const link = document.createElement("link");
      link.rel = "stylesheet";
      link.href = leafletCssUrl;
      link.crossOrigin = "";
      document.head.appendChild(link);
    }

    const script = document.createElement("script");
    script.src = leafletScriptUrl;
    script.crossOrigin = "";
    script.onload = () => resolve(window.L);
    script.onerror = reject;
    document.head.appendChild(script);
  });

  return window.__leafletLoader;
}

export default {
  props: {
    center: Object,
    zoom: Number,
    markers: {
      type: Array,
      default: () => [],
    },
    selected: String,
    enabled: Boolean,
    polylines: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      map: null,
      L: null,
      markerLayer: null,
      polylineLayer: null,
      markerRefs: [],
    };
  },
  watch: {
    markers: {
      deep: true,
      handler() {
        this.renderMarkers();
      },
    },
    polylines: {
      deep: true,
      handler() {
        this.renderPolylines();
      },
    },
    selected() {
      this.openSelectedMarker();
    },
    center: {
      deep: true,
      handler(value) {
        if (!this.map || !value) return;
        this.map.setView([value.lat, value.lng], this.map.getZoom());
      },
    },
  },
  mounted() {
    loadLeaflet().then((L) => {
      this.L = L;
      this.initMap();
      this.renderMarkers();
      this.renderPolylines();
      this.$nextTick(() => {
        this.map.invalidateSize();
        this.fitBounds();
      });
    });
  },
  beforeDestroy() {
    if (this.map) {
      this.map.remove();
    }
  },
  methods: {
    initMap() {
      if (this.map) return;

      const initialCenter = this.center || { lat: 0, lng: 0 };
      this.map = this.L.map(this.$refs.map).setView(
        [initialCenter.lat, initialCenter.lng],
        this.zoom || 12
      );

      this.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
      }).addTo(this.map);

      this.markerLayer = this.L.layerGroup().addTo(this.map);
      this.polylineLayer = this.L.layerGroup().addTo(this.map);

      this.map.on("click", (event) => {
        if (!this.enabled) return;
        this.$emit("map-click", {
          place_id: `${event.latlng.lat},${event.latlng.lng}`,
          formatted_address: `${event.latlng.lat.toFixed(6)}, ${event.latlng.lng.toFixed(6)}`,
          geometry: {
            location: {
              lat: () => event.latlng.lat,
              lng: () => event.latlng.lng,
            },
          },
        });
      });
    },
    renderMarkers() {
      if (!this.map || !this.markerLayer) return;

      this.markerLayer.clearLayers();
      this.markerRefs = [];

      this.markers.forEach((marker) => {
        if (!marker.position) return;

        const options = {};
        if (marker.icon) {
          options.icon = this.L.icon({
            iconUrl: marker.icon,
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            popupAnchor: [0, -32],
          });
        }

        const leafletMarker = this.L.marker(
          [marker.position.lat, marker.position.lng],
          options
        );

        if (marker.infoText) {
          leafletMarker.bindPopup(marker.infoText);
        }

        leafletMarker.addTo(this.markerLayer);
        this.markerRefs.push({ id: marker.place_id, marker: leafletMarker });
      });

      this.openSelectedMarker();
    },
    renderPolylines() {
      if (!this.map || !this.polylineLayer) return;

      this.polylineLayer.clearLayers();
      this.polylines.forEach((polyline) => {
        if (!polyline.data || polyline.data.length === 0) return;
        this.L.polyline(
          polyline.data.map((point) => [point.lat, point.lng]),
          {
            color: polyline.strokeColor || "#1976d2",
            weight: 5,
          }
        ).addTo(this.polylineLayer);
      });
    },
    fitBounds() {
      if (!this.map || this.markers.length === 0) return;

      const points = this.markers
        .filter((marker) => marker.position)
        .map((marker) => [marker.position.lat, marker.position.lng]);

      if (points.length === 1) {
        this.map.setView(points[0], this.zoom || 12);
        return;
      }

      this.map.fitBounds(points, { padding: [32, 32] });
    },
    openSelectedMarker() {
      if (!this.selected) return;

      const match = this.markerRefs.find((entry) => entry.id === this.selected);
      if (match) {
        match.marker.openPopup();
        this.map.setView(match.marker.getLatLng(), this.map.getZoom());
      }
    },
  },
};
</script>

<style scoped>
.leaflet-map {
  width: 100%;
  height: 400px;
}
</style>

<style>
.leaflet-container {
  overflow: hidden;
  background: #ddd;
  outline: 0;
  font: 12px/1.5 "Helvetica Neue", Arial, Helvetica, sans-serif;
}

.leaflet-pane,
.leaflet-tile,
.leaflet-marker-icon,
.leaflet-marker-shadow,
.leaflet-tile-container,
.leaflet-pane > svg,
.leaflet-pane > canvas,
.leaflet-zoom-box,
.leaflet-image-layer,
.leaflet-layer {
  position: absolute;
  left: 0;
  top: 0;
}

.leaflet-container img.leaflet-tile {
  max-width: none !important;
  max-height: none !important;
}

.leaflet-tile {
  user-select: none;
  visibility: hidden;
}

.leaflet-tile-loaded {
  visibility: inherit;
}

.leaflet-marker-icon,
.leaflet-marker-shadow {
  display: block;
}

.leaflet-map-pane,
.leaflet-tile-pane,
.leaflet-overlay-pane,
.leaflet-shadow-pane,
.leaflet-marker-pane,
.leaflet-tooltip-pane,
.leaflet-popup-pane {
  position: absolute;
  left: 0;
  top: 0;
}

.leaflet-tile-pane {
  z-index: 200;
}

.leaflet-overlay-pane {
  z-index: 400;
}

.leaflet-shadow-pane {
  z-index: 500;
}

.leaflet-marker-pane {
  z-index: 600;
}

.leaflet-tooltip-pane {
  z-index: 650;
}

.leaflet-popup-pane {
  z-index: 700;
}

.leaflet-control {
  position: relative;
  z-index: 800;
  pointer-events: auto;
}

.leaflet-top,
.leaflet-bottom {
  position: absolute;
  z-index: 1000;
  pointer-events: none;
}

.leaflet-top {
  top: 0;
}

.leaflet-right {
  right: 0;
}

.leaflet-bottom {
  bottom: 0;
}

.leaflet-left {
  left: 0;
}

.leaflet-control-container .leaflet-control {
  margin: 10px;
}

.leaflet-control-zoom a {
  display: block;
  width: 26px;
  height: 26px;
  line-height: 26px;
  text-align: center;
  text-decoration: none;
  background: #fff;
  color: #000;
  border-bottom: 1px solid #ccc;
}

.leaflet-control-zoom {
  border: 2px solid rgba(0, 0, 0, 0.2);
  border-radius: 4px;
  overflow: hidden;
}

.leaflet-popup {
  position: absolute;
  text-align: center;
  margin-bottom: 20px;
}

.leaflet-popup-content-wrapper {
  padding: 1px;
  text-align: left;
  border-radius: 4px;
  background: #fff;
  box-shadow: 0 3px 14px rgba(0, 0, 0, 0.4);
}

.leaflet-popup-content {
  margin: 13px 19px;
  line-height: 1.4;
}
</style>
