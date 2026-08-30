<template>
  <div>
    <!-- Filter Bar -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row align="center">
          <v-col cols="12" sm="3">
            <v-select v-model="filters.type" :items="typeOptions" label="Jenis Booking" outlined dense />
          </v-col>
          <v-col cols="12" sm="3">
            <v-select v-model="filters.status" :items="statusOptions" label="Status" outlined dense />
          </v-col>
          <v-col cols="12" sm="3">
            <v-btn color="primary" @click="fetchLocations" :loading="loading">
              <v-icon left>mdi-magnify</v-icon>Filter
            </v-btn>
            <v-btn text @click="resetFilters" class="ml-2">Reset</v-btn>
          </v-col>
          <v-col cols="12" sm="3" class="text-right">
            <v-chip color="primary" outlined>
              <v-icon left>mdi-map-marker</v-icon>{{ locations.length }} Lokasi
            </v-chip>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Main Content -->
    <v-row>
      <!-- Map -->
      <v-col cols="12" :md="selectedLocation ? 8 : 12">
        <v-card>
          <v-card-title>
            <v-icon left>mdi-map</v-icon>Peta Lokasi Customer
            <v-spacer />
            <v-btn-toggle v-model="mapStyle" mandatory dense>
              <v-btn small value="pins" :class="{ 'primary white--text': mapStyle === 'pins' }">
                <v-icon small>mdi-map-marker</v-icon>
              </v-btn>
              <v-btn small value="clusters" :class="{ 'primary white--text': mapStyle === 'clusters' }">
                <v-icon small>mdi-google-maps</v-icon>
              </v-btn>
            </v-btn-toggle>
          </v-card-title>
          <v-card-text class="pa-0">
            <div style="height: 600px; position: relative;">
              <LeafletMapLoader
                ref="map"
                :center="mapCenter"
                :zoom="10"
                :markers="mapMarkers"
                :selected="selectedLocationId"
                style="height: 100%;"
              />
              <!-- Loading overlay -->
              <div v-if="loading" class="map-loading-overlay">
                <v-progress-circular indeterminate color="primary" size="48" />
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Detail Panel -->
      <v-col v-if="selectedLocation" cols="12" md="4">
        <v-card>
          <v-card-title class="subtitle-1">
            <v-icon left :color="selectedLocation.type === 'charter' ? 'success' : 'primary'">
              {{ selectedLocation.type === 'charter' ? 'mdi-bus-multiple' : 'mdi-ticket' }}
            </v-icon>
            {{ selectedLocation.type === 'charter' ? 'Charter Booking' : 'Regular Booking' }}
            <v-spacer />
            <v-btn icon @click="selectedLocation = null"><v-icon>mdi-close</v-icon></v-btn>
          </v-card-title>
          <v-divider />
          <v-card-text>
            <!-- Customer Info -->
            <div class="mb-4">
              <div class="overline grey--text">Customer</div>
              <div class="font-weight-bold">{{ selectedLocation.customer_name }}</div>
              <div class="body-2 grey--text">{{ selectedLocation.customer_phone }}</div>
            </div>

            <!-- Pickup Location -->
            <div class="mb-4">
              <div class="overline grey--text">Lokasi Jemput</div>
              <div>{{ selectedLocation.pickup_address }}</div>
              <v-chip small outlined class="mt-1">
                <v-icon small left>mdi-crosshairs</v-icon>
                {{ selectedLocation.pickup_lat.toFixed(6) }}, {{ selectedLocation.pickup_lng.toFixed(6) }}
              </v-chip>
            </div>

            <!-- Destination -->
            <div class="mb-4">
              <div class="overline grey--text">Tujuan</div>
              <div>{{ selectedLocation.destination }}</div>
              <div v-if="selectedLocation.dropoff_lat" class="body-2 grey--text">
                <v-icon x-small>mdi-crosshairs</v-icon>
                {{ selectedLocation.dropoff_lat.toFixed(6) }}, {{ selectedLocation.dropoff_lng.toFixed(6) }}
              </div>
            </div>

            <!-- Booking Details -->
            <v-divider class="mb-3" />
            <div v-if="selectedLocation.type === 'charter'">
              <v-simple-table dense>
                <template v-slot:default>
                  <tbody>
                    <tr><td class="grey--text">Kode</td><td class="font-weight-bold">{{ selectedLocation.reference_code }}</td></tr>
                    <tr><td class="grey--text">Bus Type</td><td>{{ selectedLocation.bus_type }}</td></tr>
                    <tr><td class="grey--text">Penumpang</td><td>{{ selectedLocation.passenger_count }} orang</td></tr>
                    <tr><td class="grey--text">Tanggal</td><td>{{ selectedLocation.departure_date }}</td></tr>
                    <tr><td class="grey--text">Waktu</td><td>{{ selectedLocation.departure_time }}</td></tr>
                    <tr><td class="grey--text">Status</td><td><v-chip x-small :color="getStatusColor(selectedLocation.status)" dark>{{ selectedLocation.status }}</v-chip></td></tr>
                    <tr><td class="grey--text">Harga</td><td class="font-weight-bold success--text">{{ formatCurrency(selectedLocation.quoted_price) }}</td></tr>
                    <tr><td class="grey--text">Depo</td><td>{{ selectedLocation.depot_name }}</td></tr>
                    <tr><td class="grey--text">Driver</td><td>{{ selectedLocation.driver_name }}</td></tr>
                  </tbody>
                </template>
              </v-simple-table>
            </div>
            <div v-else>
              <v-simple-table dense>
                <template v-slot:default>
                  <tbody>
                    <tr><td class="grey--text">Rute</td><td>{{ selectedLocation.route_name }}</td></tr>
                    <tr><td class="grey--text">Tanggal</td><td>{{ selectedLocation.planned_date }}</td></tr>
                    <tr><td class="grey--text">Status</td><td><v-chip x-small :color="getRideStatusColor(selectedLocation.ride_status)" dark>{{ getRideStatusText(selectedLocation.ride_status) }}</v-chip></td></tr>
                    <tr><td class="grey--text">Harga</td><td class="font-weight-bold success--text">{{ formatCurrency(selectedLocation.trip_price) }}</td></tr>
                    <tr><td class="grey--text">Ticket</td><td class="font-weight-bold">{{ selectedLocation.ticket_number }}</td></tr>
                  </tbody>
                </template>
              </v-simple-table>
            </div>

            <!-- Nearby Depots -->
            <v-divider class="my-3" />
            <div class="overline grey--text mb-2">Depo Terdekat</div>
            <v-btn small color="primary" outlined @click="findNearbyDepots" :loading="loadingDepots">
              <v-icon left small>mdi-map-marker-distance</v-icon>Cari Depo Terdekat
            </v-btn>
            <div v-if="nearbyDepots.length > 0" class="mt-3">
              <v-card v-for="depot in nearbyDepots" :key="depot.id" outlined class="mb-2 pa-3">
                <div class="d-flex justify-space-between align-center">
                  <div>
                    <div class="font-weight-bold">{{ depot.name }}</div>
                    <div class="body-2 grey--text">{{ depot.city }}</div>
                    <v-chip x-small color="info" dark class="mt-1">{{ depot.distance_km ? depot.distance_km.toFixed(1) + ' km' : '-' }}</v-chip>
                  </div>
                  <v-btn small color="primary" @click="assignDepot(depot)">
                    <v-icon left small>mdi-check</v-icon>Assign
                  </v-btn>
                </div>
              </v-card>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Empty State -->
    <v-card v-if="!loading && locations.length === 0" class="text-center py-12">
      <v-icon size="80" color="grey lighten-1">mdi-map-marker-off</v-icon>
      <h3 class="mt-4 grey--text">Tidak ada lokasi customer ditemukan</h3>
      <p class="grey--text">Customer perlu mengisi koordinat saat melakukan booking</p>
    </v-card>
  </div>
