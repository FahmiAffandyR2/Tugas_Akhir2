<template>
  <div>
    <v-alert v-if="gpsMessage" :type="gpsMessage.type" dismissible>{{ gpsMessage.text }}</v-alert>
    <v-alert v-if="queueCount > 0" type="warning" dense class="mb-3">
      <v-icon small class="mr-1">mdi-cloud-upload-outline</v-icon>
      {{ queueCount }} lokasi GPS menunggu pengiriman saat online kembali.
    </v-alert>

    <v-dialog v-model="gpsCheckDialog" max-width="420" persistent>
      <v-card class="gps-check-card">
        <v-card-title class="d-flex align-center pa-5">
          <v-avatar color="primary" size="42" class="mr-3"><v-icon dark>mdi-crosshairs-gps</v-icon></v-avatar>
          <div><div class="text-h6 font-weight-bold">Cek GPS</div><div class="caption grey--text">Pastikan GPS siap sebelum perjalanan</div></div>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-5">
          <div v-for="(step, i) in gpsCheckSteps" :key="i" class="d-flex align-start mb-4">
            <v-avatar :color="step.status === 'done' ? 'success' : step.status === 'active' ? 'primary' : 'grey lighten-2'" size="28" class="mr-3 mt-1 flex-shrink-0">
              <v-icon v-if="step.status === 'done'" x-small dark>mdi-check</v-icon>
              <v-icon v-else-if="step.status === 'error'" x-small dark color="error">mdi-close</v-icon>
              <span v-else class="caption font-weight-bold white--text">{{ i + 1 }}</span>
            </v-avatar>
            <div class="flex-grow-1">
              <div class="font-weight-medium" :class="{ 'success--text': step.status === 'done', 'error--text': step.status === 'error' }">{{ step.title }}</div>
              <div class="caption grey--text mt-1">{{ step.description }}</div>
              <v-alert v-if="step.status === 'error' && step.help" type="warning" dense text class="mt-2 mb-0 caption">
                {{ step.help }}
              </v-alert>
            </div>
          </div>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn text @click="cancelGpsCheck" :disabled="gpsChecking">Batal</v-btn>
          <v-btn v-if="gpsCheckFailed" color="primary" @click="retryGpsCheck" :loading="gpsChecking">
            <v-icon left small>mdi-refresh</v-icon>Coba Lagi
          </v-btn>
          <v-btn v-else color="success" :disabled="!gpsCheckPassed || gpsChecking" :loading="gpsChecking" @click="confirmGpsCheck">
            <v-icon left small>mdi-check-circle</v-icon>Mulai Perjalanan
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <div class="page-heading d-flex align-center mb-6">
      <div><h1 class="text-h5 font-weight-bold mb-1">{{ pageTitle }}</h1><p class="grey--text mb-0">{{ pageSubtitle }}</p></div>
      <v-spacer/><v-chip color="primary" outlined><v-icon left small>mdi-bus-clock</v-icon>{{ filteredTrips.length }} perjalanan</v-chip>
    </div>
    <v-skeleton-loader v-if="loading" type="card, card" />
    <v-card v-else-if="filteredTrips.length === 0" flat class="empty-state text-center pa-8 pa-md-12">
      <div class="empty-icon mx-auto mb-5"><v-icon size="64" color="primary">{{ emptyIcon }}</v-icon></div>
      <h2 class="text-h6 font-weight-bold mb-2">{{ emptyTitle }}</h2>
      <p class="grey--text mx-auto empty-copy">{{ emptyText }}</p>
      <v-btn v-if="filter === 'schedule'" outlined color="primary" class="mt-2" @click="loadTrips"><v-icon left>mdi-refresh</v-icon>Perbarui Jadwal</v-btn>
      <v-btn v-else-if="filter === 'active'" color="primary" class="mt-2" to="/driver/jadwal"><v-icon left>mdi-calendar-clock</v-icon>Lihat Jadwal</v-btn>
    </v-card>
    <div v-else>
      <v-card v-if="filter === 'active' && activeTrip" flat class="navigation-card mb-6 overflow-hidden">
        <div class="navigation-header pa-4 pa-md-5 d-flex flex-wrap align-center">
          <div><div class="caption text-uppercase font-weight-bold primary--text mb-1">Navigasi perjalanan</div><h2 class="text-h6 font-weight-bold mb-0">{{ activeTrip.route ? activeTrip.route.name : 'Rute perjalanan' }}</h2></div>
          <v-spacer />
          <div class="d-flex align-center mt-3 mt-sm-0">
            <v-chip small :color="gpsStatusColor" dark class="mr-2">
              <v-icon left small>{{ gpsStatusIcon }}</v-icon>{{ gpsStatusLabel }}
            </v-chip>
            <div class="navigation-metrics d-flex">
              <div class="metric px-4"><div class="caption grey--text">Jarak tersisa</div><strong>{{ remainingDistanceLabel }}</strong></div>
              <div class="metric px-4"><div class="caption grey--text">Estimasi tiba</div><strong>{{ etaLabel }}</strong></div>
            </div>
          </div>
        </div>
        <v-row no-gutters>
          <v-col cols="12" lg="8" class="map-column">
            <LeafletMapLoader :center="mapCenter" :zoom="12" :markers="navigationMarkers" :polylines="navigationPolylines" :enabled="false" />
            <div v-if="!currentPosition" class="map-waiting pa-3"><v-icon small color="warning" class="mr-2">mdi-crosshairs-question</v-icon>Menunggu posisi GPS perangkat</div>
          </v-col>
          <v-col cols="12" lg="4" class="stops-column pa-5">
            <div v-if="nextStop" class="next-stop-card pa-3 mb-4">
              <div class="caption text-uppercase font-weight-bold primary--text mb-1">Arah driver ke titik jemput</div>
              <div class="font-weight-bold">{{ nextStop.name || 'Titik jemput berikutnya' }}</div>
              <div v-if="nextStopDistanceLabel" class="caption grey--text mt-1">{{ nextStopDistanceLabel }} dari posisi driver</div>
            </div>
            <div class="d-flex align-center mb-4"><v-icon color="primary" class="mr-2">mdi-map-marker-path</v-icon><span class="font-weight-bold">Titik pemberhentian</span></div>
            <div v-for="(stop,index) in routeStops" :key="stop.id" class="stop-item d-flex">
              <div class="stop-track mr-3"><span :class="['stop-dot', {destination:index===routeStops.length-1}]" /><span v-if="index<routeStops.length-1" class="stop-line" /></div>
              <div class="pb-5"><div class="font-weight-bold" :class="{ 'primary--text': nextStop && nextStop.id === stop.id }">{{ stop.name || `Stop ${index+1}` }}</div><div class="caption grey--text">{{ index===0?'Titik awal':index===routeStops.length-1?'Tujuan akhir':`Pemberhentian ${index+1}` }}</div><div v-if="stop.address" class="caption stop-address mt-1">{{ stop.address }}</div></div>
            </div>
            <v-alert v-if="routeStops.length===0" type="warning" text dense>Daftar pemberhentian belum tersedia pada rute ini.</v-alert>
          </v-col>
        </v-row>
      </v-card>
      <v-row>
      <v-col v-for="trip in filteredTrips" :key="trip.id" cols="12" md="6">
        <v-card flat class="trip-card overflow-hidden">
          <div class="card-accent" :class="status(trip).className" />
          <v-card-title class="d-flex pt-5"><div class="route-symbol mr-3"><v-icon color="primary">mdi-bus-marker</v-icon></div><span class="route-title">{{ trip.route ? trip.route.name : 'Perjalanan #' + trip.id }}</span><v-spacer/><v-chip small :color="status(trip).color" dark>{{ status(trip).label }}</v-chip></v-card-title>
          <v-card-text class="pt-3">
            <div class="info-row mb-3"><v-icon small class="mr-2">mdi-calendar-blank-outline</v-icon><span>{{ formatDate(trip.planned_date) }}</span></div>
            <div class="info-row mb-3"><v-icon small class="mr-2">mdi-bus-side</v-icon><span>{{ busName(trip) }}</span></div>
            <div v-if="isActive(trip)" class="gps-banner pa-3 mt-4"><span class="gps-pulse mr-2"/><span>GPS aktif dan lokasi dikirim ke Super Admin</span></div>
          </v-card-text>
          <v-card-actions class="px-4 pb-5">
            <v-btn v-if="isScheduled(trip)" block large color="primary" class="action-btn" :loading="actionId === trip.id" @click="startTrip(trip)"><v-icon left>mdi-play-circle-outline</v-icon>Mulai Perjalanan</v-btn>
            <v-btn v-if="isActive(trip)" block large color="error" class="action-btn" :loading="actionId === trip.id" @click="completeTrip(trip)"><v-icon left>mdi-flag-checkered</v-icon>Selesai Perjalanan</v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
      </v-row>
    </div>
  </div>
