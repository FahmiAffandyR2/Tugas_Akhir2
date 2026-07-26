<template>
  <div class="customer-login d-flex align-center justify-center pa-4">
    <v-card class="login-card overflow-hidden" elevation="12">
      <v-row no-gutters>
        <v-col cols="12" md="6" class="hero-panel pa-8 pa-md-12 d-none d-md-flex flex-column justify-space-between">
          <div><div class="d-flex align-center mb-10"><v-img :src="require('@/assets/images/logos/logo.png')" max-width="44" class="mr-3"/><span class="text-h6 font-weight-bold">Bus Pariwisata</span></div><h1 class="text-h3 font-weight-bold mb-5">Perjalanan rombongan jadi lebih mudah.</h1><p class="text-h6 hero-copy">Rencanakan study tour, gathering, dan perjalanan wisata dalam satu tempat.</p></div>
          <div class="d-flex"><v-chip outlined dark class="mr-2"><v-icon left small>mdi-bus</v-icon>Armada pilihan</v-chip><v-chip outlined dark><v-icon left small>mdi-map-marker-path</v-icon>Live tracking</v-chip></div>
        </v-col>
        <v-col cols="12" md="6" class="pa-7 pa-sm-10 pa-md-12">
          <div class="d-flex align-center d-md-none mb-8"><v-img :src="require('@/assets/images/logos/logo.png')" max-width="40" class="mr-3"/><span class="text-h6 font-weight-bold">Portal Customer</span></div>
          <p class="overline primary--text font-weight-bold">PORTAL CUSTOMER</p><h2 class="text-h4 font-weight-bold mb-2">Selamat datang</h2><p class="grey--text mb-7">Masuk untuk mengelola permintaan sewa bus Anda.</p>
          <v-alert v-if="error" type="error" dense>{{ error }}</v-alert>
          <v-form ref="form" @submit.prevent="login">
            <v-text-field v-model.trim="email" outlined label="Email" type="email" :rules="[v => !!v || 'Email wajib diisi']" class="mb-2" />
            <v-text-field v-model="password" outlined label="Password" :type="showPassword ? 'text' : 'password'" :append-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'" :rules="[v => !!v || 'Password wajib diisi']" @click:append="showPassword = !showPassword" />
            <div class="d-flex justify-space-between align-center mb-5"><v-checkbox label="Ingat saya" hide-details class="mt-0"/><router-link to="/forgot-password">Lupa password?</router-link></div>
            <v-btn type="submit" block large color="primary" class="login-btn" :loading="loading">Masuk sebagai Customer</v-btn>
          </v-form>
          <div class="text-center mt-6">Belum memiliki akun? <router-link to="/customer/register" class="font-weight-bold">Daftar sebagai customer</router-link></div>
          <v-divider class="my-6"/><div class="text-center text-body-2">Anda Admin atau Driver? <router-link to="/login" class="font-weight-bold">Masuk ke portal internal</router-link></div>
        </v-col>
      </v-row>
    </v-card>
  </div>
</template>

<script>
import AuthService from '@/services/AuthService'
export default {
  data: () => ({ email: '', password: '', showPassword: false, loading: false, error: null }),
  methods: {
    async login() { if (!this.$refs.form.validate()) return; this.loading = true; this.error = null; const result = await AuthService.login({ email: this.email, password: this.password, portal: 'customer', notify: this.$notify }); this.loading = false; if (result === true) this.$router.push('/customer/beranda').catch(() => {}); else this.error = result && result.message || 'Login customer gagal.' },
  },
}
</script>

<style scoped>.customer-login{min-height:100vh;background:linear-gradient(135deg,#f4efff,#f7f8fc)}.login-card{width:100%;max-width:1050px;border-radius:22px}.hero-panel{min-height:610px;color:#fff;background:linear-gradient(145deg,#6f36d8,#9155fd 58%,#b47cff)}.hero-copy{color:rgba(255,255,255,.78);line-height:1.6}.login-btn{border-radius:11px;text-transform:none}@media(max-width:600px){.login-card{border-radius:16px}}
</style>
