<template>
  <div>
    <v-card class="mx-auto" max-width="500"> </v-card>
    <v-row>
      <v-col cols="12" :md="stop && stop.category === 'tourist_attraction' ? 6 : 12">
        <v-card>
          <v-card-title>
            <v-icon left :color="getCategoryColor(stop ? stop.category : 'regular')">{{ getCategoryIcon(stop ? stop.category : 'regular') }}</v-icon>
            <span v-if="stop != null" class="me-3">{{ stop.name }}</span>
            <v-chip v-if="stop" small :color="getCategoryColor(stop.category)" dark class="ml-2">
              {{ getCategoryLabel(stop.category) }}
            </v-chip>
            <v-spacer></v-spacer>
            <v-btn depressed color="secondary" @click="$router.go(-1)" class="mx-1">
              Back
              <v-icon right dark>mdi-keyboard-return</v-icon>
            </v-btn>
          </v-card-title>
          <v-card-text>
            <div class="row">
              <div class="col-md-5">
                <div v-if="stop != null" class="list-group-item pa-2 active-stop" @click="selectedItem = stop.place_id">
                  <div class="text-dark m-1 my-1">{{ stop.address }}</div>
                </div>

                <!-- Tourist Info -->
                <template v-if="stop && stop.category === 'tourist_attraction'">
                  <v-divider class="my-3"></v-divider>

                  <div v-if="stop.description" class="mb-3">
                    <div class="overline grey--text">Deskripsi</div>
                    <div class="body-2">{{ stop.description }}</div>
                  </div>
                </template>
              </div>
              <div class="col-md-7" id="map">
                <LeafletMapLoader
                  :enabled="false"
                  :center="center"
                  :selected="selectedItem"
                  :zoom="zoom"
                  :markers="markers"
                ></LeafletMapLoader>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Nearby Depots Panel -->
      <v-col v-if="stop" cols="12" md="6">
        <v-card>
          <v-card-title>
            <v-icon left color="primary">mdi-garage-variant</v-icon>
            Depo Terdekat
            <v-spacer></v-spacer>
            <v-btn small color="primary" outlined @click="fetchNearbyDepots" :loading="loadingDepots">
              <v-icon left small>mdi-refresh</v-icon>Refresh
            </v-btn>
          </v-card-title>
          <v-card-text>
            <div v-if="loadingDepots" class="text-center py-4">
              <v-progress-circular indeterminate color="primary"></v-progress-circular>
              <p class="mt-2 grey--text">Mencari depo terdekat...</p>
            </div>
            <div v-else-if="nearbyDepots.length === 0" class="text-center py-4">
              <v-icon size="48" color="grey lighten-1">mdi-garage-variant</v-icon>
              <p class="mt-2 grey--text">Tidak ada depo aktif ditemukan</p>
            </div>
            <v-list v-else two-line>
              <v-list-item v-for="depot in nearbyDepots" :key="depot.id" class="mb-2" outlined rounded>
                <v-list-item-avatar color="purple lighten-5">
                  <v-icon color="primary">mdi-bus-multiple</v-icon>
                </v-list-item-avatar>
                <v-list-item-content>
                  <v-list-item-title class="font-weight-bold">{{ depot.name }}</v-list-item-title>
                  <v-list-item-subtitle>{{ depot.city }} · {{ depot.address }}</v-list-item-subtitle>
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
                  <v-btn small color="primary" :disabled="depot.available_buses_count === 0" @click="viewDepot(depot)">
                    <v-icon small left>mdi-eye</v-icon>Lihat
                  </v-btn>
                </v-list-item-action>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script>
import LeafletMapLoader from "../../../components/LeafletMapLoader.vue";
import { Keys } from "/src/config.js";

export default {
  components: {
    LeafletMapLoader,
  },

  data() {
    return {
      stop_id: null,
      loading: false,
      markers: [],
      selectedIdx: null,
      currentPlace: null,
      stop: null,
      nearbyDepots: [],
      loadingDepots: false,
      center: {
        lat: Keys.VUE_APP_ORIGIN_LAT,
        lng: Keys.VUE_APP_ORIGIN_LNG,
      },
      zoom: 12,
      selectedItem: null,
    };
  },
  mounted() {
    this.center.lat = parseFloat(this.center.lat);
    this.center.lng = parseFloat(this.center.lng);
    if (this.$route.params.stop_id != null) {
      this.stop_id = this.$route.params.stop_id;
      this.fetchStop();
    }
  },
  methods: {
    getCategoryColor(category) {
      const colors = {
        regular: "grey",
        tourist_attraction: "green",
        terminal: "blue",
        mall: "purple",
        hospital: "red",
        school: "orange",
      };
      return colors[category] || "grey";
    },
    getCategoryIcon(category) {
      const icons = {
        regular: "mdi-map-marker",
        tourist_attraction: "mdi-palm-tree",
        terminal: "mdi-bus-stop",
        mall: "mdi-shopping",
        hospital: "mdi-hospital",
        school: "mdi-school",
      };
      return icons[category] || "mdi-map-marker";
    },
    getCategoryLabel(category) {
      const labels = {
        regular: "Regular",
        tourist_attraction: "Wisata",
        terminal: "Terminal",
        mall: "Mall",
        hospital: "RS",
        school: "Sekolah",
      };
      return labels[category] || category;
    },
    addStopMarker() {
      if (this.stop) {
        const position = {
          lat: parseFloat(this.stop.lat),
          lng: parseFloat(this.stop.lng),
        };
        let marker = {
          place_id: this.stop.place_id,
          position: position,
          infoText: "<strong>" + this.stop.name + "</strong><br/>" + this.stop.address,
        };
        this.markers.push(marker);
        this.center = position;
      }
    },
    fetchStop() {
      this.loading = true;
      axios
        .get(`/stops/${this.stop_id}`)
        .then((response) => {
          this.loading = false;
          this.stop = response.data;
          this.stop.lat = parseFloat(this.stop.lat);
          this.stop.lng = parseFloat(this.stop.lng);
          this.addStopMarker();
          this.fetchNearbyDepots();
        })
        .catch((error) => {
          this.loading = false;
          this.$notify({ title: "Error", text: "Error fetching stop data", type: "error" });
          console.log(error);
          this.$router.go(-1);
        });
    },
    fetchNearbyDepots() {
      this.loadingDepots = true;
      this.nearbyDepots = [];
      axios
        .get(`/stops/${this.stop_id}/nearby-depots`)
        .then((response) => {
          this.nearbyDepots = response.data.depots || [];
        })
        .catch((error) => {
          console.error("Failed to fetch nearby depots:", error);
          this.$notify({ title: "Error", text: "Gagal memuat depo terdekat", type: "error" });
        })
        .then(() => {
          this.loadingDepots = false;
        });
    },
    viewDepot(depot) {
      this.$router.push({ name: "fleet-depot-detail", params: { depot_id: depot.id } });
    },
  },
};
</script>

<style>
.flip-list-move {
  transition: transform 0.5s;
}
.no-move {
  transition: transform 0s;
}
.ghost {
  opacity: 0.5;
  background: #c8ebfb;
}
.list-group {
  min-height: 20px;
}
.list-group-item {
  cursor: pointer;
}
.list-group-item i {
  cursor: pointer;
}
.v-application ul {
  padding-left: 12px !important;
}
</style>

<style lang="scss">
.active-stop {
  background: rgba($primary-shade--light, 0.15) !important;
}
</style>
