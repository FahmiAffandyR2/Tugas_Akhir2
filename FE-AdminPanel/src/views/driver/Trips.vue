<template>
  <div>
    <v-alert v-if="gpsMessage" :type="gpsMessage.type" dismissible>{{ gpsMessage.text }}</v-alert>
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
          <div class="navigation-metrics d-flex mt-3 mt-sm-0">
            <div class="metric px-4"><div class="caption grey--text">Jarak tersisa</div><strong>{{ remainingDistanceLabel }}</strong></div>
            <div class="metric px-4"><div class="caption grey--text">Estimasi tiba</div><strong>{{ etaLabel }}</strong></div>
          </div>
        </div>
        <v-row no-gutters>
          <v-col cols="12" lg="8" class="map-column">
            <LeafletMapLoader :center="mapCenter" :zoom="12" :markers="navigationMarkers" :polylines="navigationPolylines" :enabled="false" />
            <div v-if="!currentPosition" class="map-waiting pa-3"><v-icon small color="warning" class="mr-2">mdi-crosshairs-question</v-icon>Menunggu posisi GPS perangkat</div>
          </v-col>
          <v-col cols="12" lg="4" class="stops-column pa-5">
            <div class="d-flex align-center mb-4"><v-icon color="primary" class="mr-2">mdi-map-marker-path</v-icon><span class="font-weight-bold">Titik pemberhentian</span></div>
            <div v-for="(stop,index) in routeStops" :key="stop.id" class="stop-item d-flex">
              <div class="stop-track mr-3"><span :class="['stop-dot', {destination:index===routeStops.length-1}]" /><span v-if="index<routeStops.length-1" class="stop-line" /></div>
              <div class="pb-5"><div class="font-weight-bold">{{ stop.name || `Stop ${index+1}` }}</div><div class="caption grey--text">{{ index===0?'Titik awal':index===routeStops.length-1?'Tujuan akhir':`Pemberhentian ${index+1}` }}</div><div v-if="stop.address" class="caption stop-address mt-1">{{ stop.address }}</div></div>
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
export default {
  components: { LeafletMapLoader },
  data: () => ({ trips: [], loading: false, actionId: null, watchId: null, activeTripId: null, lastSentAt: 0, gpsMessage: null, currentPosition: null }),
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
    destination() { return this.routeStops.length ? this.routeStops[this.routeStops.length-1] : null },
    mapCenter() { return this.currentPosition || (this.routeStops[0] ? {lat:Number(this.routeStops[0].lat),lng:Number(this.routeStops[0].lng)} : {lat:-6.2,lng:106.8}) },
    navigationMarkers() {
      const markers=this.routeStops.map((stop,index)=>({place_id:`stop-${stop.id}`,position:{lat:Number(stop.lat),lng:Number(stop.lng)},infoText:`<b>${stop.name||`Stop ${index+1}`}</b><br>${index===0?'Titik awal':index===this.routeStops.length-1?'Tujuan akhir':'Pemberhentian'}`}))
      if(this.currentPosition) markers.push({place_id:'driver-position',position:this.currentPosition,infoText:'<b>Posisi Anda saat ini</b>'})
      return markers
    },
    navigationPolylines() { return this.routePath.length ? [{data:this.routePath,strokeColor:'#7c3aed'}] : [] },
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
  },
  watch: { '$route.meta.tripFilter'() { this.resumeTracking() } },
  mounted() { this.loadTrips() },
  beforeDestroy() { this.stopTracking() },
  methods: {
    async loadTrips() {
      this.loading = true
      try { const r = await axios.get('/drivers/get-driver-trips'); this.trips = r.data.trips || []; this.resumeTracking() }
      catch (e) { this.notifyError(e, 'Jadwal tidak dapat dimuat.') }
      finally { this.loading = false }
    },
    isScheduled(t) { return !t.started_at && !t.ended_at },
    isActive(t) { return !!t.started_at && !t.ended_at },
    status(t) { return t.ended_at ? { label: 'Selesai', color: 'success', className: 'completed' } : t.started_at ? { label: 'Berjalan', color: 'orange', className: 'active' } : { label: 'Terjadwal', color: 'primary', className: 'scheduled' } },
    busName(t) { return t.bus ? (t.bus.license || t.bus.name || `Bus #${t.bus.id}`) : 'Bus belum ditentukan' },
    formatDate(value) { return value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' }).format(new Date(value + 'T00:00:00')) : '-' },
    async startTrip(trip) {
      if (!navigator.geolocation) return this.setGpsError('Perangkat ini tidak mendukung GPS.')
      this.actionId = trip.id
      navigator.geolocation.getCurrentPosition(async position => {
        try {
          await axios.post('/planned-trips/start-stop', { planned_trip_id: trip.id, mode: 1 })
          trip.started_at = new Date().toISOString()
          await this.sendPosition(trip.id, position)
          this.startTracking(trip.id)
          this.$router.push('/driver/perjalanan').catch(() => {})
        } catch (e) { this.notifyError(e, 'Perjalanan gagal dimulai.') }
        finally { this.actionId = null }
      }, error => { this.actionId = null; this.setGpsError(this.geoError(error)) }, { enableHighAccuracy: true, timeout: 15000 })
    },
    async completeTrip(trip) {
      const confirmation = await this.$swal.fire({ title: 'Selesaikan perjalanan?', text: 'Pengiriman lokasi GPS akan dihentikan.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, selesai', cancelButtonText: 'Batal' })
      if (!confirmation.isConfirmed) return
      this.actionId = trip.id
      try { await axios.post('/planned-trips/start-stop', { planned_trip_id: trip.id, mode: 0 }); trip.ended_at = new Date().toISOString(); this.stopTracking(); this.$notify({ type: 'success', title: 'Berhasil', text: 'Perjalanan telah selesai.' }) }
      catch (e) { this.notifyError(e, 'Perjalanan gagal diselesaikan.') }
      finally { this.actionId = null }
    },
    resumeTracking() { const active = this.trips.find(this.isActive); if (active) { const lat=Number(active.last_position_lat);const lng=Number(active.last_position_lng);if(Number.isFinite(lat)&&Number.isFinite(lng))this.currentPosition={lat,lng};this.startTracking(active.id) } else { this.currentPosition=null;this.stopTracking() } },
    startTracking(id) {
      if (this.watchId !== null && this.activeTripId === id) return
      this.stopTracking(); this.activeTripId = id
      if (!navigator.geolocation) return this.setGpsError('Perangkat ini tidak mendukung GPS.')
      this.watchId = navigator.geolocation.watchPosition(p => this.sendPosition(id, p), e => this.setGpsError(this.geoError(e)), { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 })
    },
    stopTracking() { if (this.watchId !== null && navigator.geolocation) navigator.geolocation.clearWatch(this.watchId); this.watchId = null; this.activeTripId = null },
    async sendPosition(id, position) {
      this.currentPosition = { lat: position.coords.latitude, lng: position.coords.longitude }
      if (Date.now() - this.lastSentAt < 10000) return
      this.lastSentAt = Date.now()
      try { await axios.post('/planned-trips/set-last-position', { planned_trip_id: id, lat: position.coords.latitude, lng: position.coords.longitude, speed: position.coords.speed || 0 }); this.gpsMessage = { type: 'success', text: 'Lokasi GPS berhasil dikirim ke Super Admin.' } }
      catch (e) {
        const message = e.response && e.response.data && e.response.data.message
        this.gpsMessage = { type: 'warning', text: message || 'Lokasi belum berhasil dikirim. Sistem akan mencoba lagi.' }
      }
    },
    geoError(e) { return e.code === 1 ? 'Izin lokasi ditolak. Aktifkan izin GPS untuk memulai perjalanan.' : 'Lokasi GPS tidak dapat diperoleh. Pastikan GPS aktif.' },
    setGpsError(text) { this.gpsMessage = { type: 'error', text } },
    notifyError(e, fallback) { const text = e.response && e.response.data && e.response.data.message || fallback; this.$notify({ type: 'error', title: 'Gagal', text }) },
    distanceKm(a,b) { const rad=value=>value*Math.PI/180;const earth=6371;const dLat=rad(Number(b.lat)-Number(a.lat));const dLng=rad(Number(b.lng)-Number(a.lng));const lat1=rad(Number(a.lat));const lat2=rad(Number(b.lat));const value=Math.sin(dLat/2)**2+Math.cos(lat1)*Math.cos(lat2)*Math.sin(dLng/2)**2;return earth*2*Math.atan2(Math.sqrt(value),Math.sqrt(1-value)) },
  },
}
</script>

