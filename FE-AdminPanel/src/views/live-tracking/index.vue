<template>
  <v-card class="tracking-card">
    <v-card-title class="flex-wrap">
      <span>Live Tracking Bus</span>
      <v-spacer />
      <v-chip small color="primary" outlined class="ma-2">{{ buses.length }} bus dalam perjalanan</v-chip>
      <v-btn text color="primary" :loading="loading" @click="refresh"><v-icon left small>mdi-refresh</v-icon>Perbarui</v-btn>
    </v-card-title>
    <v-card-text>
      <p class="mb-3">Lokasi seluruh bus yang sedang dalam perjalanan. Diperbarui otomatis setiap 10 detik.</p>
      <v-alert v-if="error" type="warning" text dense>{{ error }}</v-alert>
      <v-alert v-else-if="!loading && !buses.length" type="info" text dense>Belum ada bus yang sedang dalam perjalanan.</v-alert>
      <v-alert v-if="missingGps" type="info" text dense>{{ missingGps }} bus masih menunggu lokasi GPS dan belum dapat ditampilkan pada peta.</v-alert>
      <div class="tracking-map">
        <leaflet-map-loader ref="map" :enabled="false" :center="center" :zoom="11" :markers="markers" :animate="true" />
      </div>
    </v-card-text>
  </v-card>
</template>
<script>
import axios from 'axios'
import LeafletMapLoader from '@/components/LeafletMapLoader.vue'
import { runningBuses, busMarkers } from '@/utils/liveBusTracking'
export default {
  components: { LeafletMapLoader },
  data: () => ({ buses: [], markers: [], loading: false, error: '', timer: null, fitted: false, requestController: null, center: { lat: Number(process.env.VUE_APP_ORIGIN_LAT) || -6.2, lng: Number(process.env.VUE_APP_ORIGIN_LNG) || 106.8 } }),
  computed: { missingGps() { return this.buses.filter(bus => !bus.position).length } },
  mounted() {
    this.refresh()
    this.timer = window.setInterval(() => { if (!document.hidden) this.refresh() }, 10000)
    document.addEventListener('visibilitychange', this.onVisibilityChange)
  },
  beforeDestroy() {
    window.clearInterval(this.timer)
    document.removeEventListener('visibilitychange', this.onVisibilityChange)
    if (this.requestController) this.requestController.abort()
  },
  methods: {
    onVisibilityChange() { if (!document.hidden) this.refresh() },
    async refresh() {
      if (this.loading) return
      this.loading = true
      this.requestController = new AbortController()
      try {
        const response = await axios.get('/planned-trips/on-route', { signal: this.requestController.signal, timeout: 15000 })
        if (this._isDestroyed) return
        this.buses = runningBuses(response.data.running || [])
        this.markers = busMarkers(this.buses)
        this.error = ''
        if (!this.markers.length) this.fitted = false
        if (this.markers.length && !this.fitted) {
          this.center = this.markers[0].position
          this.$nextTick(() => { if (this.$refs.map) this.$refs.map.fitBounds() })
          this.fitted = true
        }
      } catch (error) {
        if (this._isDestroyed || axios.isCancel(error)) return
        this.error = 'Lokasi bus gagal diperbarui. Peta menampilkan data terakhir yang berhasil dimuat.'
      } finally {
        if (!this._isDestroyed) this.loading = false
      }
    },
  },
}
</script>
<style scoped>
.tracking-card{border-radius:16px}.tracking-map{min-height:480px}.tracking-map ::v-deep .leaflet-map{height:calc(100vh - 290px);min-height:480px}@media(max-width:600px){.tracking-map,.tracking-map ::v-deep .leaflet-map{min-height:360px}}
</style>
