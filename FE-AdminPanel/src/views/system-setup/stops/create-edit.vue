<template>
  <div>
    <vue-element-loading :active="submiting" :is-full-screen="true" />
    <v-card>
      <v-card-title>
        {{ mode == 1 ? 'Edit' : 'Create' }} Stop
        <v-spacer></v-spacer>
        <v-btn depressed color="secondary" @click="$router.go(-1)" class="mx-1">
          Cancel
          <v-icon right dark>mdi-keyboard-return</v-icon>
        </v-btn>
        <v-btn depressed color="primary" @click="saveStop" class="mx-1">
          {{ mode == 1 ? "Update" : "Save" }}
          <v-icon right dark>mdi-content-save</v-icon>
        </v-btn>
      </v-card-title>
      <v-card-text>
        <v-form ref="form" v-model="valid" lazy-validation>
          <v-row>
            <v-col cols="12" md="4">
              <v-row>
                <v-col cols="12" md="3">
                  <label for="stop-name">Name</label>
                </v-col>
                <v-col cols="12" md="9">
                  <v-text-field
                    id="stop-name"
                    v-model="stop.name"
                    outlined
                    dense
                    placeholder="Stop Name"
                    required
                    :rules="nameRules"
                  ></v-text-field>
                </v-col>
              </v-row>
              <v-row>
                <v-col cols="12" md="3">
                  <label for="stop-category">Kategori</label>
                </v-col>
                <v-col cols="12" md="9">
                  <v-select
                    id="stop-category"
                    v-model="stop.category"
                    :items="categoryOptions"
                    outlined
                    dense
                  ></v-select>
                </v-col>
              </v-row>
              <v-row>
                <v-col cols="12" md="3">
                  <label for="stop-address">Alamat</label>
                </v-col>
                <v-col cols="12" md="9">
                  <v-text-field
                    id="stop-address"
                    v-model="addressSearch"
                    outlined
                    dense
                    placeholder="Ketik nama tempat, tekan Enter"
                    :rules="nameRules"
                    @keyup.enter="searchAddress"
                    :loading="searchingAddress"
                  >
                    <template v-slot:append>
                      <v-icon @click="searchAddress" :disabled="searchingAddress">mdi-magnify</v-icon>
                    </template>
                  </v-text-field>
                  <div v-if="stop.address" class="caption grey--text mt-1">
                    <v-icon x-small class="mr-1">mdi-map-marker</v-icon>{{ stop.address }}
                  </div>
                </v-col>
              </v-row>

              <!-- Tourist Fields -->
              <template v-if="stop.category === 'tourist_attraction'">
                <v-row>
                  <v-col cols="12" md="3">
                    <label for="stop-description">Deskripsi</label>
                  </v-col>
                  <v-col cols="12" md="9">
                    <v-textarea
                      id="stop-description"
                      v-model="stop.description"
                      outlined
                      dense
                      rows="3"
                      placeholder="Deskripsi tempat wisata"
                    ></v-textarea>
                  </v-col>
                </v-row>
              </template>
            </v-col>
            <v-col cols="12" md="8">
              <LeafletMapLoader
                :enabled="true"
                :center="center"
                :zoom="zoom"
                :markers="markers"
                @map-click="handleMapClick"
              ></LeafletMapLoader>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>
    </v-card>
  </div>
</template>

<script>
import LeafletMapLoader from "../../../components/LeafletMapLoader.vue";
import VueElementLoading from "vue-element-loading";
import { Keys } from "/src/config.js";

