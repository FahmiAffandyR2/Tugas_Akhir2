<template>
  <div>
    <!-- Loading -->
    <div v-if="loading" class="text-center py-12">
      <v-progress-circular indeterminate color="primary" size="64" />
      <p class="mt-4 grey--text">Memuat detail...</p>
    </div>

    <template v-else-if="stop">
      <!-- Back Button -->
      <v-btn text color="primary" @click="$router.go(-1)" class="mb-4">
        <v-icon left>mdi-arrow-left</v-icon>Kembali
      </v-btn>

      <v-row>
        <!-- Stop Info -->
        <v-col cols="12" md="7">
          <v-card class="mb-4">
            <v-img
              v-if="stop.image_url"
              :src="stop.image_url"
              height="250"
              class="tourist-image"
            >
              <template v-slot:placeholder>
                <v-row class="fill-height" align="center" justify="center">
                  <v-icon size="64" color="grey lighten-1">mdi-image</v-icon>
                </v-row>
              </template>
            </v-img>
            <div v-else class="tourist-placeholder d-flex align-center justify-center">
              <v-icon size="80" color="grey lighten-1">mdi-palm-tree</v-icon>
            </div>

            <v-card-title class="text-h5 font-weight-bold">{{ stop.name }}</v-card-title>
            <v-card-subtitle>
              <v-icon small class="mr-1">mdi-map-marker</v-icon>
              {{ stop.address }}
            </v-card-subtitle>

            <v-card-text>
              <div v-if="stop.description" class="mb-4">
                <div class="overline grey--text">Deskripsi</div>
                <div class="body-1">{{ stop.description }}</div>
              </div>
            </v-card-text>
          </v-card>

          <!-- Bus Price List -->
          <v-card class="mb-4">
            <v-card-title>
              <v-icon left color="primary">mdi-cash-multiple</v-icon>
              Harga Sewa Bus
              <v-spacer />
              <v-chip v-if="distanceKm" small color="info" dark>
                <v-icon x-small left>mdi-map-marker-distance</v-icon>
                {{ distanceKm }} km dari lokasi Anda
              </v-chip>
            </v-card-title>
            <v-card-text>
              <div v-if="loadingPrices" class="text-center py-6">
                <v-progress-circular indeterminate color="primary" size="40"></v-progress-circular>
                <p class="mt-2 grey--text caption">Menghitung harga...</p>
              </div>
              <div v-else-if="!userLocation" class="text-center py-6">
                <v-icon size="48" color="grey lighten-1">mdi-crosshairs-off</v-icon>
                <p class="mt-2 grey--text">Aktifkan lokasi untuk melihat harga</p>
                <v-btn small color="primary" outlined @click="getUserLocation" :loading="gettingLocation">
                  <v-icon left small>mdi-crosshairs</v-icon>Ambil Lokasi
                </v-btn>
              </div>
              <div v-else-if="busPrices.length === 0" class="text-center py-6">
                <v-icon size="48" color="grey lighten-1">mdi-bus</v-icon>
                <p class="mt-2 grey--text">Tidak ada bus tersedia</p>
              </div>
              <v-row v-else>
                <v-col v-for="bus in busPrices" :key="bus.id" cols="12" sm="6">
                  <v-card outlined class="price-card pa-4" :class="{ 'selected-bus': selectedBus && selectedBus.id === bus.id }" @click="selectBus(bus)">
                    <div class="d-flex align-center justify-space-between mb-3">
                      <div>
                        <div class="font-weight-bold text-h6 primary--text">{{ bus.name }}</div>
                        <div class="caption grey--text">
                          <v-icon x-small class="mr-1">mdi-account-group</v-icon>
                          {{ bus.capacity }} kursi
                        </div>
                      </div>
                      <v-avatar :color="getBusColor(bus.slug)" size="48">
                        <v-icon dark>mdi-bus</v-icon>
                      </v-avatar>
                    </div>

                    <v-divider class="mb-3"></v-divider>

                    <div class="price-breakdown">
                      <div class="d-flex justify-space-between mb-1">
                        <span class="caption grey--text">Biaya dasar</span>
                        <span class="caption">{{ formatCurrency(bus.breakdown.base_price) }}</span>
                      </div>
                      <div class="d-flex justify-space-between mb-1">
                        <span class="caption grey--text">Biaya/km ({{ bus.breakdown.distance_km }} km)</span>
                        <span class="caption">{{ formatCurrency(bus.breakdown.price_per_km * bus.breakdown.distance_km) }}</span>
                      </div>
                      <div class="d-flex justify-space-between mb-1">
                        <span class="caption grey--text">Biaya jemput</span>
                        <span class="caption">{{ formatCurrency(bus.breakdown.pickup_fee) }}</span>
                      </div>
                      <v-divider class="my-2"></v-divider>
                      <div class="d-flex justify-space-between">
                        <span class="font-weight-bold">Total Harga</span>
                        <span class="font-weight-bold primary--text text-h6">{{ bus.formatted_price }}</span>
                      </div>
                    </div>

                    <v-btn
                      block
                      color="primary"
                      class="mt-3"
                      @click.stop="bookBus(bus)"
                    >
                      <v-icon left>mdi-bus-plus</v-icon>Pesan {{ bus.name }}
                    </v-btn>
                  </v-card>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Nearby Depots -->
        <v-col cols="12" md="5">
          <v-card class="mb-4">
            <v-card-title>
              <v-icon left color="primary">mdi-bus-multiple</v-icon>
              Depo Terdekat
            </v-card-title>
            <v-card-text>
              <div v-if="loadingDepots" class="text-center py-4">
                <v-progress-circular indeterminate color="primary" size="32"></v-progress-circular>
                <p class="mt-2 grey--text caption">Mencari depo...</p>
              </div>
              <div v-else-if="nearbyDepots.length === 0" class="text-center py-4">
                <v-icon size="48" color="grey lighten-1">mdi-garage-variant</v-icon>
                <p class="mt-2 grey--text">Tidak ada depo aktif di sekitar</p>
              </div>
              <v-list v-else two-line>
                <v-list-item
                  v-for="depot in nearbyDepots"
                  :key="depot.id"
                  class="mb-2"
                  outlined
                  rounded
                >
                  <v-list-item-avatar color="purple lighten-5">
                    <v-icon color="primary">mdi-bus-multiple</v-icon>
                  </v-list-item-avatar>
                  <v-list-item-content>
                    <v-list-item-title class="font-weight-bold">{{ depot.name }}</v-list-item-title>
                    <v-list-item-subtitle>{{ depot.city }}</v-list-item-subtitle>
                    <div class="d-flex align-center mt-1">
                      <v-chip x-small color="info" dark class="mr-2">
                        <v-icon x-small left>mdi-map-marker-distance</v-icon>
                        {{ depot.distance_km ? depot.distance_km.toFixed(1) + ' km' : '-' }}
                      </v-chip>
                      <v-chip x-small :color="depot.available_buses_count > 0 ? 'success' : 'grey'" dark>
                        {{ depot.available_buses_count }} bus tersedia
                      </v-chip>
                    </div>
                  </v-list-item-content>
                  <v-list-item-action>
                    <v-btn
                      small
                      color="primary"
                      :disabled="depot.available_buses_count === 0"
                      @click="selectDepot(depot)"
                    >
                      <v-icon small left>mdi-bus-plus</v-icon>Pilih
                    </v-btn>
                  </v-list-item-action>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>

          <!-- Action Cards -->
          <v-card class="mb-4" outlined>
            <v-card-title class="subtitle-1">
              <v-icon left color="orange">mdi-bus-marker</v-icon>
              Opsi Perjalanan
            </v-card-title>
            <v-card-text>
              <v-btn
                block
                large
                color="primary"
                class="mb-3"
                :disabled="!selectedDepot"
                @click="goToCharter"
              >
                <v-icon left>mdi-bus-plus</v-icon>
                Booking Charter Bus
              </v-btn>
              <v-btn
                block
                large
                outlined
                color="primary"
                :disabled="!selectedDepot"
                @click="goToRoutes"
              >
                <v-icon left>mdi-routes</v-icon>
                Lihat Rute Bus
              </v-btn>
              <p v-if="!selectedDepot" class="caption grey--text text-center mt-2">
                Pilih depo terdekat terlebih dahulu
              </p>
            </v-card-text>
          </v-card>

          <!-- Selected Depot Info -->
          <v-card v-if="selectedDepot" color="purple lighten-5">
            <v-card-text>
              <div class="d-flex align-center">
                <v-icon color="primary" class="mr-3">mdi-check-circle</v-icon>
                <div>
                  <div class="font-weight-bold">Depo Dipilih</div>
                  <div class="body-2">{{ selectedDepot.name }} - {{ selectedDepot.city }}</div>
                </div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>
    <v-alert v-else-if="!loading && !stop" type="info" text class="mt-4">
      Data tempat wisata tidak ditemukan.
      <v-btn text small @click="$router.back()">Kembali</v-btn>
    </v-alert>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading: false,
      loadingDepots: false,
      loadingPrices: false,
      gettingLocation: false,
      stop: null,
      nearbyDepots: [],
      selectedDepot: null,
      busPrices: [],
      distanceKm: null,
      userLocation: null,
      selectedBus: null,
    };
  },
  mounted() {
    if (this.$route.params.stop_id) {
      this.fetchStopDetail();
      this.getUserLocation();
    }
  },
  methods: {
    async fetchStopDetail() {
      this.loading = true;
      try {
        const response = await axios.get(`/customer-tourist/stops/${this.$route.params.stop_id}`);
        this.stop = response.data.stop;
        this.nearbyDepots = response.data.nearby_depots || [];
      } catch (error) {
        console.error("Failed to fetch stop detail:", error);
        this.$swal({ icon: "error", title: "Gagal memuat detail tempat wisata" });
        this.$router.go(-1);
      } finally {
        this.loading = false;
      }
    },
    getUserLocation() {
      if (!navigator.geolocation) {
        console.warn('Geolocation not supported');
        return;
      }
      this.gettingLocation = true;
      navigator.geolocation.getCurrentPosition(
        (position) => {
          this.userLocation = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
          };
          this.gettingLocation = false;
          this.fetchBusPrices();
        },
        (error) => {
          console.warn('Geolocation error:', error.message);
          this.gettingLocation = false;
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 }
      );
    },
    async fetchBusPrices() {
      if (!this.userLocation || !this.$route.params.stop_id) return;
      this.loadingPrices = true;
      try {
        const response = await axios.get(`/customer-tourist/stops/${this.$route.params.stop_id}/prices`, {
          params: { lat: this.userLocation.lat, lng: this.userLocation.lng },
        });
        this.busPrices = response.data.prices || [];
        this.distanceKm = response.data.distance_km;
      } catch (error) {
        console.error("Failed to fetch bus prices:", error);
        this.busPrices = [];
      } finally {
        this.loadingPrices = false;
      }
    },
    selectBus(bus) {
      this.selectedBus = bus;
    },
    selectDepot(depot) {
      this.selectedDepot = depot;
    },
    bookBus(bus) {
      this.$router.push({
        name: "customer-booking",
        query: {
          stop_id: this.$route.params.stop_id,
          bus_type_id: bus.id,
          distance_km: this.distanceKm,
        },
      });
    },
    goToCharter() {
      if (this.selectedDepot) {
        this.$router.push({ name: "customer-booking" });
      }
    },
    goToRoutes() {
      if (this.selectedDepot) {
        this.$router.push({ name: "customer-booking" });
      }
    },
    formatCurrency(value) {
      if (value === null || value === undefined) return "Rp 0";
      return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", minimumFractionDigits: 0 }).format(value);
    },
    getBusColor(slug) {
      const colors = {
        elf: 'teal',
        medium: 'blue',
        medium_25: 'blue',
        medium_26: 'blue',
        big: 'indigo',
        big_45: 'indigo',
        big_50: 'indigo',
        luxury: 'purple',
      };
      return colors[slug] || 'grey';
    },
  },
};
</script>

<style scoped>
.tourist-image {
  border-radius: 14px 14px 0 0;
}
.tourist-placeholder {
  height: 250px;
  background: #f5f5f5;
  border-radius: 14px 14px 0 0;
}
.price-card {
  border-radius: 14px;
  cursor: pointer;
  transition: all 0.2s;
  border: 2px solid #ececf3;
}
.price-card:hover {
  border-color: #7c3aed;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(124, 58, 237, 0.15);
}
.price-card.selected-bus {
  border-color: #7c3aed;
  background: #f9f5ff;
}
.price-breakdown {
  background: #f8f7fb;
  border-radius: 10px;
  padding: 12px;
}
</style>
