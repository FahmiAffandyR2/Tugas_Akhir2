<template>
  <v-app class="customer-app">
    <v-navigation-drawer v-model="drawer" app :permanent="$vuetify.breakpoint.mdAndUp" width="270" class="customer-drawer">
      <div class="brand d-flex align-center px-6 py-6">
        <div class="brand-logo mr-3"><v-img :src="require('@/assets/images/logos/logo.png')" max-width="42" /></div>
        <div><div class="font-weight-bold text-h6">EZBus</div><div class="caption grey--text">Portal Customer</div></div>
      </div>
      <v-list nav class="px-4">
        <v-list-item v-for="item in menus" :key="item.to" :to="item.to" exact active-class="customer-menu-active" class="customer-menu mb-2">
          <v-list-item-icon><v-icon>{{ item.icon }}</v-icon></v-list-item-icon>
          <v-list-item-title>{{ item.title }}</v-list-item-title>
        </v-list-item>
      </v-list>
      <template #append>
        <div class="help-card ma-4 pa-4">
          <v-icon color="primary" class="mb-2">mdi-headset</v-icon>
          <div class="font-weight-bold mb-1">Butuh bantuan?</div>
          <div class="caption grey--text mb-3">Tim kami siap membantu rencana perjalanan Anda.</div>
          <v-btn small outlined color="primary" block>Hubungi Kami</v-btn>
        </div>
      </template>
    </v-navigation-drawer>

    <v-app-bar app flat color="white" class="customer-bar px-md-5">
      <v-app-bar-nav-icon class="d-md-none" @click="drawer = !drawer" />
      <div class="d-none d-sm-block"><div class="font-weight-bold">Sewa Bus Pariwisata</div><small class="grey--text">Perjalanan nyaman untuk rombongan Anda</small></div>
      <v-spacer />
      <v-btn icon class="mr-1"><v-icon>mdi-bell-outline</v-icon></v-btn>
      <v-menu offset-y left>
        <template #activator="{ on, attrs }"><v-btn text rounded v-bind="attrs" v-on="on"><v-avatar color="deep-purple lighten-5" size="34" class="mr-sm-2"><v-icon color="primary">mdi-account</v-icon></v-avatar><span class="d-none d-sm-inline text-capitalize">{{ customerName }}</span><v-icon small>mdi-chevron-down</v-icon></v-btn></template>
        <v-list min-width="190"><v-list-item @click="logout"><v-list-item-icon><v-icon color="error">mdi-logout</v-icon></v-list-item-icon><v-list-item-title>Keluar</v-list-item-title></v-list-item></v-list>
      </v-menu>
    </v-app-bar>
    <v-main><v-container class="customer-container pa-4 pa-md-8"><slot /></v-container></v-main>

    <v-bottom-navigation v-if="$vuetify.breakpoint.smAndDown" app grow color="primary" class="customer-bottom-nav">
      <v-btn v-for="item in mobileMenus" :key="item.to" :to="item.to">
        <span>{{ item.mobileTitle }}</span>
        <v-icon>{{ item.icon }}</v-icon>
      </v-btn>
    </v-bottom-navigation>
  </v-app>
</template>

<script>
import AuthService from '@/services/AuthService'
export default {
  data: () => ({
    drawer: null,
    customerName: 'Customer',
    menus: [
      { title: 'Beranda', mobileTitle: 'Beranda', icon: 'mdi-view-dashboard-outline', to: '/customer/beranda' },
      { title: 'Pesan Bus', mobileTitle: 'Pesan', icon: 'mdi-bus-marker', to: '/customer/pesan' },
      { title: 'Pemesanan Saya', mobileTitle: 'Pesanan', icon: 'mdi-clipboard-text-clock-outline', to: '/customer/pemesanan' },
      { title: 'Profil Saya', mobileTitle: 'Profil', icon: 'mdi-account-outline', to: '/customer/profil' },
    ],
  }),
  computed: {
    mobileMenus() { return this.menus },
  },
  async created() { try { const r = await AuthService.getAuthUser(); this.customerName = r.data.data.name } catch (_) {} },
  methods: {
    async logout() {
      const confirmed = await this.confirmLogout()
      if (confirmed) AuthService.logout('customer')
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
.customer-app{background:#f7f8fc!important;color:#4b465c}.customer-drawer{border-right:1px solid #ececf3!important}.brand-logo{width:52px;height:52px;border-radius:16px;background:#f1eaff;display:flex;align-items:center;justify-content:center}.customer-bar{border-bottom:1px solid #ececf3!important}.customer-container{max-width:1320px}.customer-menu{border-radius:12px!important;color:#716b7c}.customer-menu-active{background:linear-gradient(118deg,#7c3aed,#9b5cff)!important;color:#fff!important;box-shadow:0 6px 16px rgba(124,58,237,.25)}.customer-menu-active .v-icon{color:#fff!important}.help-card{border-radius:16px;background:#f7f3ff;border:1px solid #ede4ff}.customer-bottom-nav{border-top:1px solid rgba(58,53,65,.1)}
@media (max-width: 600px){.customer-container{padding-left:16px!important;padding-right:16px!important;padding-bottom:82px!important}}
</style>
