<template>
  <div>
    <vue-element-loading :active="submiting" />
    <v-card>
      <!-- Page Heading -->
      <v-card-title>
        <span class="me-3">Live Tracking Perjalanan</span>
        <v-spacer></v-spacer>
        <v-chip small color="green lighten-5" text-color="green darken-2" class="mr-2">
          <v-icon small left>mdi-map-outline</v-icon>Leaflet · OpenStreetMap
        </v-chip>
        <v-chip v-if="activeAlerts.length > 0" small color="error" text-color="white" class="mr-2">
          <v-icon small left>mdi-alert</v-icon>{{ activeAlerts.length }} Alert(s)
        </v-chip>
      </v-card-title>

      <!-- Tabs -->
      <v-tabs v-model="activeTab" color="primary" class="px-4">
        <v-tab href="#realtime">
          <v-icon small left>mdi-bus</v-icon>
          Real-time Trip
        </v-tab>
        <v-tab href="#all-buses">
          <v-icon small left>mdi-bus-school</v-icon>
          Semua Bus
          <v-chip x-small class="ml-2" color="primary" dark v-if="allVehicles.length">{{ allVehicles.length }}</v-chip>
        </v-tab>
        <v-tab href="#riwayat">
          <v-icon small left>mdi-history</v-icon>
          Riwayat
        </v-tab>
        <v-tab href="#alerts" class="ml-2">
          <v-icon small left>mdi-bell-alert</v-icon>
          Alerts
          <span v-if="activeAlerts.length > 0" class="ml-2 badge-count">{{ activeAlerts.length }}</span>
        </v-tab>
      </v-tabs>

      <v-divider />

      <v-card-text>
        <v-tabs-items v-model="activeTab">
        <!-- ==================== TAB REAL-TIME ==================== -->
        <v-tab-item value="realtime">
          <div class="row">
            <div v-if="on_route_trips.length === 0" class="col-md-4 text-center py-10">
              <v-icon size="100" class="py-10">mdi-bus-alert</v-icon>
              <h3>No on-route trips</h3>
            </div>
            <div v-else class="col-md-4">
              <div
                class="list-group-item py-6 my-2"
                :class="selectedIdx == index ? 'active-stop' : ''"
                v-for="(element, index) in on_route_trips"
                :key="element.id"
                @click="selectedItem = element.channel; selectedIdx = index;"
              >
                <div v-if="element.driver" class="d-flex align-center">
                  <v-icon size="30" class="mr-2">mdi-bus</v-icon>
                  <div class="font-weight-bold text-dark m-1 my-1">
                    {{ element.driver.name }}
                  </div>
                </div>
                <div class="text-dark m-1 my-1 ml-1" v-if="element.trip && element.trip.route">
                  <v-icon size="25" class="mr-2">mdi-road-variant</v-icon>
                  {{ element.trip.route.name }}
                </div>
                <div class="text-dark m-1 my-1 ml-1">
                  <v-icon size="25" class="mr-2">mdi-clock-time-four-outline</v-icon>
                  {{ element.started_at }}
                </div>
                <div class="m-1 my-1 ml-1" :class="hasPosition(element) ? 'success--text' : 'warning--text'">
                  <v-icon size="22" class="mr-2" :color="hasPosition(element) ? 'success' : 'warning'">mdi-crosshairs-gps</v-icon>
                  {{ hasPosition(element) ? 'GPS terhubung' : 'Menunggu lokasi GPS driver' }}
                </div>
              </div>
            </div>
            <div class="col-md-8" id="map">
              <LeafletMapLoader
                :enabled="false"
                :center="center"
                :selected="selectedItem"
                :zoom="zoom"
                :markers="markers"
                :polylines="polyline"
                :animate="true"
              />
            </div>
          </div>
        </v-tab-item>

        <!-- ==================== TAB SEMUA BUS ==================== -->
        <v-tab-item value="all-buses">
          <div class="row">
            <div class="col-md-4" style="max-height: 500px; overflow-y: auto;">
              <div class="d-flex align-center mb-3">
                <v-icon color="primary" class="mr-2">mdi-bus-school</v-icon>
                <span class="text-h6 font-weight-bold">Semua Bus</span>
                <v-spacer />
                <v-btn small text color="primary" @click="fetchAllVehicles">
                  <v-icon left small>mdi-refresh</v-icon>Refresh
                </v-btn>
              </div>

              <v-chip-group class="mb-3">
                <v-chip small :color="vehicleFilter === 'all' ? 'primary' : 'grey lighten-2'" @click="vehicleFilter = 'all'">
                  Semua ({{ allVehicles.length }})
                </v-chip>
                <v-chip small :color="vehicleFilter === 'online' ? 'success' : 'grey lighten-2'" @click="vehicleFilter = 'online'">
                  Online ({{ allVehicles.filter(v => v.status === 'online').length }})
                </v-chip>
                <v-chip small :color="vehicleFilter === 'idle' ? 'warning' : 'grey lighten-2'" @click="vehicleFilter = 'idle'">
                  Idle ({{ allVehicles.filter(v => v.status === 'idle').length }})
                </v-chip>
                <v-chip small :color="vehicleFilter === 'offline' ? 'error' : 'grey lighten-2'" @click="vehicleFilter = 'offline'">
                  Offline ({{ allVehicles.filter(v => v.status === 'offline').length }})
                </v-chip>
              </v-chip-group>

              <div v-if="filteredVehicles.length === 0" class="text-center py-8">
                <v-icon size="60" class="grey--text">mdi-bus</v-icon>
                <p class="grey--text mt-2">Tidak ada bus ditemukan</p>
              </div>

              <div
                v-for="vehicle in filteredVehicles"
                :key="'v-' + vehicle.id"
                class="list-group-item py-3 mb-2"
                :class="selectedVehicleIdx === vehicle.id ? 'active-stop' : ''"
                @click="selectVehicle(vehicle)"
                style="border: 1px solid rgba(58,53,65,.08); border-radius: 12px; cursor: pointer;"
              >
                <div class="d-flex align-center">
                  <v-avatar :color="vehicle.status === 'online' ? 'success' : vehicle.status === 'idle' ? 'warning' : 'error'" size="36">
                    <v-icon dark small>mdi-bus</v-icon>
                  </v-avatar>
                  <div class="ml-3 flex-grow-1">
                    <div class="font-weight-bold">{{ vehicle.fleet_number || vehicle.license }}</div>
                    <div class="caption grey--text">{{ vehicle.license }}</div>
                  </div>
                  <v-chip x-small :color="vehicle.status === 'online' ? 'success' : vehicle.status === 'idle' ? 'warning' : 'error'" dark>
                    {{ vehicle.status }}
                  </v-chip>
                </div>
                <div v-if="vehicle.driver" class="caption mt-2 ml-9 grey--text">
                  <v-icon x-small class="mr-1">mdi-account</v-icon>{{ vehicle.driver.name }}
                </div>
                <div v-if="vehicle.speed !== null" class="caption ml-9 grey--text">
                  <v-icon x-small class="mr-1">mdi-speedometer</v-icon>{{ Math.round(vehicle.speed) }} km/h
                </div>
                <div class="caption ml-9 grey--text">
                  <v-icon x-small class="mr-1">mdi-clock</v-icon>{{ formatDateShort(vehicle.last_gps_at) }}
                  <v-chip x-small class="ml-1" outlined>{{ vehicle.gps_source === 'device' ? 'Device' : 'Phone' }}</v-chip>
                </div>
              </div>
            </div>

            <div class="col-md-8" id="all-buses-map" style="min-height: 500px;">
              <LeafletMapLoader
                :enabled="false"
                :center="allBusesCenter"
                :zoom="12"
                :markers="allBusMarkers"
                :animate="true"
              />
            </div>
          </div>
        </v-tab-item>

        <!-- ==================== TAB RIWAYAT ==================== -->
        <v-tab-item value="riwayat">
          <div class="row">
            <!-- Filter Panel -->
            <div class="col-md-4">
              <v-card flat class="pa-4 mb-4" style="border: 1px solid rgba(58,53,65,.08); border-radius: 16px;">
                <div class="d-flex align-center mb-4">
                  <v-icon color="primary" class="mr-2">mdi-filter</v-icon>
                  <span class="text-h6 font-weight-bold">Filter Riwayat</span>
                </div>

                <v-text-field
                  v-model="historyFilter.startDate"
                  label="Tanggal Mulai"
                  type="date"
                  outlined
                  dense
                  class="mb-2"
                />

                <v-text-field
                  v-model="historyFilter.endDate"
                  label="Tanggal Selesai"
                  type="date"
                  outlined
                  dense
                  class="mb-2"
                />

                <v-select
                  v-model="historyFilter.driverId"
                  :items="activeDrivers"
                  item-text="name"
                  item-value="id"
                  label="Driver"
                  outlined
                  dense
                  clearable
                  class="mb-2"
                />

                <v-select
                  v-model="historyFilter.tripId"
                  :items="driverTrips"
                  item-text="routeName"
                  item-value="id"
                  label="Trip"
                  outlined
                  dense
                  clearable
                  class="mb-4"
                />

                <v-btn
                  block
                  color="primary"
                  @click="fetchHistoryData"
                  :loading="loadingHistory"
                >
                  <v-icon left>mdi-magnify</v-icon>
                  Cari Riwayat
                </v-btn>
              </v-card>

              <!-- Playback Player -->
              <PlaybackPlayer
                v-if="playbackLogs.length > 0"
                :logs="playbackLogs"
                :trip-id="historyFilter.tripId"
                :summary="playbackSummary"
                @position-change="onPlaybackPositionChange"
                @export="exportHistory"
              />
            </div>

            <!-- Map -->
            <div class="col-md-8" id="history-map">
              <LeafletMapLoader
                :enabled="false"
                :center="historyCenter"
                :zoom="zoom"
                :markers="historyMarkers"
                :polylines="historyPolylines"
              />
            </div>
          </div>
        </v-tab-item>

        <!-- ==================== TAB ALERTS ==================== -->
        <v-tab-item value="alerts">
          <div class="row">
            <div class="col-md-8">
              <div class="d-flex align-center mb-4">
                <v-icon color="warning" class="mr-2">mdi-bell-alert</v-icon>
                <span class="text-h6 font-weight-bold">GPS Alerts Aktif</span>
                <v-spacer />
                <v-btn small text color="primary" @click="fetchAlerts">
                  <v-icon left small>mdi-refresh</v-icon>Refresh
                </v-btn>
              </div>

              <div v-if="activeAlerts.length === 0" class="text-center py-10">
                <v-icon size="80" color="success">mdi-check-circle</v-icon>
                <h3 class="mt-4">Tidak ada alert aktif</h3>
                <p class="grey--text">Semua bus beroperasi normal</p>
              </div>

              <v-card
                v-for="alert in activeAlerts"
                :key="alert.id"
                flat
                class="mb-3 pa-4"
                :style="{ borderLeft: `4px solid ${getAlertColor(alert.alert_type)}` }"
              >
                <div class="d-flex align-start">
                  <v-icon :color="getAlertColor(alert.alert_type)" class="mr-3 mt-1">
                    {{ getAlertIcon(alert.alert_type) }}
                  </v-icon>
                  <div class="flex-grow-1">
                    <div class="d-flex align-center">
                      <span class="font-weight-bold">{{ alert.driver && alert.driver.name || 'Unknown' }}</span>
                      <v-chip small :color="getAlertColor(alert.alert_type)" dark class="ml-2">
                        {{ getAlertTypeLabel(alert.alert_type) }}
                      </v-chip>
                    </div>
                    <div class="grey--text mt-1">{{ alert.message }}</div>
                    <div class="caption grey--text mt-1">
                      <v-icon small class="mr-1">mdi-clock</v-icon>
                      {{ formatDate(alert.created_at) }}
                    </div>
                  </div>
                  <div>
                    <v-btn small text color="error" @click="dismissAlert(alert.id)">
                      <v-icon small left>mdi-close</v-icon>Dismiss
                    </v-btn>
                  </div>
                </div>
              </v-card>
            </div>

            <!-- Alert Stats -->
            <div class="col-md-4">
              <v-card flat class="pa-4" style="border: 1px solid rgba(58,53,65,.08); border-radius: 16px;">
                <div class="d-flex align-center mb-4">
                  <v-icon color="primary" class="mr-2">mdi-chart-bar</v-icon>
                  <span class="text-h6 font-weight-bold">Statistik Alert</span>
                </div>

                <div class="mb-3">
                  <div class="d-flex justify-space-between">
                    <span class="grey--text">GPS Offline</span>
                    <span class="font-weight-bold error--text">
                      {{ activeAlerts.filter(a => a.alert_type === 'gps_offline').length }}
                    </span>
                  </div>
                </div>

                <div class="mb-3">
                  <div class="d-flex justify-space-between">
                    <span class="grey--text">Keluar Jalur</span>
                    <span class="font-weight-bold warning--text">
                      {{ activeAlerts.filter(a => a.alert_type === 'out_of_route').length }}
                    </span>
                  </div>
                </div>

                <div class="mb-3">
                  <div class="d-flex justify-space-between">
                    <span class="grey--text">Kecepatan Berlebih</span>
                    <span class="font-weight-bold orange--text">
                      {{ activeAlerts.filter(a => a.alert_type === 'speed_exceeded').length }}
                    </span>
                  </div>
                </div>

                <div class="mb-3">
                  <div class="d-flex justify-space-between">
                    <span class="grey--text">Tiba di Pool</span>
                    <span class="font-weight-bold success--text">
                      {{ activeAlerts.filter(a => a.alert_type === 'arrived_at_depot').length }}
                    </span>
                  </div>
                </div>

                <v-divider class="my-3" />

                <v-btn block outlined color="primary" @click="showAlertLog = true">
                  <v-icon left small>mdi-history</v-icon>Lihat Riwayat Alert
                </v-btn>
              </v-card>
            </div>
          </div>
        </v-tab-item>
        </v-tabs-items>
      </v-card-text>
    </v-card>

    <!-- Export Dialog -->
    <v-dialog v-model="showExportDialog" max-width="500">
      <v-card>
        <v-card-title>
          <v-icon left>mdi-download</v-icon>Export GPS Logs
          <v-spacer />
          <v-btn icon @click="showExportDialog = false"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>
        <v-card-text>
          <v-text-field v-model="exportFilter.startDate" label="Tanggal Mulai" type="date" outlined dense class="mb-2" />
          <v-text-field v-model="exportFilter.endDate" label="Tanggal Selesai" type="date" outlined dense class="mb-2" />
          <v-select v-model="exportFilter.driverId" :items="activeDrivers" item-text="name" item-value="id" label="Driver" outlined dense clearable class="mb-2" />
          <v-select v-model="exportFilter.tripId" :items="driverTrips" item-text="routeName" item-value="id" label="Trip" outlined dense clearable />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn text @click="showExportDialog = false">Batal</v-btn>
          <v-btn color="primary" @click="exportData" :loading="exporting">
            <v-icon left>mdi-download</v-icon>Download CSV
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Alert Log Dialog -->
    <v-dialog v-model="showAlertLog" max-width="800">
      <v-card>
        <v-card-title>
          <v-icon left>mdi-history</v-icon>Riwayat Alert
          <v-spacer />
          <v-btn icon @click="showAlertLog = false"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>
        <v-card-text style="max-height: 500px; overflow-y: auto;">
          <div v-if="alertLog.length === 0" class="text-center py-8">
            <v-icon size="60" color="grey lighten-1">mdi-bell-off</v-icon>
            <p class="grey--text mt-2">Belum ada riwayat alert</p>
          </div>
          <v-simple-table v-else dense>
            <template v-slot:default>
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Driver</th>
                  <th>Tipe</th>
                  <th>Pesan</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="alert in alertLog" :key="alert.id">
                  <td>{{ formatDate(alert.created_at) }}</td>
                  <td>{{ alert.driver && alert.driver.name || '-' }}</td>
                  <td>
                    <v-chip x-small :color="getAlertColor(alert.alert_type)" dark>
                      {{ getAlertTypeLabel(alert.alert_type) }}
                    </v-chip>
                  </td>
                  <td class="text-truncate" style="max-width: 200px;">{{ alert.message }}</td>
                  <td>
                    <v-chip x-small :color="alert.dismissed ? 'success' : 'warning'" dark>
                      {{ alert.dismissed ? 'Dismissed' : 'Active' }}
                    </v-chip>
                  </td>
                </tr>
              </tbody>
            </template>
          </v-simple-table>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import LeafletMapLoader from "../../components/LeafletMapLoader.vue";
