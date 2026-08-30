<template>
  <div class="login-page d-flex align-center justify-center">
    <v-card class="pa-8 text-center" max-width="420">
      <v-progress-circular v-if="loading" indeterminate color="primary" size="48" class="mb-4" />
      <v-icon v-else-if="success" color="success" size="64" class="mb-4">mdi-check-circle</v-icon>
      <v-icon v-else color="error" size="64" class="mb-4">mdi-alert-circle</v-icon>
      <h2 class="text-h6 font-weight-bold mb-2">{{ title }}</h2>
      <p class="grey--text">{{ message }}</p>
      <v-btn v-if="!loading" color="primary" class="mt-4" to="/login">Kembali ke Login</v-btn>
    </v-card>
  </div>
</template>

<script>
import AuthService from '@/services/AuthService'
export default {
  data() {
    return { loading: true, success: false, title: 'Memproses...', message: 'Mohon tunggu sebentar.' }
  },
  async created() {
    const params = new URLSearchParams(window.location.search)
    const code = params.get('code')
    const state = params.get('state')
    const error = params.get('error')

    if (error) {
      this.loading = false
      this.title = 'Login Gagal'
      this.message = error === 'access_denied' ? 'Anda membatalkan login Google.' : 'Terjadi kesalahan. Silakan coba lagi.'
      return
    }

    if (!code || !state) {
      this.loading = false
      this.title = 'Login Gagal'
      this.message = 'Parameter tidak valid. Silakan coba login kembali.'
      return
    }

    try {
      const result = await AuthService.handleGoogleCallback(code, state)
      if (result && result.success) {
        this.success = true
        this.title = 'Login Berhasil'
        this.message = 'Mengalihkan ke dashboard...'
        setTimeout(() => {
          const role = Number(localStorage.getItem('internalRole') || localStorage.getItem('customerRole'))
          const dest = role === 1 ? '/customer/beranda' : role === 2 ? '/driver/beranda' : '/dashboard'
          this.$router.push(dest).catch(() => {})
        }, 1000)
      } else {
        this.title = 'Login Gagal'
        this.message = (result && result.message) || 'Terjadi kesalahan. Silakan coba lagi.'
      }
    } catch (e) {
      this.title = 'Login Gagal'
      this.message = 'Terjadi kesalahan. Silakan coba lagi.'
    } finally {
      this.loading = false
    }
  },
}
</script>

<style scoped>
.login-page { min-height: 100vh; background: #f5f6fa; }
</style>
