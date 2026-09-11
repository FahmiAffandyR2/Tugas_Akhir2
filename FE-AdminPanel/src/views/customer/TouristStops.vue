<template>
  <div>
    <section class="customer-hero pa-6 pa-md-9 mb-7">
      <v-row align="center">
        <v-col cols="12" md="7">
          <div class="hero-label mb-3">
            <v-icon small color="white" class="mr-1">mdi-palm-tree</v-icon> Jelajahi Tempat Wisata
          </div>
          <h1 class="text-h4 text-md-h3 font-weight-bold mb-3">Mau berwisata ke mana?</h1>
          <p class="hero-copy mb-6">Temukan tempat wisata menarik di sekitar Anda dan temukan bus terdekat untuk perjalanan Anda.</p>
        </v-col>
        <v-col cols="12" md="5" class="d-none d-md-flex justify-center">
          <div class="hero-bus">
            <v-icon size="118" color="white">mdi-map-marker-star</v-icon>
          </div>
        </v-col>
      </v-row>
    </section>

    <!-- Search -->
    <v-card class="mb-6">
      <v-card-text>
        <v-row align="center">
          <v-col cols="12" md="6">
            <v-text-field
              v-model="search"
              label="Cari tempat wisata..."
              outlined
              dense
              prepend-inner-icon="mdi-magnify"
              clearable
              @input="debouncedSearch"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-btn color="primary" @click="fetchTouristStops" :loading="loading">
              <v-icon left>mdi-magnify</v-icon>Cari
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12">
      <v-progress-circular indeterminate color="primary" size="64" />
      <p class="mt-4 grey--text">Memuat tempat wisata...</p>
    </div>

    <!-- Empty State -->
    <v-card v-else-if="touristStops.length === 0" class="text-center py-12">
      <v-icon size="80" color="grey lighten-1">mdi-map-marker-off</v-icon>
      <h3 class="mt-4 grey--text">Tidak ada tempat wisata ditemukan</h3>
      <p class="grey--text">Coba kata kunci pencarian yang berbeda</p>
    </v-card>

    <!-- Tourist Stops Grid -->
    <v-row v-else>
      <v-col v-for="stop in touristStops" :key="stop.id" cols="12" sm="6" md="4">
        <v-card class="tourist-card" @click="viewStop(stop)" hover>
          <v-img
            v-if="stop.image_url"
            :src="stop.image_url"
            height="180"
            class="tourist-image"
          >
            <template v-slot:placeholder>
              <v-row class="fill-height" align="center" justify="center">
                <v-icon size="48" color="grey lighten-1">mdi-image</v-icon>
              </v-row>
            </template>
          </v-img>
          <div v-else class="tourist-placeholder d-flex align-center justify-center">
            <v-icon size="64" color="grey lighten-1">mdi-palm-tree</v-icon>
          </div>

          <v-card-title class="font-weight-bold">{{ stop.name }}</v-card-title>
          <v-card-subtitle>
            <v-icon x-small class="mr-1">mdi-map-marker</v-icon>
            {{ stop.address }}
          </v-card-subtitle>

          <v-card-text>
            <p v-if="stop.description" class="body-2 grey--text text--darken-1 mb-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
              {{ stop.description }}
            </p>

            <div v-if="stop.nearest_depot" class="mt-3">
              <v-divider class="mb-2"></v-divider>
              <div class="overline grey--text">Depo Terdekat</div>
              <div class="d-flex align-center">
                <v-icon small color="primary" class="mr-1">mdi-garage-variant</v-icon>
                <span class="body-2 font-weight-bold">{{ stop.nearest_depot.name }}</span>
                <v-chip x-small color="info" dark class="ml-2">
                  {{ stop.nearest_depot.distance_km }} km
                </v-chip>
              </div>
            </div>
          </v-card-text>

          <v-card-actions>
            <v-btn text color="primary" @click.stop="viewStop(stop)">
              <v-icon left small>mdi-information-outline</v-icon>Detail
            </v-btn>
            <v-spacer></v-spacer>
            <v-btn color="primary" :disabled="!stop.nearest_depot" @click.stop="bookFromDepot(stop)">
              <v-icon left small>mdi-bus-plus</v-icon>Pesan Bus
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading: false,
      search: "",
      touristStops: [],
      searchTimeout: null,
    };
  },
  mounted() {
    this.fetchTouristStops();
  },
  methods: {
    debouncedSearch() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.fetchTouristStops();
      }, 500);
    },
    async fetchTouristStops() {
      this.loading = true;
      try {
        const params = {};
        if (this.search) params.search = this.search;
        const response = await axios.get("/customer-tourist/stops", { params });
        this.touristStops = response.data.tourist_stops || [];
      } catch (error) {
        console.error("Failed to fetch tourist stops:", error);
        this.$swal({ icon: "error", title: "Gagal memuat tempat wisata" });
      } finally {
        this.loading = false;
      }
    },
    viewStop(stop) {
      this.$router.push({ name: "customer-stop-detail", params: { stop_id: stop.id } });
    },
    bookFromDepot(stop) {
      if (stop.nearest_depot) {
        this.$router.push({ name: "customer-booking" });
      }
    },
  },
};
</script>

<style scoped>
.customer-hero {
  background: linear-gradient(135deg, #7c3aed 0%, #9b5cff 100%);
  border-radius: 16px;
  color: white;
}
.hero-label {
  display: inline-flex;
  align-items: center;
  background: rgba(255, 255, 255, 0.2);
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.85rem;
}
.hero-copy {
  color: rgba(255, 255, 255, 0.85);
  max-width: 420px;
}
.hero-bus {
  width: 160px;
  height: 160px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
}
.tourist-card {
  border-radius: 14px;
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
  cursor: pointer;
}
.tourist-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}
.tourist-image {
  border-radius: 14px 14px 0 0;
}
.tourist-placeholder {
  height: 180px;
  background: #f5f5f5;
}
</style>