</template>

<script>
import LeafletMapLoader from '@/components/LeafletMapLoader.vue'
import GpsTrackingService from '@/services/GpsTrackingService'

export default {
  components: { LeafletMapLoader },
  data: () => ({
    trips: [],
    loading: false,
    actionId: null,
    currentPosition: null,
    gpsMessage: null,
    gpsServiceStatus: 'stopped',
    queueCount: 0,
    driverToNextStopRoutePath: [],
    routeRequestTimeout: null,
    routeRequestId: 0,
    lastRouteRequestKey: null,
    removePositionListener: null,
    removeStatusListener: null,
    gpsCheckDialog: false,
    gpsChecking: false,
    gpsCheckSteps: [
      { title: 'Browser mendukung GPS', description: 'Memeriksa perangkat...', status: 'pending', help: '' },
      { title: 'Izin lokasi diberikan', description: 'Menunggu izin dari browser...', status: 'pending', help: 'Klik "Izinkan" saat browser meminta akses lokasi.' },
      { title: 'GPS perangkat aktif', description: 'Memverifikasi akurasi GPS...', status: 'pending', help: 'Aktifkan GPS di pengaturan HP Anda (Settings → Location → On).' },
    ],
    gpsCheckPassed: false,
    gpsCheckFailed: false,
    pendingTrip: null,
  }),
  computed: {
    filter() { return this.$route.meta.tripFilter },
    filteredTrips() { return this.trips.filter(t => this.filter === 'active' ? this.isActive(t) : this.filter === 'history' ? !!t.ended_at : this.isScheduled(t)) },
    pageTitle() { return { schedule: 'Jadwal Saya', active: 'Perjalanan Aktif', history: 'Riwayat Perjalanan' }[this.filter] },
    pageSubtitle() { return this.filter === 'active' ? 'Lokasi GPS akan dikirim ke Super Admin.' : this.filter === 'history' ? 'Perjalanan yang sudah diselesaikan.' : 'Jadwal perjalanan yang ditugaskan kepada Anda.' },
    emptyIcon() { return { schedule: 'mdi-calendar-blank-outline', active: 'mdi-bus-alert', history: 'mdi-history' }[this.filter] },
    emptyTitle() { return { schedule: 'Belum ada jadwal perjalanan', active: 'Tidak ada perjalanan aktif', history: 'Belum ada riwayat perjalanan' }[this.filter] },
    emptyText() { return { schedule: 'Jadwal yang ditugaskan Super Admin akan muncul di sini. Tekan perbarui untuk memeriksa tugas terbaru.', active: 'Mulai salah satu jadwal Anda untuk mengaktifkan GPS dan perjalanan.', history: 'Perjalanan yang telah diselesaikan akan tersimpan dan muncul di halaman ini.' }[this.filter] },
    activeTrip() { return this.trips.find(this.isActive) || null },
    routeStops() { return this.activeTrip && Array.isArray(this.activeTrip.route_stops) ? this.activeTrip.route_stops : [] },
    routePath() { return this.activeTrip && Array.isArray(this.activeTrip.route_path) ? this.activeTrip.route_path.map(p => ({ lat:Number(p.lat), lng:Number(p.lng) })).filter(p => Number.isFinite(p.lat)&&Number.isFinite(p.lng)) : [] },
    tripDetails() {
      if (!this.activeTrip) return []
      const details = this.activeTrip.planned_trip_detail || this.activeTrip.plannedTripDetail || []
      return Array.isArray(details) ? details : []
    },
    nextStop() {
      const pendingDetail = this.tripDetails.find(detail => !detail.actual_timestamp && detail.stop)
      if (pendingDetail && pendingDetail.stop) {
        return {
          id: pendingDetail.stop.id,
          name: pendingDetail.stop.name,
          address: pendingDetail.stop.address,
          lat: Number(pendingDetail.stop.lat),
          lng: Number(pendingDetail.stop.lng),
        }
      }
      return this.routeStops.find(stop => Number.isFinite(Number(stop.lat)) && Number.isFinite(Number(stop.lng))) || null
    },
    nextStopDistanceLabel() {
      if (!this.currentPosition || !this.nextStop) return null
      const distance = this.driverToNextStopDistanceKm
      return distance < 1 ? `${Math.round(distance * 1000)} m` : `${distance.toFixed(1)} km`
    },
    driverToNextStopDistanceKm() {
      const path = this.driverToNextStopRoutePath.length
        ? this.driverToNextStopRoutePath
        : this.routePathToNextStop.length
          ? this.routePathToNextStop
          : this.driverToNextStopPath
      if (path.length < 2) return this.currentPosition && this.nextStop ? this.distanceKm(this.currentPosition, this.nextStop) : 0
      let total = 0
      for (let i = 0; i < path.length - 1; i++) total += this.distanceKm(path[i], path[i + 1])
      return total
    },
    destination() { return this.routeStops.length ? this.routeStops[this.routeStops.length-1] : null },
    mapCenter() { return this.currentPosition || (this.routeStops[0] ? {lat:Number(this.routeStops[0].lat),lng:Number(this.routeStops[0].lng)} : {lat:-6.2,lng:106.8}) },
    navigationMarkers() {
      const markers=this.routeStops.map((stop,index)=>({place_id:`stop-${stop.id}`,position:{lat:Number(stop.lat),lng:Number(stop.lng)},infoText:`<b>${stop.name||`Stop ${index+1}`}</b><br>${this.nextStop&&this.nextStop.id===stop.id?'Titik jemput berikutnya':index===0?'Titik awal':index===this.routeStops.length-1?'Tujuan akhir':'Pemberhentian'}`}))
      if(this.currentPosition) markers.push({place_id:'driver-position',position:this.currentPosition,infoText:'<b>Posisi Anda saat ini</b>'})
      return markers
    },
    driverToNextStopPath() { return this.currentPosition && this.nextStop ? [this.currentPosition, {lat:Number(this.nextStop.lat),lng:Number(this.nextStop.lng)}] : [] },
    routePathToNextStop() {
      if (!this.currentPosition || !this.nextStop || this.routePath.length < 2) return []

      const driverIndex = this.nearestPointIndex(this.routePath, this.currentPosition)
      const stopIndex = this.nearestPointIndex(this.routePath, this.nextStop)
      if (driverIndex === -1 || stopIndex === -1) return []

      const start = Math.min(driverIndex, stopIndex)
      const end = Math.max(driverIndex, stopIndex)
      const segment = this.routePath.slice(start, end + 1)

      if (segment.length < 2) return []
      return [
        this.currentPosition,
        ...segment,
        { lat: Number(this.nextStop.lat), lng: Number(this.nextStop.lng) },
      ]
    },
    navigationPolylines() {
      const lines = []
      if (this.routePath.length) lines.push({data:this.routePath,strokeColor:'#7c3aed',weight:5})
      const driverPath = this.driverToNextStopRoutePath.length
        ? this.driverToNextStopRoutePath
        : this.routePathToNextStop.length
          ? this.routePathToNextStop
          : this.driverToNextStopPath
      if (driverPath.length) lines.push({
        data:driverPath,
        strokeColor:'#22c55e',
        weight:4,
        dashArray:this.driverToNextStopRoutePath.length || this.routePathToNextStop.length ? null : '8 8',
        opacity:.95,
      })
      return lines
    },
    remainingDistanceKm() {
      if(!this.currentPosition||!this.destination)return null
      if(!this.routePath.length)return this.distanceKm(this.currentPosition,this.destination)
      let nearest=0,min=Infinity
      this.routePath.forEach((point,index)=>{const distance=this.distanceKm(this.currentPosition,point);if(distance<min){min=distance;nearest=index}})
      let total=min
      for(let i=nearest;i<this.routePath.length-1;i++)total+=this.distanceKm(this.routePath[i],this.routePath[i+1])
      return total
    },
    remainingDistanceLabel() { const distance=this.remainingDistanceKm;return distance===null?'Menunggu GPS':distance<1?`${Math.round(distance*1000)} m`:`${distance.toFixed(1)} km` },
    etaLabel() { const distance=this.remainingDistanceKm;if(distance===null)return '-';const minutes=Math.max(1,Math.round(distance/40*60));if(minutes<60)return `± ${minutes} menit`;return `± ${Math.floor(minutes/60)} jam ${minutes%60} mnt` },
    gpsStatusColor() {
      const colors = { tracking: 'success', sent: 'success', queued: 'warning', background: 'orange', offline: 'grey', error: 'error', stopped: 'grey', online: 'success' }
      return colors[this.gpsServiceStatus] || 'grey'
    },
    gpsStatusIcon() {
      const icons = { tracking: 'mdi-crosshairs-gps', sent: 'mdi-check-circle', queued: 'mdi-cloud-upload', background: 'mdi-sleep', offline: 'mdi-wifi-off', error: 'mdi-alert-circle', stopped: 'mdi-stop-circle', online: 'mdi-wifi' }
      return icons[this.gpsServiceStatus] || 'mdi-help-circle'
    },
    gpsStatusLabel() {
      const labels = { tracking: 'GPS aktif', sent: 'Terkirim', queued: 'Menunggu', background: 'Background', offline: 'Offline', error: 'Error GPS', stopped: 'Berhenti', online: 'Online' }
      return labels[this.gpsServiceStatus] || 'Unknown'
    },
  },
  watch: {
    '$route.meta.tripFilter'() { this.loadTrips() },
    currentPosition: {
      deep: true,
      handler() { this.scheduleDriverRoute() },
    },
    nextStop: {
      deep: true,
      handler() { this.scheduleDriverRoute() },
    },
  },
  created() {
    this.removePositionListener = GpsTrackingService.onPosition((pos) => {
      this.currentPosition = { lat: pos.lat, lng: pos.lng }
    })
    this.removeStatusListener = GpsTrackingService.onStatus((status, message) => {
      this.gpsServiceStatus = status
      if (status === 'error') {
        this.gpsMessage = { type: 'error', text: message }
      } else if (status === 'queued') {
        this.gpsMessage = { type: 'warning', text: message }
      } else if (status === 'sent') {
        this.gpsMessage = { type: 'success', text: 'Lokasi GPS berhasil dikirim ke Super Admin.' }
      }
      this._refreshQueueCount()
    })
  },
  mounted() { this.loadTrips() },
  beforeDestroy() {
    if (this.removePositionListener) this.removePositionListener()
    if (this.removeStatusListener) this.removeStatusListener()
    if (this.routeRequestTimeout) clearTimeout(this.routeRequestTimeout)
  },
  methods: {
    async loadTrips() {
      this.loading = true
      try {
        const r = await axios.get('/drivers/get-driver-trips')
        this.trips = r.data.trips || []
        this._syncTracking()
        this._refreshQueueCount()
      }
      catch (e) { this.notifyError(e, 'Jadwal tidak dapat dimuat.') }
      finally { this.loading = false }
    },
    _syncTracking() {
      const active = this.activeTrip
      if (active) {
        const lat = Number(active.last_position_lat)
        const lng = Number(active.last_position_lng)
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
          this.currentPosition = { lat, lng }
        }
        if (!GpsTrackingService.isTracking || GpsTrackingService.tripId !== active.id) {
          GpsTrackingService.start(active.id)
        }
      } else {
        if (GpsTrackingService.isTracking) {
          GpsTrackingService.stop()
        }
        this.currentPosition = null
        this.driverToNextStopRoutePath = []
      }
    },
    async _refreshQueueCount() {
      this.queueCount = await GpsTrackingService.getQueueCount()
    },
    isScheduled(t) { return !t.started_at && !t.ended_at },
    isActive(t) { return !!t.started_at && !t.ended_at },
    status(t) { return t.ended_at ? { label: 'Selesai', color: 'success', className: 'completed' } : t.started_at ? { label: 'Berjalan', color: 'orange', className: 'active' } : { label: 'Terjadwal', color: 'primary', className: 'scheduled' } },
    busName(t) { return t.bus ? (t.bus.license || t.bus.name || `Bus #${t.bus.id}`) : 'Bus belum ditentukan' },
    formatDate(value) { return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' }).format(new Date(value + 'T00:00:00')) : '-' },
    async startTrip(trip) {
      if (!navigator.geolocation) {
        this.gpsMessage = { type: 'error', text: 'Perangkat ini tidak mendukung GPS.' }
        return
      }
      this.pendingTrip = trip
      this.openGpsCheck()
    },
    openGpsCheck() {
      this.gpsCheckSteps = [
        { title: 'Browser mendukung GPS', description: 'Memeriksa perangkat...', status: 'active', help: '' },
        { title: 'Izin lokasi diberikan', description: 'Menunggu izin dari browser...', status: 'pending', help: 'Klik "Izinkan" saat browser meminta akses lokasi.' },
        { title: 'GPS perangkat aktif', description: 'Memverifikasi akurasi GPS...', status: 'pending', help: 'Aktifkan GPS di pengaturan HP Anda (Settings → Location → On).' },
      ]
      this.gpsCheckPassed = false
      this.gpsCheckFailed = false
      this.gpsCheckDialog = true
      this.runGpsCheck()
    },
    async runGpsCheck() {
      this.gpsChecking = true
      this.gpsCheckFailed = false

      this.gpsCheckSteps[0].status = 'active'
      this.gpsCheckSteps[0].description = 'Memeriksa perangkat...'

      await this.sleep(400)
      if (!navigator.geolocation) {
        this.gpsCheckSteps[0].status = 'error'
        this.gpsCheckSteps[0].description = 'Perangkat tidak mendukung GPS'
        this.gpsCheckSteps[0].help = 'Gunakan perangkat dengan GPS (HP Android/iOS).'
        this.gpsChecking = false
        this.gpsCheckFailed = true
        return
      }
      this.gpsCheckSteps[0].status = 'done'
      this.gpsCheckSteps[0].description = 'Browser mendukung GPS'

      this.gpsCheckSteps[1].status = 'active'
      this.gpsCheckSteps[1].description = 'Meminta izin lokasi...'

      try {
        const position = await new Promise((resolve, reject) => {
          navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0,
          })
        })

        this.gpsCheckSteps[1].status = 'done'
        this.gpsCheckSteps[1].description = 'Izin lokasi diberikan'

        this.gpsCheckSteps[2].status = 'active'
        this.gpsCheckSteps[2].description = 'Memverifikasi akurasi GPS...'
        await this.sleep(300)

        const accuracy = position.coords.accuracy
        if (accuracy > 100) {
          this.gpsCheckSteps[2].status = 'error'
          this.gpsCheckSteps[2].description = `Akurasi GPS rendah (${Math.round(accuracy)}m)`
          this.gpsCheckSteps[2].help = 'Aktifkan GPS di pengaturan HP untuk akurasi lebih baik (Settings → Location → High Accuracy).'
          this.gpsChecking = false
          this.gpsCheckFailed = true
          return
        }

        this.gpsCheckSteps[2].status = 'done'
        this.gpsCheckSteps[2].description = `GPS aktif (akurasi: ${Math.round(accuracy)}m)`
        this.gpsCheckPassed = true
        this.gpsChecking = false

      } catch (error) {
        let stepIdx = 1
        let errorMsg = ''
        let helpMsg = ''

        if (error.code === 1) {
          stepIdx = 1
          errorMsg = 'Izin lokasi ditolak'
          helpMsg = 'Buka Pengaturan → Aplikasi → Browser → Izin → Lokasi → Izinkan. Lalu muat ulang halaman ini.'
        } else if (error.code === 2) {
          stepIdx = 2
          errorMsg = 'GPS tidak dapat menentukan lokasi'
          helpMsg = 'Pastikan GPS/Location aktif di pengaturan HP (Settings → Location → On).'
        } else {
          stepIdx = 2
          errorMsg = 'Timeout mendapatkan lokasi'
          helpMsg = 'Pastikan GPS aktif dan Anda berada di tempat terbuka. Coba lagi.'
        }

        for (let i = 0; i <= stepIdx; i++) {
          if (i < stepIdx) {
            this.gpsCheckSteps[i].status = 'done'
          } else {
            this.gpsCheckSteps[i].status = 'error'
            this.gpsCheckSteps[i].description = errorMsg
            this.gpsCheckSteps[i].help = helpMsg
          }
        }
        this.gpsChecking = false
        this.gpsCheckFailed = true
      }
    },
    retryGpsCheck() {
      this.runGpsCheck()
    },
    cancelGpsCheck() {
      this.gpsCheckDialog = false
      this.pendingTrip = null
    },
    async confirmGpsCheck() {
      this.gpsCheckDialog = false
      const trip = this.pendingTrip
      this.pendingTrip = null
      if (!trip) return

      this.actionId = trip.id
      try {
        await axios.post('/planned-trips/start-stop', { planned_trip_id: trip.id, mode: 1 })
        trip.started_at = new Date().toISOString()
        GpsTrackingService.start(trip.id)
        this.$router.push('/driver/perjalanan').catch(() => {})
      } catch (e) { this.notifyError(e, 'Perjalanan gagal dimulai.') }
      finally { this.actionId = null }
    },
    sleep(ms) { return new Promise(r => setTimeout(r, ms)) },
    async completeTrip(trip) {
      const confirmation = await this.$swal.fire({ title: 'Selesaikan perjalanan?', text: 'Pengiriman lokasi GPS akan dihentikan.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, selesai', cancelButtonText: 'Batal' })
      if (!confirmation.isConfirmed) return
      this.actionId = trip.id
      try {
        await axios.post('/planned-trips/start-stop', { planned_trip_id: trip.id, mode: 0 })
        GpsTrackingService.stop()
        this.$notify({ type: 'success', title: 'Berhasil', text: 'Perjalanan telah selesai.' })
        await this.loadTrips()
      }
      catch (e) { this.notifyError(e, 'Perjalanan gagal diselesaikan.') }
      finally { this.actionId = null }
    },
    notifyError(e, fallback) { const text = e.response && e.response.data && e.response.data.message || fallback; this.$notify({ type: 'error', title: 'Gagal', text }) },
    scheduleDriverRoute() {
      if (this.routeRequestTimeout) clearTimeout(this.routeRequestTimeout)
      if (!this.currentPosition || !this.nextStop) {
        this.driverToNextStopRoutePath = []
        return
      }
      this.routeRequestTimeout = setTimeout(() => this.loadDriverToNextStopRoute(), 700)
    },
    async loadDriverToNextStopRoute() {
      if (!this.currentPosition || !this.nextStop) return
      const originLat = Number(this.currentPosition.lat).toFixed(5)
      const originLng = Number(this.currentPosition.lng).toFixed(5)
      const destinationLat = Number(this.nextStop.lat).toFixed(5)
      const destinationLng = Number(this.nextStop.lng).toFixed(5)
      const requestKey = `${originLat},${originLng}-${destinationLat},${destinationLng}`
      if (requestKey === this.lastRouteRequestKey) return

      this.lastRouteRequestKey = requestKey
      const requestId = ++this.routeRequestId
      try {
        const response = await axios.get(`/google-routes/compute-route?origin_lat=${originLat}&origin_lng=${originLng}&destination_lat=${destinationLat}&destination_lng=${destinationLng}`)
        if (requestId !== this.routeRequestId) return

        const backendPath = this.parseRoutePath(response.data)
        if (backendPath.length > 1) {
          this.driverToNextStopRoutePath = backendPath
          return
        }
      } catch (_) {}

      try {
        const osrmPath = await this.loadOsrmRoutePath(originLat, originLng, destinationLat, destinationLng)
        if (requestId === this.routeRequestId) this.driverToNextStopRoutePath = osrmPath
      } catch (_) {
        if (requestId === this.routeRequestId) this.driverToNextStopRoutePath = []
      }
    },
    parseRoutePath(data) {
      const route = data && data.routes && data.routes[0]
      const coordinates = route && route.polyline && route.polyline.geoJsonLinestring
        ? route.polyline.geoJsonLinestring.coordinates
        : route && route.geometry && Array.isArray(route.geometry.coordinates)
          ? route.geometry.coordinates
          : []
      if (!Array.isArray(coordinates)) return []
      return coordinates
        .map(([lng, lat]) => ({ lat:Number(lat), lng:Number(lng) }))
        .filter(point => Number.isFinite(point.lat) && Number.isFinite(point.lng))
    },
    async loadOsrmRoutePath(originLat, originLng, destinationLat, destinationLng) {
      const coordinates = `${originLng},${originLat};${destinationLng},${destinationLat}`
      const params = new URLSearchParams({ overview: 'full', geometries: 'geojson', steps: 'false' })
      const response = await fetch(`https://router.project-osrm.org/route/v1/driving/${coordinates}?${params.toString()}`, { method: 'GET', mode: 'cors' })
      if (!response.ok) return []
      const data = await response.json()
      if (!data || data.code !== 'Ok') return []
      return this.parseRoutePath(data)
    },
    nearestPointIndex(path, target) {
      let nearest = -1
      let min = Infinity
      path.forEach((point, index) => {
        const distance = this.distanceKm(point, target)
        if (distance < min) { min = distance; nearest = index }
      })
      return nearest
    },
    distanceKm(a,b) { const rad=value=>value*Math.PI/180;const earth=6371;const dLat=rad(Number(b.lat)-Number(a.lat));const dLng=rad(Number(b.lng)-Number(a.lng));const lat1=rad(Number(a.lat));const lat2=rad(Number(b.lat));const value=Math.sin(dLat/2)**2+Math.cos(lat1)*Math.cos(lat2)*Math.sin(dLng/2)**2;return earth*2*Math.atan2(Math.sqrt(value),Math.sqrt(1-value)) },
  },
}
</script>