import PlaybackPlayer from "./PlaybackPlayer.vue";
import VueElementLoading from "vue-element-loading";
import { Keys } from '@/config.js';
import { playAlertSound, playCriticalSound, playWarningSound, playSuccessSound } from '@/utils/alertSound';
import { requestNotificationPermission, sendGpsAlertNotification } from '@/utils/browserNotification';

export default {
  components: {
    LeafletMapLoader,
    PlaybackPlayer,
    VueElementLoading,
  },

  data() {
    return {
      // Tab
      activeTab: 'realtime',

      // Real-time
      markers: [],
      selectedIdx: null,
      on_route_trips: [],
      polyline: [],
      center: {
        lat: Keys.VUE_APP_ORIGIN_LAT,
        lng: Keys.VUE_APP_ORIGIN_LNG,
      },
      zoom: 12,
      selectedItem: null,
      submiting: false,
      pollTimer: null,
      realtimeAvailable: false,

      // All Buses
      allVehicles: [],
      vehicleFilter: 'all',
      selectedVehicleIdx: null,
      allBusesCenter: { lat: parseFloat(Keys.VUE_APP_ORIGIN_LAT), lng: parseFloat(Keys.VUE_APP_ORIGIN_LNG) },
      allBusMarkers: [],
      allBusesPollTimer: null,

      // History
      historyFilter: {
        startDate: new Date().toISOString().split('T')[0],
        endDate: new Date().toISOString().split('T')[0],
        driverId: null,
        tripId: null,
      },
      activeDrivers: [],
      driverTrips: [],
      playbackLogs: [],
      playbackSummary: {},
      historyMarkers: [],
      historyPolylines: [],
      historyCenter: { lat: parseFloat(Keys.VUE_APP_ORIGIN_LAT), lng: parseFloat(Keys.VUE_APP_ORIGIN_LNG) },
      loadingHistory: false,

      // Alerts
      activeAlerts: [],
      alertLog: [],
      showAlertLog: false,
      alertPollTimer: null,

      // Export
      showExportDialog: false,
      exportFilter: {
        startDate: new Date().toISOString().split('T')[0],
        endDate: new Date().toISOString().split('T')[0],
        driverId: null,
        tripId: null,
      },
      exporting: false,
    };
  },

  computed: {
    filteredVehicles() {
      if (this.vehicleFilter === 'all') return this.allVehicles;
      return this.allVehicles.filter(v => v.status === this.vehicleFilter);
    },
  },

  mounted() {
    this.center.lat = parseFloat(this.center.lat);
    this.center.lng = parseFloat(this.center.lng);
    this.allBusesCenter.lat = parseFloat(this.allBusesCenter.lat);
    this.allBusesCenter.lng = parseFloat(this.allBusesCenter.lng);

    requestNotificationPermission();

    this.fetchOnRouteTrips();
    this.fetchActiveDrivers();
    this.fetchAlerts();
    this.fetchAllVehicles();

    this.pollTimer = window.setInterval(this.refreshTripPositions, 10000);
    this.alertPollTimer = window.setInterval(this.fetchAlerts, 30000);
    this.allBusesPollTimer = window.setInterval(this.fetchAllVehicles, 15000);

    this.realtimeAvailable = Boolean(window.Echo && typeof window.Echo.channel === 'function');
    if (this.realtimeAvailable) {
      this.listenToAlertChannel();
    }

    this._visibilityHandler = () => {
      if (document.hidden) {
        this.pausePolling();
      } else {
        this.resumePolling();
      }
    };
    document.addEventListener('visibilitychange', this._visibilityHandler);
  },

  beforeDestroy() {
    if (this.pollTimer) window.clearInterval(this.pollTimer);
    if (this.alertPollTimer) window.clearInterval(this.alertPollTimer);
    if (this.allBusesPollTimer) window.clearInterval(this.allBusesPollTimer);
    if (this._visibilityHandler) {
      document.removeEventListener('visibilitychange', this._visibilityHandler);
    }
  },

  watch: {
    historyFilter: {
      deep: true,
      handler(val) {
        if (val.driverId) {
          this.fetchDriverTrips(val.driverId);
        }
      },
    },
    vehicleFilter() {
      this.buildAllBusMarkers();
    },
  },

  methods: {
    pausePolling() {
      if (this.pollTimer) { window.clearInterval(this.pollTimer); this.pollTimer = null; }
      if (this.alertPollTimer) { window.clearInterval(this.alertPollTimer); this.alertPollTimer = null; }
      if (this.allBusesPollTimer) { window.clearInterval(this.allBusesPollTimer); this.allBusesPollTimer = null; }
    },
    resumePolling() {
      if (!this.pollTimer) this.pollTimer = window.setInterval(this.refreshTripPositions, 10000);
      if (!this.alertPollTimer) this.alertPollTimer = window.setInterval(this.fetchAlerts, 30000);
      if (!this.allBusesPollTimer) this.allBusesPollTimer = window.setInterval(this.fetchAllVehicles, 15000);
      this.refreshTripPositions();
      this.fetchAlerts();
      this.fetchAllVehicles();
    },
    // ==================== ALL BUSES ====================
    async fetchAllVehicles() {
      try {
        const response = await axios.get('/admin/tracking/vehicles');
        this.allVehicles = response.data;
        this.buildAllBusMarkers();
      } catch (error) {
        console.error('Failed to fetch all vehicles:', error);
      }
    },

    buildAllBusMarkers() {
      const filtered = this.filteredVehicles;
      const newMarkers = filtered.map(vehicle => ({
        place_id: 'bus-' + vehicle.id,
        position: { lat: vehicle.lat, lng: vehicle.lng },
        infoText: '<b>' + (vehicle.fleet_number || vehicle.license) + '</b><br/>' +
          (vehicle.driver ? 'Driver: ' + vehicle.driver.name + '<br/>' : '') +
          'Status: ' + vehicle.status + '<br/>' +
          (vehicle.speed ? 'Speed: ' + Math.round(vehicle.speed) + ' km/h<br/>' : '') +
          'Source: ' + (vehicle.gps_source === 'device' ? 'Device' : 'Phone'),
        icon: vehicle.status === 'online'
          ? 'https://cdn-icons-png.flaticon.com/32/3471/3471521.png'
          : vehicle.status === 'idle'
            ? 'https://cdn-icons-png.flaticon.com/32/3471/3471510.png'
            : 'https://cdn-icons-png.flaticon.com/32/3471/3471499.png',
      }));

      if (this.allBusMarkers.length === 0) {
        this.allBusMarkers = newMarkers;
        return;
      }

      const oldMap = {};
      this.allBusMarkers.forEach(m => { oldMap[m.place_id] = m; });
      let changed = false;
      newMarkers.forEach(m => {
        const old = oldMap[m.place_id];
        if (!old || old.position.lat !== m.position.lat || old.position.lng !== m.position.lng) {
          changed = true;
        }
      });
      if (!changed && newMarkers.length === this.allBusMarkers.length) return;
      this.allBusMarkers = newMarkers;
    },

    selectVehicle(vehicle) {
      this.selectedVehicleIdx = vehicle.id;
      this.allBusesCenter = { lat: vehicle.lat, lng: vehicle.lng };
    },

    formatDateShort(date) {
      if (!date) return '-';
      const d = new Date(date);
      const now = new Date();
      const diffMs = now - d;
      if (diffMs < 60000) return 'Baru saja';
      if (diffMs < 3600000) return Math.floor(diffMs / 60000) + ' mnt lalu';
      if (diffMs < 86400000) return Math.floor(diffMs / 3600000) + ' jam lalu';
      return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    },

    // ==================== REAL-TIME ====================
    addBusIcon(on_route_trip) {
      if (!this.hasPosition(on_route_trip)) return false;
      if (this.markers.find(item => item.place_id === on_route_trip.channel)) return false;
      const position = {
        lat: parseFloat(on_route_trip.last_position_lat),
        lng: parseFloat(on_route_trip.last_position_lng),
      };
      let infoText = this.getTripInfoText(on_route_trip);
      let marker = {
        place_id: on_route_trip.channel,
        position: position,
        infoText: infoText,
        icon: "https://cdn-icons-png.flaticon.com/32/3471/3471521.png",
        heading: on_route_trip.heading || null,
      };
      this.markers.push(marker);
      return true;
    },

    hasPosition(trip) {
      if (!trip) return false;
      const lat = parseFloat(trip.last_position_lat);
      const lng = parseFloat(trip.last_position_lng);
      return Number.isFinite(lat) && Number.isFinite(lng);
    },

    getTripInfoText(on_route_trip, speed = null) {
      let infoText = "";
      if (on_route_trip.driver) {
        infoText += "<b>Driver:</b> " + on_route_trip.driver.name + "<br/>";
      }
      if (on_route_trip.trip && on_route_trip.trip.route) {
        infoText += "<b>Route:</b> " + on_route_trip.trip.route.name + "<br/>";
      }
      if (speed) {
        infoText += "<b>Speed:</b> " + speed + " km/h<br/>";
      }
      return infoText;
    },

    refreshTripPositions() {
      axios.get('/planned-trips/on-route').then((response) => {
        const running = response.data.running || [];
        this.on_route_trips = running;

        const runningChannels = running.map(t => t.channel);
        this.markers = this.markers.filter(m => runningChannels.includes(m.place_id));

        running.forEach(trip => {
          if (!this.hasPosition(trip)) return;
          const lat = parseFloat(trip.last_position_lat);
          const lng = parseFloat(trip.last_position_lng);
          let marker = this.markers.find(item => item.place_id === trip.channel);
          if (!marker) {
            this.addBusIcon(trip);
            return;
          }
          const prevLat = marker.position ? marker.position.lat : null;
          const prevLng = marker.position ? marker.position.lng : null;
          if (prevLat === lat && prevLng === lng) return;
          marker.position = { lat, lng };
          marker.infoText = this.getTripInfoText(trip);
          if (this.selectedItem === trip.channel) this.center = marker.position;
        });
      }).catch((error) => {
        console.error('Gagal refresh posisi bus:', error);
      });
    },

    fetchOnRouteTrips() {
      this.submiting = true;
      axios
        .get('/planned-trips/on-route')
        .then((response) => {
          this.submiting = false;
          this.on_route_trips = response.data.running;
          if (this.on_route_trips.length > 0) {
            for (let index = 0; index < this.on_route_trips.length; index++) {
              this.addBusIcon(this.on_route_trips[index]);
            }
            if (this.realtimeAvailable) this.listenToChannels();
          }
        })
        .catch((error) => {
          this.submiting = false;
          const message = error.response && error.response.data && error.response.data.message;
          this.$notify({
            title: "Error",
            text: message || "Data live tracking tidak dapat dimuat.",
            type: "error",
          });
        });
    },

    listenToChannels() {
      if (!this.realtimeAvailable) return;
      for (let index = 0; index < this.on_route_trips.length; index++) {
        this.listenToChannel(index, this.on_route_trips[index]);
      }
    },

    listenToChannel(index, trip) {
      if (!window.Echo || typeof window.Echo.channel !== 'function') return;
      window.Echo.channel(trip.channel).listen("App.Events.TripPositionUpdated", (e) => {
        let data = JSON.parse(e.data);
        let lat = parseFloat(data.lat);
        let lng = parseFloat(data.lng);
        let speed = Math.round(data.speed * 100) / 100;
        let position = { lat, lng };

        let marker = this.markers.find(item => item.place_id === trip.channel);
        if (!marker) {
          trip.last_position_lat = lat;
          trip.last_position_lng = lng;
          this.addBusIcon(trip);
          marker = this.markers.find(item => item.place_id === trip.channel);
        }
        if (!marker) return;
        marker.position = position;
        marker.infoText = this.getTripInfoText(trip, speed);
        if (this.selectedItem === trip.channel) {
          this.center = position;
        }
      });
    },

    // ==================== HISTORY ====================
    async fetchActiveDrivers() {
      try {
        const response = await axios.get('/tracking/drivers/active');
        this.activeDrivers = response.data;
      } catch (error) {
        console.error('Failed to fetch active drivers:', error);
      }
    },

    async fetchDriverTrips(driverId) {
      try {
        const response = await axios.get(`/tracking/drivers/${driverId}/trips`);
        this.driverTrips = response.data.map(trip => ({
          ...trip,
          routeName: trip.route?.name || `Trip #${trip.id}`,
        }));
      } catch (error) {
        console.error('Failed to fetch driver trips:', error);
      }
    },

    async fetchHistoryData() {
      if (!this.historyFilter.tripId) {
        this.$notify({ title: 'Error', text: 'Pilih trip terlebih dahulu', type: 'error' });
        return;
      }

      this.loadingHistory = true;
      try {
        const params = {
          planned_trip_id: this.historyFilter.tripId,
          start_date: this.historyFilter.startDate,
        };
        if (this.historyFilter.endDate) params.end_date = this.historyFilter.endDate;
        if (this.historyFilter.driverId) params.driver_id = this.historyFilter.driverId;

        const response = await axios.get('/tracking-logs/playback', { params });
        this.playbackLogs = response.data.logs;
        this.playbackSummary = response.data.summary;

        // Build history markers and polyline
        this.buildHistoryMap();

        this.$notify({ title: 'Success', text: `${this.playbackLogs.length} titik GPS dimuat`, type: 'success' });
      } catch (error) {
        this.$notify({ title: 'Error', text: 'Gagal memuat riwayat perjalanan', type: 'error' });
      } finally {
        this.loadingHistory = false;
      }
    },

    buildHistoryMap() {
      this.historyMarkers = [];
      this.historyPolylines = [];

      if (this.playbackLogs.length === 0) return;

      // Add start marker
      const firstLog = this.playbackLogs[0];
      this.historyMarkers.push({
        place_id: 'start',
        position: { lat: parseFloat(firstLog.latitude), lng: parseFloat(firstLog.longitude) },
        infoText: '<b>Start</b>',
        icon: 'https://cdn-icons-png.flaticon.com/32/190/190411.png',
      });

      // Add end marker
      const lastLog = this.playbackLogs[this.playbackLogs.length - 1];
      this.historyMarkers.push({
        place_id: 'end',
        position: { lat: parseFloat(lastLog.latitude), lng: parseFloat(lastLog.longitude) },
        infoText: '<b>End</b>',
        icon: 'https://cdn-icons-png.flaticon.com/32/190/190411.png',
      });

      // Add current playback marker
      this.historyMarkers.push({
        place_id: 'current',
        position: { lat: parseFloat(firstLog.latitude), lng: parseFloat(firstLog.longitude) },
        infoText: '<b>Posisi Saat Ini</b>',
        icon: 'https://cdn-icons-png.flaticon.com/32/3471/3471521.png',
      });

      // Build polyline
      const path = this.playbackLogs.map(log => ({
        lat: parseFloat(log.latitude),
        lng: parseFloat(log.longitude),
      }));

      this.historyPolylines = [{ data: path, strokeColor: '#7c3aed', weight: 4 }];

      // Center map
      this.historyCenter = {
        lat: parseFloat(firstLog.latitude),
        lng: parseFloat(firstLog.longitude),
      };
    },

    onPlaybackPositionChange(position) {
      const marker = this.historyMarkers.find(m => m.place_id === 'current');
      if (marker) {
        marker.position = { lat: position.lat, lng: position.lng };
      }
      this.historyCenter = { lat: position.lat, lng: position.lng };
    },

    // ==================== ALERTS ====================
    async fetchAlerts() {
      try {
        const [activeResponse, logResponse] = await Promise.all([
          axios.get('/gps-alerts'),
          axios.get('/gps-alerts/log'),
        ]);
        this.activeAlerts = activeResponse.data;
        this.alertLog = logResponse.data.data || [];
      } catch (error) {
        console.error('Failed to fetch alerts:', error);
      }
    },

    listenToAlertChannel() {
      if (!window.Echo || typeof window.Echo.channel !== 'function') return;
      window.Echo.channel('gps-alerts').listen('.GpsAlertCreated', (e) => {
        // Add to active alerts
        this.activeAlerts.unshift({
          id: Date.now(),
          alert_type: e.alertType,
          message: e.message,
          driver: { name: 'Driver' },
          created_at: e.timestamp,
        });

        // Play sound
        this.playAlertForType(e.alertType);

        // Send browser notification
        sendGpsAlertNotification(e.alertType, 'Driver', e.message);

        // Show toast
        this.$notify({
          title: '⚠️ GPS Alert',
          text: e.message,
          type: 'warning',
          duration: 10000,
        });
      });
    },

    playAlertForType(type) {
      switch (type) {
        case 'gps_offline':
          playCriticalSound();
          break;
        case 'out_of_route':
          playWarningSound();
          break;
        case 'speed_exceeded':
          playAlertSound();
          break;
        case 'arrived_at_depot':
          playSuccessSound();
          break;
        default:
          playAlertSound();
      }
    },

    async dismissAlert(id) {
      try {
        await axios.post(`/gps-alerts/${id}/dismiss`);
        this.activeAlerts = this.activeAlerts.filter(a => a.id !== id);
        this.$notify({ title: 'Success', text: 'Alert berhasil di-dismiss', type: 'success' });
      } catch (error) {
        this.$notify({ title: 'Error', text: 'Gagal dismiss alert', type: 'error' });
      }
    },

    getAlertColor(type) {
      const colors = {
        gps_offline: '#f44336',
        out_of_route: '#ff9800',
        speed_exceeded: '#ff5722',
        arrived_at_depot: '#4caf50',
      };
      return colors[type] || '#9e9e9e';
    },

    getAlertIcon(type) {
      const icons = {
        gps_offline: 'mdi-wifi-off',
        out_of_route: 'mdi-map-marker-off',
        speed_exceeded: 'mdi-speedometer',
        arrived_at_depot: 'mdi-home-map-marker',
      };
      return icons[type] || 'mdi-alert';
    },

    getAlertTypeLabel(type) {
      const labels = {
        gps_offline: 'GPS Offline',
        out_of_route: 'Keluar Jalur',
        speed_exceeded: 'Kecepatan Berlebih',
        arrived_at_depot: 'Tiba di Pool',
      };
      return labels[type] || 'Unknown';
    },

    // ==================== EXPORT ====================
    exportHistory() {
      this.exportFilter = { ...this.historyFilter };
      this.showExportDialog = true;
    },

    async exportData() {
      this.exporting = true;
      try {
        const params = {
          format: 'csv',
          start_date: this.exportFilter.startDate,
        };
        if (this.exportFilter.endDate) params.end_date = this.exportFilter.endDate;
        if (this.exportFilter.driverId) params.driver_id = this.exportFilter.driverId;
        if (this.exportFilter.tripId) params.planned_trip_id = this.exportFilter.tripId;

        const response = await axios.get('/tracking-logs/export', {
          params,
          responseType: 'blob',
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `gps_tracking_logs_${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        link.remove();

        this.showExportDialog = false;
        this.$notify({ title: 'Success', text: 'File berhasil didownload', type: 'success' });
      } catch (error) {
        this.$notify({ title: 'Error', text: 'Gagal export data', type: 'error' });
      } finally {
        this.exporting = false;
      }
    },

    // ==================== UTILS ====================
    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
  },
};
</script>

<style>
.flip-list-move { transition: transform 0.5s; }
.no-move { transition: transform 0s; }
.ghost { opacity: 0.5; background: #c8ebfb; }
.list-group { min-height: 20px; }
.list-group-item { cursor: pointer; }
.list-group-item i { cursor: pointer; }
.v-application ul { padding-left: 12px !important; }
</style>

<style lang="scss">
.active-stop { background: rgba($primary-shade--light, 0.15) !important; }
.badge-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 10px;
  background-color: #f44336;
  color: white;
  font-size: 11px;
  font-weight: bold;
}
</style>
