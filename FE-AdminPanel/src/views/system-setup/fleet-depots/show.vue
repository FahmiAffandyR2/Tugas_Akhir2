<template>
  <div>
    <v-breadcrumbs :items="breadcrumbs" class="mb-2" />
    <v-skeleton-loader v-if="loading" type="article, table" />
    <template v-else-if="depot">
      <v-row>
        <v-col cols="12" lg="4">
          <v-card class="rounded-xl">
            <v-card-title>
              <v-icon color="primary" class="mr-2">mdi-garage-variant</v-icon>
              {{ depot.name }}
              <v-spacer />
              <v-chip small :color="depot.is_active ? 'success' : 'grey'" dark>
                {{ depot.is_active ? 'Aktif' : 'Nonaktif' }}
              </v-chip>
            </v-card-title>
            <v-card-text>
              <v-list dense>
                <v-list-item>
                  <v-list-item-icon><v-icon small>mdi-city</v-icon></v-list-item-icon>
                  <v-list-item-content><v-list-item-subtitle>Kota</v-list-item-subtitle><v-list-item-title>{{ depot.city }}</v-list-item-title></v-list-item-content>
                </v-list-item>
                <v-list-item v-if="depot.address">
                  <v-list-item-icon><v-icon small>mdi-map-marker</v-icon></v-list-item-icon>
                  <v-list-item-content><v-list-item-subtitle>Alamat</v-list-item-subtitle><v-list-item-title>{{ depot.address }}</v-list-item-title></v-list-item-content>
                </v-list-item>
                <v-list-item v-if="depot.contact_name">
                  <v-list-item-icon><v-icon small>mdi-account</v-icon></v-list-item-icon>
                  <v-list-item-content><v-list-item-subtitle>Penanggung Jawab</v-list-item-subtitle><v-list-item-title>{{ depot.contact_name }}</v-list-item-title></v-list-item-content>
                </v-list-item>
                <v-list-item v-if="depot.contact_phone">
                  <v-list-item-icon><v-icon small>mdi-phone</v-icon></v-list-item-icon>
                  <v-list-item-content><v-list-item-subtitle>Telepon</v-list-item-subtitle><v-list-item-title>{{ depot.contact_phone }}</v-list-item-title></v-list-item-content>
                </v-list-item>
                <v-list-item>
                  <v-list-item-icon><v-icon small>mdi-radar</v-icon></v-list-item-icon>
                  <v-list-item-content><v-list-item-subtitle>Geofence Radius</v-list-item-subtitle><v-list-item-title>{{ depot.geofence_radius || 500 }} meter</v-list-item-title></v-list-item-content>
                </v-list-item>
                <v-list-item>
                  <v-list-item-icon><v-icon small>mdi-crosshairs-gps</v-icon></v-list-item-icon>
                  <v-list-item-content><v-list-item-subtitle>Koordinat</v-list-item-subtitle><v-list-item-title>{{ depot.latitude }}, {{ depot.longitude }}</v-list-item-title></v-list-item-content>
                </v-list-item>
              </v-list>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" lg="8">
          <v-card class="rounded-xl overflow-hidden">
            <v-card-title>Lokasi Depo</v-card-title>
            <leaflet-map-loader
              :center="{ lat: Number(depot.latitude), lng: Number(depot.longitude) }"
              :zoom="13"
              :markers="mapMarkers"
              class="depot-detail-map"
            />
          </v-card>
        </v-col>
      </v-row>

      <v-card class="mt-4 rounded-xl">
        <v-card-title>
          <v-icon color="primary" class="mr-2">mdi-bus-multiple</v-icon>
          Armada Bus ({{ depot.buses.length }})
          <v-spacer />
          <v-chip small class="mr-2" color="success" outlined>
            <v-icon left small>mdi-check-circle</v-icon>
            {{ depot.available_buses_count }} tersedia
          </v-chip>
          <v-chip small class="mr-2" color="warning" outlined>
            <v-icon left small>mdi-road-variant</v-icon>
            {{ depot.on_trip_buses_count }} di perjalanan
          </v-chip>
          <v-chip small color="error" outlined>
            <v-icon left small>mdi-close-circle</v-icon>
            {{ depot.inactive_buses_count }} tidak aktif
          </v-chip>
        </v-card-title>
        <v-card-text>
          <v-data-table
            :headers="headers"
            :items="depot.buses"
            :search="search"
            :loading="loading"
            class="elevation-0"
          >
            <template v-slot:top>
              <v-text-field v-model="search" outlined dense hide-details prepend-inner-icon="mdi-magnify" label="Cari bus..." class="mx-4 mb-4" style="max-width:320px" />
            </template>
            <template v-slot:item.fleet_number="{ item }">
              <span class="font-weight-bold">#{{ item.fleet_number }}</span>
            </template>
            <template v-slot:item.bus_type="{ item }">
              <v-chip small outlined>{{ item.bus_type ? item.bus_type.name : '-' }}</v-chip>
            </template>
            <template v-slot:item.status="{ item }">
              <v-chip v-if="!item.is_active" color="error" small dark>
                <v-icon left small>mdi-close-circle</v-icon>
                Tidak Aktif
              </v-chip>
              <v-chip v-else-if="item.status === 'on_trip'" color="warning" small dark>
                <v-icon left small>mdi-road-variant</v-icon>
                Di Perjalanan
              </v-chip>
              <v-chip v-else color="success" small dark>
                <v-icon left small>mdi-check-circle</v-icon>
                Tersedia
              </v-chip>
            </template>
            <template v-slot:item.is_active="{ item }">
              <v-icon :color="item.is_active ? 'success' : 'grey'" small>
                {{ item.is_active ? 'mdi-check-circle' : 'mdi-close-circle' }}
              </v-icon>
            </template>
            <template v-slot:item.last_gps="{ item }">
              <template v-if="item.last_gps_at">
                <div class="caption">{{ formatDateTime(item.last_gps_at) }}</div>
                <div v-if="item.current_speed" class="caption grey--text">{{ item.current_speed }} km/h</div>
              </template>
              <span v-else class="grey--text caption">-</span>
            </template>
            <template v-slot:item.actions="{ item }">
              <v-icon small class="mr-1" @click="goToBusEdit(item)" title="Edit bus">mdi-pencil</v-icon>
            </template>
          </v-data-table>
        </v-card-text>
      </v-card>
    </template>


  </div>
