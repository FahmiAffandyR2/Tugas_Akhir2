<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-2">Live Tracking</h1>
        <p class="grey--text mb-0">Pantau posisi bus perjalanan Anda secara real-time.</p>
      </div>
      <v-btn text color="primary" :loading="loading" @click="loadData">
        <v-icon left>mdi-refresh</v-icon>Refresh
      </v-btn>
    </div>

    <v-skeleton-loader v-if="loading && !selectedBooking" type="card, card" />

    <v-alert v-else-if="error" type="error" text class="mb-4">
      {{ error }}
      <v-btn text small @click="loadData">Coba lagi</v-btn>
    </v-alert>

    <v-card v-else-if="activeBookings.length === 0 && !loading" flat class="empty-state text-center pa-10">
      <div class="empty-icon mx-auto mb-5">
        <v-icon size="64" color="primary">mdi-map-marker-off</v-icon>
      </div>
      <h2 class="text-h6 font-weight-bold mb-2">Tidak ada perjalanan aktif</h2>
      <p class="grey--text">Perjalanan yang sedang berjalan akan muncul di sini.</p>
      <v-btn color="primary" to="/customer/pemesanan">Lihat Pemesanan</v-btn>
    </v-card>

    <div v-else class="tracking-layout">
      <div class="booking-sidebar">
        <div class="d-flex align-center mb-4">
          <v-icon color="primary" class="mr-2">mdi-bus</v-icon>
          <span class="text-h6 font-weight-bold">Perjalanan Aktif</span>
          <v-chip small color="primary" dark class="ml-2">{{ activeBookings.length }}</v-chip>
        </div>

        <div
          v-for="booking in activeBookings"
          :key="booking.id"
          class="booking-item pa-4 mb-3"
          :class="{ 'selected': selectedBooking && selectedBooking.id === booking.id }"
          @click="selectBooking(booking)"
        >
          <div class="d-flex align-center mb-2">
            <v-avatar :color="isOnline(booking) ? 'success' : 'grey'" size="36" class="mr-3">
              <v-icon dark small>mdi-bus</v-icon>
            </v-avatar>
            <div class="flex-grow-1">
              <div class="font-weight-bold">{{ booking.bus ? booking.bus.license : 'Bus' }}</div>
              <div class="caption grey--text">{{ booking.bus_type }}</div>
            </div>
            <v-chip x-small :color="isOnline(booking) ? 'success' : 'grey'" dark>
              {{ isOnline(booking) ? 'Online' : 'Offline' }}
            </v-chip>
          </div>

          <div class="caption grey--text mb-1">
            <v-icon x-small class="mr-1">mdi-map-marker</v-icon>
            {{ booking.origin }} → {{ booking.destination }}
          </div>

          <div v-if="booking.driver" class="caption grey--text mb-1">
            <v-icon x-small class="mr-1">mdi-account</v-icon>
            Driver: {{ booking.driver.name }}
          </div>

          <div v-if="getEta(booking)" class="eta-badge mt-2 pa-2">
            <v-icon x-small color="primary" class="mr-1">mdi-clock-outline</v-icon>
            <span class="font-weight-bold primary--text">Estimasi tiba: {{ getEta(booking).label }}</span>
          </div>
        </div>
      </div>

      <div class="map-area">
        <LeafletMapLoader
          v-if="mapReady"
          :center="mapCenter"
          :zoom="12"
          :markers="mapMarkers"
          :polylines="mapPolylines"
          :animate="true"
          :enabled="false"
        />
        <div v-if="!hasPosition && selectedBooking" class="map-overlay pa-4">
          <v-icon color="warning" class="mr-2">mdi-crosshairs-question</v-icon>
          Menunggu posisi GPS bus...
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import LeafletMapLoader from '@/components/LeafletMapLoader.vue'

