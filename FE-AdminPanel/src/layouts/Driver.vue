<template>
  <v-app class="driver-app">
    <v-navigation-drawer v-model="drawer" app width="278" class="driver-drawer" :permanent="$vuetify.breakpoint.lgAndUp">
      <div class="brand pa-6 d-flex align-center">
        <div class="logo-box mr-3"><v-img :src="require('@/assets/images/logos/logo.png')" max-width="40" /></div>
        <div><div class="text-h6 font-weight-bold">Driver</div><small class="muted">Teman perjalanan Anda</small></div>
      </div>
      <v-divider />
      <v-list nav class="px-4 pt-5">
        <v-list-item v-for="item in items" :key="item.to" :to="item.to" color="primary" class="nav-item mb-2">
          <v-list-item-icon><v-icon>{{ item.icon }}</v-icon></v-list-item-icon>
          <v-list-item-content><v-list-item-title class="font-weight-medium">{{ item.title }}</v-list-item-title></v-list-item-content>
        </v-list-item>
      </v-list>
      <template #append>
        <div class="pa-5">
          <div class="connection-card pa-3 mb-4 d-flex align-center">
            <span class="status-dot mr-2" :class="online ? 'online' : 'offline'" />
            <div><small class="muted">Status koneksi</small><div class="text-body-2 font-weight-medium">{{ online ? 'Terhubung' : 'Offline' }}</div></div>
          </div>
          <v-btn block outlined color="error" class="logout-btn" @click="logout"><v-icon left>mdi-logout</v-icon>Keluar</v-btn>
        </div>
      </template>
    </v-navigation-drawer>

    <v-app-bar app flat color="white" class="driver-bar">
      <v-app-bar-nav-icon class="d-lg-none" @click="drawer = !drawer" />
      <div class="d-none d-sm-block"><div class="font-weight-bold">Area Driver</div><small class="muted">{{ today }}</small></div>
      <v-spacer />
      <v-chip small :color="online ? 'success' : 'grey'" outlined class="mr-3 d-none d-sm-flex"><v-icon left small>mdi-wifi</v-icon>{{ online ? 'Online' : 'Offline' }}</v-chip>
      <v-avatar color="primary" size="38" class="mr-2"><span class="white--text font-weight-bold">{{ initials }}</span></v-avatar>
      <div class="d-none d-sm-block"><div class="text-body-2 font-weight-bold">{{ userName }}</div><small class="muted">Driver</small></div>
    </v-app-bar>

    <v-main><v-container class="driver-container py-6 py-md-8"><slot /></v-container></v-main>

    <v-bottom-navigation v-if="$vuetify.breakpoint.smAndDown" app grow color="primary" class="driver-bottom-nav">
      <v-btn v-for="item in mobileItems" :key="item.to" :to="item.to"><span>{{ item.mobileTitle }}</span><v-icon>{{ item.icon }}</v-icon></v-btn>
    </v-bottom-navigation>
  </v-app>
</template>

<script>
import AuthService from '@/services/AuthService'
export default {
  data: () => ({
    drawer: null,
    userName: 'Driver',
    online: navigator.onLine,
    items: [
      { title: 'Beranda', mobileTitle: 'Beranda', icon: 'mdi-view-dashboard-outline', to: '/driver/beranda' },
      { title: 'Jadwal Saya', mobileTitle: 'Jadwal', icon: 'mdi-calendar-clock', to: '/driver/jadwal' },
      { title: 'Perjalanan Aktif', mobileTitle: 'Aktif', icon: 'mdi-map-marker-path', to: '/driver/perjalanan' },
      { title: 'Riwayat Perjalanan', mobileTitle: 'Riwayat', icon: 'mdi-history', to: '/driver/riwayat' },
      { title: 'Profil Saya', mobileTitle: 'Profil', icon: 'mdi-account-outline', to: '/driver/profil' },
    ],
  }),
  computed: {
    mobileItems() { return this.items },
    initials() { return this.userName.split(' ').map(v => v[0]).slice(0, 2).join('').toUpperCase() },
    today() { return new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date()) },
  },
  async created() {
    try { const response = await AuthService.getAuthUser(); this.userName = response.data.data.name } catch (_) {}
    window.addEventListener('online', this.updateConnection)
    window.addEventListener('offline', this.updateConnection)
  },
  beforeDestroy() { window.removeEventListener('online', this.updateConnection); window.removeEventListener('offline', this.updateConnection) },
  methods: {
    updateConnection() { this.online = navigator.onLine },
    async logout() {
      const confirmed = await this.confirmLogout()
      if (confirmed) AuthService.logout()
    },
    async confirmLogout() {
      if (!this.$swal) return window.confirm('Apakah Anda yakin ingin keluar?')

      const result = await this.$swal.fire({
        title: 'Keluar dari aplikasi?',
        text: 'Anda perlu login kembali untuk masuk ke akun ini.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, keluar',
        cancelButtonText: 'Batal',
      })

      return result.isConfirmed
    },
  },
}
</script>

<style scoped>
.driver-app { background: #f5f6fa !important; }
.driver-drawer, .driver-bar { border-color: rgba(58, 53, 65, .08) !important; }
.brand { min-height: 88px; }
.logo-box { width: 46px; height: 46px; border-radius: 14px; display: flex; align-items: center; justify-content: center; background: #f2eaff; }
.muted { color: #8a8795; }
.nav-item { min-height: 52px; border-radius: 12px; }
.nav-item.v-list-item--active { background: linear-gradient(135deg, #9155fd, #6f36d8); color: #fff !important; box-shadow: 0 7px 18px rgba(145,85,253,.24); }
.nav-item.v-list-item--active .v-icon { color: #fff !important; }
.connection-card { border-radius: 12px; background: #f7f7fa; }
.status-dot { width: 10px; height: 10px; border-radius: 50%; }.status-dot.online { background: #4caf50; box-shadow: 0 0 0 4px rgba(76,175,80,.12); }.status-dot.offline { background: #9e9e9e; }
.logout-btn { border-radius: 10px; }
.driver-bar { box-shadow: 0 1px 0 rgba(58,53,65,.08) !important; }
.driver-container { max-width: 1280px; }
.driver-bottom-nav { border-top: 1px solid rgba(58,53,65,.1); }
@media (max-width: 600px) { .driver-container { padding-left: 16px; padding-right: 16px; padding-bottom: 82px !important; } }
</style>
