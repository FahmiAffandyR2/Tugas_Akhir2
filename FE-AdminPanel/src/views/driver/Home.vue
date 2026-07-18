<template>
  <div>
    <v-skeleton-loader v-if="loading" type="heading, card, card" />
    <template v-else>
      <section class="welcome-card pa-6 pa-md-8 mb-6">
        <v-row align="center">
          <v-col cols="12" md="8">
            <div class="eyebrow mb-2">{{ greeting }}, {{ firstName }} 👋</div>
            <h1 class="text-h4 font-weight-bold mb-2">Siap untuk perjalanan hari ini?</h1>
            <p class="mb-0 welcome-copy">Pastikan kondisi kendaraan, koneksi internet, dan GPS sudah siap sebelum berangkat.</p>
          </v-col>
          <v-col cols="12" md="4" class="text-md-right">
            <div class="readiness d-inline-flex align-center pa-3"><span class="pulse mr-3"/><div class="text-left"><small>Status bertugas</small><div class="font-weight-bold">Siap Bertugas</div></div></div>
          </v-col>
        </v-row>
      </section>

      <v-row class="mb-2">
        <v-col v-for="stat in stats" :key="stat.label" cols="6" md="3">
          <v-card flat class="stat-card pa-4 h-full"><div class="d-flex align-center"><div class="stat-icon mr-3" :style="{ background: stat.bg }"><v-icon :color="stat.color">{{ stat.icon }}</v-icon></div><div><div class="text-h5 font-weight-bold">{{ stat.value }}</div><small class="muted">{{ stat.label }}</small></div></div></v-card>
        </v-col>
      </v-row>

      <v-row>
        <v-col cols="12" lg="8">
          <div class="section-title d-flex align-center mb-3"><div><h2 class="text-h6 font-weight-bold mb-0">{{ activeTrip ? 'Perjalanan aktif' : 'Perjalanan berikutnya' }}</h2><small class="muted">Informasi tugas yang perlu Anda siapkan</small></div><v-spacer/><v-btn text color="primary" to="/driver/jadwal">Semua jadwal<v-icon right small>mdi-arrow-right</v-icon></v-btn></div>
          <v-card v-if="featuredTrip" flat class="next-trip overflow-hidden">
            <div class="trip-accent" :class="activeTrip ? 'active' : ''" />
            <v-card-text class="pa-6">
              <div class="d-flex align-start flex-wrap"><div class="route-icon mr-4"><v-icon color="primary" size="30">mdi-bus-marker</v-icon></div><div><v-chip x-small :color="activeTrip ? 'success' : 'warning'" dark class="mb-2">{{ activeTrip ? 'SEDANG BERJALAN' : 'TERJADWAL' }}</v-chip><h3 class="text-h5 font-weight-bold mb-1">{{ routeName(featuredTrip) }}</h3><div class="muted"><v-icon small class="mr-1">mdi-calendar</v-icon>{{ formatDate(featuredTrip.planned_date) }}</div></div></div>
              <v-divider class="my-5" />
              <v-row>
                <v-col cols="6" sm="4"><small class="muted">Kendaraan</small><div class="font-weight-medium mt-1"><v-icon small class="mr-1">mdi-bus-side</v-icon>{{ busName(featuredTrip) }}</div></v-col>
                <v-col cols="6" sm="4"><small class="muted">Status GPS</small><div class="font-weight-medium mt-1" :class="gpsReady ? 'success--text' : 'warning--text'"><v-icon small :color="gpsReady ? 'success' : 'warning'" class="mr-1">mdi-crosshairs-gps</v-icon>{{ gpsReady ? 'Siap' : 'Perlu izin' }}</div></v-col>
                <v-col cols="12" sm="4"><small class="muted">Status perjalanan</small><div class="font-weight-medium mt-1">{{ activeTrip ? 'Dalam perjalanan' : 'Belum dimulai' }}</div></v-col>
              </v-row>
            </v-card-text>
            <v-card-actions class="px-6 pb-6 pt-0"><v-btn large color="primary" :to="activeTrip ? '/driver/perjalanan' : '/driver/jadwal'" class="action-btn"><v-icon left>{{ activeTrip ? 'mdi-navigation-variant' : 'mdi-eye-outline' }}</v-icon>{{ activeTrip ? 'Buka Perjalanan' : 'Lihat Detail Jadwal' }}</v-btn></v-card-actions>
          </v-card>
          <v-card v-else flat class="empty-card text-center pa-8 pa-md-12">
            <div class="empty-illustration mx-auto mb-5"><v-icon size="66" color="primary">mdi-bus-clock</v-icon><span class="road-line"/></div>
            <h3 class="text-h6 font-weight-bold mb-2">Belum ada jadwal perjalanan</h3>
            <p class="muted mx-auto empty-copy">Jadwal yang ditugaskan oleh Super Admin akan muncul di sini. Nikmati waktu istirahat Anda.</p>
            <v-btn outlined color="primary" class="mt-2" @click="loadData"><v-icon left>mdi-refresh</v-icon>Perbarui Jadwal</v-btn>
          </v-card>
        </v-col>

        <v-col cols="12" lg="4">
          <h2 class="text-h6 font-weight-bold mb-3">Kesiapan perjalanan</h2>
          <v-card flat class="checklist-card pa-5">
            <div v-for="item in checklist" :key="item.label" class="check-row d-flex align-center py-3"><div class="check-icon mr-3" :class="item.ready ? 'ready' : 'attention'"><v-icon small :color="item.ready ? 'success' : 'warning'">{{ item.ready ? 'mdi-check' : 'mdi-alert-outline' }}</v-icon></div><div><div class="font-weight-medium">{{ item.label }}</div><small class="muted">{{ item.description }}</small></div></div>
            <v-divider class="my-3" />
            <div class="tip pa-4"><v-icon color="primary" class="mr-2">mdi-lightbulb-outline</v-icon><span class="text-body-2">Buka PWA selama perjalanan agar lokasi GPS dapat terkirim secara berkala.</span></div>
          </v-card>
        </v-col>
      </v-row>
    </template>
  </div>