</template>

<script>
import LeafletMapLoader from '@/components/LeafletMapLoader.vue'
export default {
  components: { LeafletMapLoader },
  data() {
    return {
      depot: null,
      loading: true,
      search: '',
      driverDialog: false,
      driverSearch: '',
      loadingDrivers: false,
      selectedBus: null,
      availableDrivers: [],
      headers: [
        { text: 'No. Armada', value: 'fleet_number' },
        { text: 'Plat Nomor', value: 'license' },
        { text: 'Kategori', value: 'bus_type', sortable: false },
        { text: 'Kapasitas', value: 'capacity' },
        { text: 'Status', value: 'status' },
        { text: 'Aktif', value: 'is_active', align: 'center' },
        { text: 'GPS Terakhir', value: 'last_gps', sortable: false },
        { text: 'Aksi', value: 'actions', sortable: false, align: 'right' },
      ],
      required: [v => !!v || 'Wajib diisi'],
    }
  },
  computed: {
    breadcrumbs() {
      return [
        { text: 'Depo Armada', to: '/fleet-depots', exact: true },
        { text: this.depot ? this.depot.name : 'Detail' },
      ]
    },
    mapMarkers() {
      if (!this.depot) return []
      return [{
        place_id: `depot-${this.depot.id}`,
        position: { lat: Number(this.depot.latitude), lng: Number(this.depot.longitude) },
        infoText: `<strong>${this.depot.name}</strong><br>${this.depot.city}<br>${this.depot.buses.length} bus`,
      }]
    },
    filteredDrivers() {
      if (!this.driverSearch) return this.availableDrivers
      const q = this.driverSearch.toLowerCase()
      return this.availableDrivers.filter(d => `${d.name} ${d.email}`.toLowerCase().includes(q))
    },
  },
  created() {
    this.loadDepot()
  },
  methods: {
    async loadDepot() {
      this.loading = true
      try {
        const id = this.$route.params.depot_id
        const r = await axios.get(`/fleet-depots/${id}`)
        this.depot = r.data.depot
      } catch (e) {
        this.$notify({ type: 'error', title: 'Gagal', text: 'Data depo tidak dapat dimuat.' })
        this.$router.push('/fleet-depots')
      } finally {
        this.loading = false
      }
    },
    formatDate(d) {
      if (!d) return '-'
      return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    },
    formatDateTime(d) {
      if (!d) return '-'
      return new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    },
    goToBusEdit(bus) {
      this.$router.push('/buses')
    },
    async assignDriver(bus) {
      this.selectedBus = bus
      this.driverSearch = ''
      this.driverDialog = true
      this.loadingDrivers = true
      try {
        const r = await axios.get('/buses/available-drivers')
        this.availableDrivers = r.data.drivers || r.data || []
      } catch (e) {
        this.$notify({ type: 'error', title: 'Gagal', text: 'Daftar driver tidak dapat dimuat.' })
      } finally {
        this.loadingDrivers = false
      }
    },
    async confirmAssignDriver(driver) {
      if (!this.selectedBus) return
      try {
        await axios.post('/buses/assign-driver', {
          bus_id: this.selectedBus.id,
          driver_id: driver.id,
        })
        this.$notify({ type: 'success', title: 'Berhasil', text: `${driver.name} ditugaskan ke ${this.selectedBus.license}` })
        this.driverDialog = false
        await this.loadDepot()
      } catch (e) {
        const msg = e.response && e.response.data && e.response.data.message
        this.$notify({ type: 'error', title: 'Gagal', text: msg || 'Gagal menugaskan driver.' })
      }
    },
    async unassignDriver(bus) {
      const result = await this.$swal.fire({
        title: 'Lepas driver?',
        text: `Driver ${bus.driver.name} akan dilepas dari bus ${bus.license}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Lepas',
        cancelButtonText: 'Batal',
      })
      if (!result.isConfirmed) return
      try {
        await axios.post('/buses/unassign-driver', { bus_id: bus.id })
        this.$notify({ type: 'success', title: 'Berhasil', text: `Driver dilepas dari ${bus.license}` })
        await this.loadDepot()
      } catch (e) {
        const msg = e.response && e.response.data && e.response.data.message
        this.$notify({ type: 'error', title: 'Gagal', text: msg || 'Gagal melepas driver.' })
      }
    },
  },
}
</script>

<style scoped>
.depot-detail-map ::v-deep .leaflet-map { height: 300px; }
</style>