<style scoped>
.trip-card,.empty-state { height:100%; border-radius:16px; border:1px solid rgba(58,53,65,.08); }.card-accent{height:5px}.card-accent.scheduled{background:linear-gradient(90deg,#9155fd,#b47cff)}.card-accent.active{background:linear-gradient(90deg,#ff9800,#ffc107)}.card-accent.completed{background:linear-gradient(90deg,#4caf50,#8bd28e)}.route-symbol{width:42px;height:42px;border-radius:12px;background:#f2eaff;display:flex;align-items:center;justify-content:center}.route-title{font-size:1.05rem}.info-row{display:flex;align-items:center;color:#6e6b78}.gps-banner{background:#eaf7eb;color:#2e7d32;border-radius:10px;display:flex;align-items:center}.gps-pulse{width:9px;height:9px;border-radius:50%;background:#4caf50;box-shadow:0 0 0 5px rgba(76,175,80,.14)}.action-btn{border-radius:10px;text-transform:none}.empty-icon{width:125px;height:100px;border-radius:50%;background:#f2eaff;display:flex;align-items:center;justify-content:center}.empty-copy{max-width:480px}.page-heading{min-height:58px}@media(max-width:600px){.page-heading{align-items:flex-start!important}.page-heading .v-chip{display:none}.route-title{max-width:160px;white-space:normal}}
.navigation-card{border-radius:18px!important;border:1px solid rgba(58,53,65,.08)}.navigation-header{border-bottom:1px solid rgba(58,53,65,.08)}.navigation-metrics .metric+ .metric{border-left:1px solid rgba(58,53,65,.1)}.map-column{position:relative;background:#eee}.map-column ::v-deep .leaflet-map{height:430px}.map-waiting{position:absolute;z-index:900;left:16px;bottom:16px;background:#fff;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.14)}.stops-column{max-height:430px;overflow-y:auto}.next-stop-card{border-radius:12px;background:#f0fdf4;border:1px solid rgba(34,197,94,.2)}.stop-track{width:18px;display:flex;flex-direction:column;align-items:center}.stop-dot{display:block;flex:none;width:14px;height:14px;border:3px solid #fff;border-radius:50%;background:#7c3aed;box-shadow:0 0 0 2px #7c3aed}.stop-dot.destination{background:#ef4444;box-shadow:0 0 0 2px #ef4444}.stop-line{width:2px;flex:1;min-height:32px;background:#ddd5ed;margin-top:4px}.stop-address{color:#777;word-break:break-word}
.gps-check-card{border-radius:16px!important}
@media(max-width:600px){.navigation-header{align-items:flex-start!important}.navigation-metrics{width:100%}.navigation-metrics .metric:first-child{padding-left:0!important}.map-column ::v-deep .leaflet-map{height:330px}.stops-column{max-height:none}}
</style>