</template>

<script>
import AuthService from '@/services/AuthService'
export default {
  data: () => ({ trips: [], loading: true, userName: 'Driver', online: navigator.onLine, gpsReady: false }),
  computed: {
    firstName() { return this.userName.split(' ')[0] },
    greeting() { const h = new Date().getHours(); return h < 11 ? 'Selamat pagi' : h < 15 ? 'Selamat siang' : h < 18 ? 'Selamat sore' : 'Selamat malam' },
    activeTrip() { return this.trips.find(t => t.started_at && !t.ended_at) },
    nextTrip() { return this.trips.filter(t => !t.started_at && !t.ended_at).sort((a, b) => new Date(a.planned_date) - new Date(b.planned_date))[0] },
    featuredTrip() { return this.activeTrip || this.nextTrip },
    completedCount() { return this.trips.filter(t => t.ended_at).length },
    scheduledCount() { return this.trips.filter(t => !t.started_at && !t.ended_at).length },
    stats() { return [
      { label: 'Jadwal tersedia', value: this.scheduledCount, icon: 'mdi-calendar-clock', color: '#9155fd', bg: '#f2eaff' },
      { label: 'Perjalanan aktif', value: this.activeTrip ? 1 : 0, icon: 'mdi-map-marker-path', color: '#ff9800', bg: '#fff3df' },
      { label: 'Telah selesai', value: this.completedCount, icon: 'mdi-check-circle-outline', color: '#4caf50', bg: '#e8f5e9' },
      { label: 'Total perjalanan', value: this.trips.length, icon: 'mdi-road-variant', color: '#2196f3', bg: '#e8f3fd' },
    ] },
    checklist() { return [
      { label: 'Koneksi internet', description: this.online ? 'Perangkat terhubung ke internet' : 'Periksa jaringan perangkat', ready: this.online },
      { label: 'Akses lokasi GPS', description: this.gpsReady ? 'Izin lokasi sudah tersedia' : 'Izin diminta saat perjalanan dimulai', ready: this.gpsReady },
      { label: 'Jadwal perjalanan', description: this.featuredTrip ? 'Tugas perjalanan tersedia' : 'Belum ada tugas baru', ready: !!this.featuredTrip },
    ] },
  },
  created() { this.loadData(); window.addEventListener('online', this.updateOnline); window.addEventListener('offline', this.updateOnline); this.checkGpsPermission() },
  beforeDestroy() { window.removeEventListener('online', this.updateOnline); window.removeEventListener('offline', this.updateOnline) },
  methods: {
    async loadData() { this.loading = true; try { const [trips, user] = await Promise.all([axios.get('/drivers/get-driver-trips'), AuthService.getAuthUser()]); this.trips = trips.data.trips || []; this.userName = user.data.data.name } catch (e) { this.$notify({ type: 'error', title: 'Gagal', text: 'Dashboard driver tidak dapat dimuat.' }) } finally { this.loading = false } },
    async checkGpsPermission() { if (!navigator.permissions) return; try { const p = await navigator.permissions.query({ name: 'geolocation' }); this.gpsReady = p.state === 'granted'; p.onchange = () => { this.gpsReady = p.state === 'granted' } } catch (_) {} },
    updateOnline() { this.online = navigator.onLine },
    routeName(t) { return t.route && t.route.name || `Perjalanan #${t.id}` },
    busName(t) { return t.bus && (t.bus.license || t.bus.name) || 'Belum ditentukan' },
    formatDate(v) { return v ? new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${v}T00:00:00`)) : '-' },
  },
}
</script>

<style scoped>
.welcome-card { color: #fff; border-radius: 20px; background: linear-gradient(125deg, #6f36d8 0%, #9155fd 55%, #b47cff 100%); box-shadow: 0 12px 30px rgba(111,54,216,.22); position: relative; overflow: hidden; }
.welcome-card:after { content: ''; position: absolute; width: 220px; height: 220px; border: 40px solid rgba(255,255,255,.08); border-radius: 50%; right: -65px; top: -95px; }.welcome-copy { color: rgba(255,255,255,.8); }.eyebrow { color: rgba(255,255,255,.85); font-weight: 600; }.readiness { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22); border-radius: 14px; }.pulse { width: 12px; height: 12px; border-radius: 50%; background: #7dff9b; box-shadow: 0 0 0 6px rgba(125,255,155,.15); }
.h-full { height: 100%; }.stat-card,.next-trip,.empty-card,.checklist-card { border-radius: 16px; border: 1px solid rgba(58,53,65,.07); }.stat-icon,.route-icon { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; }.route-icon { background:#f2eaff; }.muted { color:#8a8795; }.next-trip { position:relative; }.trip-accent { height:5px; background:linear-gradient(90deg,#ff9800,#ffc107); }.trip-accent.active { background:linear-gradient(90deg,#4caf50,#7bd77f); }.action-btn { border-radius:10px; text-transform:none; }.empty-illustration { width:140px; height:110px; border-radius:50%; background:#f2eaff; display:flex; align-items:center; justify-content:center; position:relative; }.road-line { position:absolute; width:105px; border-top:3px dashed rgba(145,85,253,.35); bottom:19px; }.empty-copy { max-width:430px; }.check-row+.check-row { border-top:1px solid rgba(58,53,65,.07); }.check-icon { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex:0 0 auto; }.check-icon.ready { background:#e8f5e9; }.check-icon.attention { background:#fff3df; }.tip { background:#f6f1ff; border-radius:12px; display:flex; align-items:flex-start; }
@media(max-width:600px){.welcome-card{border-radius:16px}.welcome-card h1{font-size:1.5rem!important}.stat-card{padding:14px!important}.stat-icon{width:40px;height:40px}.stat-card .text-h5{font-size:1.25rem!important}}
</style>