export default {
  components: {
    LeafletMapLoader,
    VueElementLoading,
  },

  data() {
    return {
      valid: true,
      nameRules: [(v) => !!v || ""],
      stop_id: null,
      markers: [],
      stop: {
        id: null,
        name: "",
        address: "",
        lat: "",
        lng: "",
        category: "regular",
        description: "",
      },
      addressSearch: "",
      searchingAddress: false,
      categoryOptions: [
        { text: "Regular", value: "regular" },
        { text: "Tempat Wisata", value: "tourist_attraction" },
        { text: "Terminal", value: "terminal" },
        { text: "Mall", value: "mall" },
        { text: "Rumah Sakit", value: "hospital" },
        { text: "Sekolah", value: "school" },
      ],
      center: {
        lat: Keys.VUE_APP_ORIGIN_LAT,
        lng: Keys.VUE_APP_ORIGIN_LNG,
      },
      zoom: 15,
      submiting: false,
      mode: null,
    };
  },
  mounted() {
    this.center.lat = parseFloat(this.center.lat);
    this.center.lng = parseFloat(this.center.lng);
    if (this.$route.params.stop_id != null) {
      this.stop_id = this.$route.params.stop_id;
      this.mode = 1;
      this.fetchStop();
    } else {
      this.mode = 0;
    }
    this.geolocate();
  },
  methods: {
    async searchAddress() {
      if (!this.addressSearch || this.addressSearch.trim().length < 3) return;
      this.searchingAddress = true;
      try {
        const query = encodeURIComponent(this.addressSearch);
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${query}&limit=1&countrycodes=id`, {
          headers: { 'Accept-Language': 'id' }
        });
        const results = await response.json();
        if (results.length > 0) {
          const place = results[0];
          this.stop.lat = parseFloat(place.lat);
          this.stop.lng = parseFloat(place.lon);
          this.stop.address = place.display_name;
          this.addStopMarker();
          this.center = { lat: this.stop.lat, lng: this.stop.lng };
          this.zoom = 16;
        } else {
          this.$notify({ title: "Info", text: "Tempat tidak ditemukan, coba kata kunci lain", type: "info" });
        }
      } catch (error) {
        console.error("Geocoding error:", error);
        this.$notify({ title: "Error", text: "Gagal mencari alamat", type: "error" });
      } finally {
        this.searchingAddress = false;
      }
    },
    addStopMarker() {
      if (this.stop.lat && this.stop.lng) {
        const position = {
          lat: parseFloat(this.stop.lat),
          lng: parseFloat(this.stop.lng),
        };
        this.markers = [{
          place_id: "stop-marker",
          position: position,
          infoText: "<strong>" + this.stop.name + "</strong><br/>" + this.stop.address,
        }];
        this.center = position;
      }
    },
    handleMapClick(place) {
      this.stop.lat = place.geometry.location.lat();
      this.stop.lng = place.geometry.location.lng();
      this.stop.address = place.formatted_address || this.stop.address;
      this.addressSearch = this.stop.address;
      this.addStopMarker();
    },
    geolocate() {
      navigator.geolocation.getCurrentPosition((position) => {
        this.center = {
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        };
      });
    },
    validate() {
      return this.$refs.form.validate();
    },
    saveStop() {
      if (!this.validate() || !this.stop.lat) {
        if (!this.stop.lat) {
          this.$notify({ title: "Error", text: "Cari dan pilih alamat terlebih dahulu", type: "error" });
        }
        return;
      }
      this.submiting = true;
      const stopData = { ...this.stop };
      if (stopData.category !== "tourist_attraction") {
        delete stopData.description;
      }
      axios
        .post("/stops/create-edit", { stop: stopData })
        .then(() => {
          this.submiting = false;
          this.$notify({
            title: "Success",
            text: this.mode == 1 ? "Stop updated!" : "Stop created!",
            type: "success",
          });
          this.$router.replace({ name: "stops" });
        })
        .catch((error) => {
          this.submiting = false;
          this.$notify({ title: "Error", text: "Error creating stop", type: "error" });
          console.log(error);
          this.$swal("Error", error.response?.data?.message || 'Terjadi kesalahan', "error");
        });
    },
    fetchStop() {
      this.submiting = true;
      axios
        .get(`/stops/${this.stop_id}`)
        .then((response) => {
          this.submiting = false;
          const data = response.data;
          this.stop = {
            id: data.id,
            name: data.name || "",
            address: data.address || "",
            lat: parseFloat(data.lat) || "",
            lng: parseFloat(data.lng) || "",
            category: data.category || "regular",
            description: data.description || "",
          };
          this.addressSearch = this.stop.address;
          this.addStopMarker();
        })
        .catch((error) => {
          this.submiting = false;
          this.$notify({ title: "Error", text: "Error fetching stop data", type: "error" });
          console.log(error);
        });
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
  cursor: move;
}
.list-group-item i {
  cursor: pointer;
}
.v-application ul {
  padding-left: 12px !important;
}
.input--error {
  border-color: red;
}
</style>

<style lang="scss">
.active-stop {
  background: rgba($primary-shade--light, 0.15) !important;
}
</style>