</template>

<script>
import LeafletMapLoader from "../../components/LeafletMapLoader.vue";

export default {
  components: {
    LeafletMapLoader,
  },
  data() {
    return {
      loading: false,
      loadingDepots: false,
      locations: [],
      selectedLocation: null,
      nearbyDepots: [],
      mapStyle: "pins",
      mapCenter: { lat: -6.2088, lng: 106.8456 },
      filters: {
        type: "all",
        status: "all",
      },
      typeOptions: [
        { text: "Semua", value: "all" },
        { text: "Regular", value: "regular" },
        { text: "Charter", value: "charter" },
      ],
      statusOptions: [
        { text: "Semua", value: "all" },
        { text: "Pending", value: "pending" },
        { text: "Aktif", value: "active" },
        { text: "Selesai", value: "completed" },
      ],
    };
  },
  computed: {
    selectedLocationId() {
      return this.selectedLocation ? `${this.selectedLocation.type}-${this.selectedLocation.id}` : null;
    },
    mapMarkers() {
      return this.locations.map((loc) => ({
        position: { lat: loc.pickup_lat, lng: loc.pickup_lng },
        icon: loc.type === "charter"
          ? "https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png"
          : "https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png",
        infoText: this.getPopupContent(loc),
        place_id: `${loc.type}-${loc.id}`,
      }));
    },
  },
  mounted() {
    this.fetchLocations();
  },
  methods: {
    async fetchLocations() {
      this.loading = true;
      try {
        const response = await axios.get("/customer-locations", { params: this.filters });
        this.locations = response.data.locations;
        if (this.locations.length > 0) {
          this.fitMapToMarkers();
        }
      } catch (error) {
        console.error("Failed to fetch locations:", error);
        this.$swal({ icon: "error", title: "Gagal memuat data lokasi" });
      } finally {
        this.loading = false;
      }
    },
    resetFilters() {
      this.filters = { type: "all", status: "all" };
      this.fetchLocations();
    },
    getPopupContent(loc) {
      return `
        <div style="min-width: 200px;">
          <b>${loc.customer_name}</b><br/>
          <small>${loc.type === "charter" ? loc.reference_code : loc.ticket_number}</small><br/>
          <small>${loc.pickup_address}</small><br/>
          <small class="text-muted">${loc.pickup_lat.toFixed(6)}, ${loc.pickup_lng.toFixed(6)}</small>
        </div>
      `;
    },
    fitMapToMarkers() {
      if (this.$refs.map && this.locations.length > 0) {
        const bounds = this.locations.map((l) => [l.pickup_lat, l.pickup_lng]);
        if (bounds.length === 1) {
          this.mapCenter = { lat: bounds[0][0], lng: bounds[0][1] };
        }
      }
    },
    async findNearbyDepots() {
      if (!this.selectedLocation) return;
      this.loadingDepots = true;
      this.nearbyDepots = [];
      try {
        const response = await axios.get("/customer-locations/nearby-depots", {
          params: {
            lat: this.selectedLocation.pickup_lat,
            lng: this.selectedLocation.pickup_lng,
            limit: 5,
          },
        });
        this.nearbyDepots = response.data.depots;
      } catch (error) {
        console.error("Failed to find depots:", error);
        this.$swal({ icon: "error", title: "Gagal mencari depo terdekat" });
      } finally {
        this.loadingDepots = false;
      }
    },
    assignDepot(depot) {
      this.$swal({
        title: "Assign Depo?",
        text: `Assign ${depot.name} ke booking ini?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Ya, Assign",
        cancelButtonText: "Batal",
      }).then((result) => {
        if (result.isConfirmed) {
          // TODO: API call to assign depot
          this.$swal({ icon: "success", title: "Depo berhasil diassign!", timer: 1500, showConfirmButton: false });
        }
      });
    },
    getStatusColor(status) {
      const colors = {
        waiting_quote: "orange",
        confirmed: "blue",
        assigned: "purple",
        in_progress: "green",
        completed: "grey",
        cancelled: "red",
      };
      return colors[status] || "grey";
    },
    getRideStatusColor(status) {
      const colors = { 0: "grey", 1: "green", 2: "orange", 3: "blue", 4: "red" };
      return colors[status] || "grey";
    },
    getRideStatusText(status) {
      const texts = { 0: "Belum", 1: "Naik", 2: "Miss", 3: "Drop Off", 4: "Dibatalkan" };
      return texts[status] || "Unknown";
    },
    formatCurrency(value) {
      if (value === null || value === undefined) return "Rp 0";
      return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 }).format(value);
    },
  },
};
</script>

<style scoped>
.map-loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
</style>