<style scoped>
.trip-card,.empty-state { height:100%; border-radius:16px; border:1px solid rgba(58,53,65,.08); }.card-accent{height:5px}.card-accent.scheduled{background:linear-gradient(90deg,#9155fd,#b47cff)}.card-accent.active{background:linear-gradient(90deg,#ff9800,#ffc107)}.card-accent.completed{background:linear-gradient(90deg,#4caf50,#8bd28e)}.route-symbol{width:42px;height:42px;border-radius:12px;background:#f2eaff;display:flex;align-items:center;justify-content:center}.route-title{font-size:1.05rem}.info-row{display:flex;align-items:center;color:#6e6b78}.gps-banner{background:#eaf7eb;color:#2e7d32;border-radius:10px;display:flex;align-items:center}.gps-pulse{width:9px;height:9px;border-radius:50%;background:#4caf50;box-shadow:0 0 0 5px rgba(76,175,80,.14)}.action-btn{border-radius:10px;text-transform:none}.empty-icon{width:125px;height:100px;border-radius:50%;background:#f2eaff;display:flex;align-items:center;justify-content:center}.empty-copy{max-width:480px}.page-heading{min-height:58px}@media(max-width:600px){.page-heading{align-items:flex-start!important}.page-heading .v-chip{display:none}.route-title{max-width:160px;white-space:normal}}
.navigation-card{border-radius:18px!important;border:1px solid rgba(58,53,65,.08)}.navigation-header{border-bottom:1px solid rgba(58,53,65,.08)}.navigation-metrics .metric+ .metric{border-left:1px solid rgba(58,53,65,.1)}.map-column{position:relative;background:#eee}.map-column ::v-deep .leaflet-map{height:430px}.map-waiting{position:absolute;z-index:900;left:16px;bottom:16px;background:#fff;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.14)}.stops-column{max-height:430px;overflow-y:auto}.stop-track{width:18px;display:flex;flex-direction:column;align-items:center}.stop-dot{display:block;flex:none;width:14px;height:14px;border:3px solid #fff;border-radius:50%;background:#7c3aed;box-shadow:0 0 0 2px #7c3aed}.stop-dot.destination{background:#ef4444;box-shadow:0 0 0 2px #ef4444}.stop-line{width:2px;flex:1;min-height:32px;background:#ddd5ed;margin-top:4px}.stop-address{color:#777;word-break:break-word}
@media(max-width:600px){.navigation-header{align-items:flex-start!important}.navigation-metrics{width:100%}.navigation-metrics .metric:first-child{padding-left:0!important}.map-column ::v-deep .leaflet-map{height:330px}.stops-column{max-height:none}}
</style>