export default {
  components: { LeafletMapLoader },
  data: () => ({
    activeBookings: [],
    selectedBooking: null,
    trackingData: null,
    loading: false,
    error: null,
    pollTimer: null,
    mapReady: false,
  }),
  computed: {
    hasPosition() {
      return this.trackingData && this.trackingData.tracking;
    },
    mapCenter() {
      if (this.hasPosition) {
        return {
          lat: this.trackingData.tracking.lat,
          lng: this.trackingData.tracking.lng,
        };
      }
      if (this.selectedBooking && this.selectedBooking.pickup_lat) {
        return {
          lat: this.selectedBooking.pickup_lat,
          lng: this.selectedBooking.pickup_lng,
        };
      }
      return { lat: -6.2, lng: 106.8 };
    },
    mapMarkers() {
      const markers = [];

      if (this.hasPosition) {
        markers.push({
          place_id: 'bus-position',
          position: {
            lat: this.trackingData.tracking.lat,
            lng: this.trackingData.tracking.lng,
          },
          infoText: this.getBusInfoText(),
          icon: 'https://cdn-icons-png.flaticon.com/32/3471/3471521.png',
        });
      }

      if (this.selectedBooking && this.selectedBooking.pickup_lat) {
        markers.push({
          place_id: 'pickup',
          position: {
            lat: this.selectedBooking.pickup_lat,
            lng: this.selectedBooking.pickup_lng,
          },
          infoText: `<b>Titik Jemput</b><br>${this.selectedBooking.origin}`,
          icon: 'https://cdn-icons-png.flaticon.com/32/190/190411.png',
        });
      }

      if (this.trackingData && this.trackingData.destination) {
        markers.push({
          place_id: 'dropoff',
          position: {
            lat: this.trackingData.destination.lat,
            lng: this.trackingData.destination.lng,
          },
          infoText: `<b>Tujuan</b><br>${this.trackingData.destination.address}`,
          icon: 'https://cdn-icons-png.flaticon.com/32/190/190411.png',
        });
      }

      return markers;
    },
    mapPolylines() {
      const lines = [];
      if (this.hasPosition && this.trackingData && this.trackingData.destination) {
        lines.push({
          data: [
            { lat: this.trackingData.tracking.lat, lng: this.trackingData.tracking.lng },
            { lat: this.trackingData.destination.lat, lng: this.trackingData.destination.lng },
          ],
          strokeColor: '#22c55e',
          weight: 4,
          dashArray: '8 8',
          opacity: 0.9,
        });
      }
      return lines;
    },
  },
  mounted() {
    this.mapReady = true;
    this.loadData();
    this.pollTimer = setInterval(this.refreshTracking, 10000);
  },
  beforeDestroy() {
    if (this.pollTimer) clearInterval(this.pollTimer);
  },
  methods: {
    async loadData() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get('/customer/tracking/active');
        this.activeBookings = response.data.bookings || [];

        if (this.activeBookings.length > 0 && !this.selectedBooking) {
          this.selectBooking(this.activeBookings[0]);
        } else if (this.selectedBooking) {
          const stillActive = this.activeBookings.find(b => b.id === this.selectedBooking.id);
          if (stillActive) {
            this.selectedBooking = stillActive;
            this.fetchTracking(stillActive.id);
          } else {
            this.selectedBooking = null;
            this.trackingData = null;
            if (this.activeBookings.length > 0) {
              this.selectBooking(this.activeBookings[0]);
            }
          }
        }
      } catch (e) {
        this.error = (e.response && e.response.data && e.response.data.message) || 'Gagal memuat data tracking.';
      } finally {
        this.loading = false;
      }
    },
    async refreshTracking() {
      if (!this.selectedBooking) return;
      await this.fetchTracking(this.selectedBooking.id);
    },
    async fetchTracking(bookingId) {
      try {
        const response = await axios.get(`/customer/tracking/${bookingId}`);
        this.trackingData = response.data;
      } catch (e) {
        console.warn('Gagal refresh tracking:', e.message);
      }
    },
    selectBooking(booking) {
      this.selectedBooking = booking;
      this.fetchTracking(booking.id);
    },
    isOnline(booking) {
      if (this.selectedBooking && this.selectedBooking.id === booking.id && this.trackingData && this.trackingData.tracking && this.trackingData.tracking.last_gps_at) {
        const lastGps = new Date(this.trackingData.tracking.last_gps_at);
        const now = new Date();
        return (now - lastGps) < 5 * 60 * 1000;
      }
      if (booking.last_position_lat && booking.last_position_lng) {
        return true;
      }
      return false;
    },
    getEta(booking) {
      if (this.selectedBooking && this.selectedBooking.id === booking.id && this.trackingData && this.trackingData.eta) {
        return this.trackingData.eta;
      }
      return null;
    },
    getBusInfoText() {
      if (!this.trackingData || !this.trackingData.booking) return '<b>Bus</b>';
      const booking = this.trackingData.booking;
      let text = '<b>' + (booking.bus ? booking.bus.license : 'Bus') + '</b><br/>';
      if (booking.driver) text += 'Driver: ' + booking.driver.name + '<br/>';
      if (this.trackingData.tracking && this.trackingData.tracking.speed) {
        text += 'Speed: ' + Math.round(this.trackingData.tracking.speed) + ' km/h<br/>';
      }
      if (this.trackingData.route) {
        text += 'Rute: ' + this.trackingData.route.name + '<br/>';
      }
      if (this.trackingData.eta) {
        text += 'Estimasi tiba: ' + this.trackingData.eta.label;
      }
      return text;
    },
  },
}
</script>

<style scoped>
.tracking-layout {
  display: grid;
  grid-template-columns: 380px 1fr;
  gap: 20px;
  min-height: 500px;
}

.booking-sidebar {
  max-height: 600px;
  overflow-y: auto;
  padding-right: 8px;
}

.booking-item {
  border-radius: 16px;
  border: 1px solid rgba(58, 53, 65, 0.08);
  background: #fff;
  cursor: pointer;
  transition: all 0.2s;
}

.booking-item:hover {
  border-color: rgba(124, 58, 237, 0.3);
}

.booking-item.selected {
  border-color: #7c3aed;
  background: #f9f5ff;
  box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);
}

.eta-badge {
  background: #f0fdf4;
  border-radius: 8px;
  border: 1px solid rgba(34, 197, 94, 0.2);
}

.map-area {
  position: relative;
  border-radius: 18px;
  overflow: hidden;
  min-height: 500px;
  background: #eee;
}

.map-area ::v-deep .leaflet-map {
  height: 500px;
}

.map-overlay {
  position: absolute;
  bottom: 16px;
  left: 16px;
  z-index: 900;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.14);
}

.empty-state {
  border-radius: 18px;
  border: 1px solid #ececf3;
}

.empty-icon {
  width: 120px;
  height: 100px;
  border-radius: 50%;
  background: #f3edff;
  display: flex;
  align-items: center;
  justify-content: center;
}

@media (max-width: 960px) {
  .tracking-layout {
    grid-template-columns: 1fr;
  }

  .booking-sidebar {
    max-height: 200px;
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 8px;
  }

  .booking-item {
    min-width: 280px;
    flex-shrink: 0;
  }

  .map-area ::v-deep .leaflet-map {
    height: 350px;
  }
}

@media (max-width: 600px) {
  .map-area ::v-deep .leaflet-map {
    height: 300px;
  }
}
</style>
