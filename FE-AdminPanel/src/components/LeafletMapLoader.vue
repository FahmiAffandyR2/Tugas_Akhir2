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
    animate: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      map: null,
      L: null,
      markerLayer: null,
      polylineLayer: null,
      markerRefs: [],
      markerMap: {},
      animationFrames: {},
    };
  },
  watch: {
    markers: {
      deep: true,
      handler(newVal, oldVal) {
        this.updateMarkersIncremental(newVal);
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
      this.updateMarkersIncremental(this.markers);
      this.renderPolylines();
      this.$nextTick(() => {
        this.map.invalidateSize();
        this.fitBounds();
      });
    });
  },
  beforeDestroy() {
    Object.keys(this.animationFrames).forEach(id => {
      cancelAnimationFrame(this.animationFrames[id]);
    });
    this.animationFrames = {};
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

    updateMarkersIncremental(newMarkers) {
      if (!this.map || !this.markerLayer) return;

      const newIds = new Set(newMarkers.map(m => m.place_id));
      const existingIds = new Set(Object.keys(this.markerMap));

      existingIds.forEach(id => {
        if (!newIds.has(id)) {
          if (this.markerMap[id]) {
            this.markerLayer.removeLayer(this.markerMap[id]);
            delete this.markerMap[id];
          }
          if (this.animationFrames[id]) {
            cancelAnimationFrame(this.animationFrames[id]);
            delete this.animationFrames[id];
          }
        }
      });

      newMarkers.forEach(marker => {
        if (!marker.position) return;

        const existing = this.markerMap[marker.place_id];

        if (existing) {
          const currentLatLng = existing.getLatLng();
          const newLatLng = [marker.position.lat, marker.position.lng];
          const distance = currentLatLng.distanceTo(this.L.latLng(newLatLng));

          if (distance < 0.5) return;

          if (this.animate && distance > 1) {
            this.animateMarker(existing, currentLatLng, newLatLng, marker);
          } else {
            existing.setLatLng(newLatLng);
            if (marker.infoText) {
              existing.setPopupContent(marker.infoText);
            }
          }
        } else {
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
          this.markerMap[marker.place_id] = leafletMarker;
        }
      });

      this.markerRefs = Object.entries(this.markerMap).map(([id, marker]) => ({
        id,
        marker,
      }));

      this.openSelectedMarker();
    },

    animateMarker(leafletMarker, from, to, markerData) {
      const markerId = markerData.place_id;
      if (this.animationFrames[markerId]) {
        cancelAnimationFrame(this.animationFrames[markerId]);
      }

      const duration = 800;
      const startTime = performance.now();
      const fromLat = from.lat;
      const fromLng = from.lng;
      const toLat = to[0];
      const toLng = to[1];

      const easeInOutCubic = (t) => {
        return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
      };

      const step = (timestamp) => {
        const elapsed = timestamp - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easedProgress = easeInOutCubic(progress);

        const currentLat = fromLat + (toLat - fromLat) * easedProgress;
        const currentLng = fromLng + (toLng - fromLng) * easedProgress;

        leafletMarker.setLatLng([currentLat, currentLng]);

        if (markerData.heading !== undefined) {
          const angle = this.calculateHeading(fromLat, fromLng, toLat, toLng);
          const iconElement = leafletMarker.getElement();
          if (iconElement) {
            iconElement.style.transform += ` rotate(${angle}deg)`;
          }
        }

        if (progress < 1) {
          this.animationFrames[markerId] = requestAnimationFrame(step);
        } else {
          if (markerData.infoText) {
            leafletMarker.setPopupContent(markerData.infoText);
          }
          delete this.animationFrames[markerId];
        }
      };

      this.animationFrames[markerId] = requestAnimationFrame(step);
    },

    calculateHeading(fromLat, fromLng, toLat, toLng) {
      const dLng = (toLng - fromLng) * Math.PI / 180;
      const lat1 = fromLat * Math.PI / 180;
      const lat2 = toLat * Math.PI / 180;
      const y = Math.sin(dLng) * Math.cos(lat2);
      const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(dLng);
      let heading = Math.atan2(y, x) * 180 / Math.PI;
      heading = (heading + 360) % 360;
      return heading;
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
            weight: polyline.weight || 5,
            opacity: polyline.opacity || 1,
            dashArray: polyline.dashArray || null,
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
  position: relative;
  z-index: 0;
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
