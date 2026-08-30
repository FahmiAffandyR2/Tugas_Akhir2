<template>
  <div class="auth-wrapper auth-v1">
    <div class="auth-inner">
      <vue-element-loading :active="submitting" />
      <v-card class="auth-card">
        <v-card-title class="d-flex align-center justify-center py-7">
          <v-img :src="require('@/assets/images/logos/logo.png')" max-height="34" max-width="34" contain class="me-3" />
          <h2 class="text-2xl font-weight-semibold">Registrasi Driver</h2>
        </v-card-title>

        <v-card-text>
          <p class="mb-0">Buat akun untuk melihat jadwal dan menjalankan perjalanan.</p>
        </v-card-text>

        <v-card-text>
          <v-alert v-if="serverError" type="error" dense>{{ serverError }}</v-alert>
          <v-form ref="form" v-model="valid" lazy-validation @submit.prevent="register">
            <v-text-field v-model.trim="form.name" outlined label="Nama lengkap" :rules="nameRules" class="mb-2" />
            <v-text-field v-model.trim="form.email" outlined label="Email" type="email" :rules="emailRules" class="mb-2" />
            <v-text-field v-model.trim="form.tel_number" outlined label="Nomor telepon (opsional)" type="tel" class="mb-2" />
            <v-text-field
              v-model="form.password"
              outlined
              label="Password"
              :type="showPassword ? 'text' : 'password'"
              :append-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
              :rules="passwordRules"
              class="mb-2"
              @click:append="showPassword = !showPassword"
            />
            <v-text-field
              v-model="form.password_confirmation"
              outlined
              label="Konfirmasi password"
              :type="showPassword ? 'text' : 'password'"
              :rules="confirmationRules"
            />
            <v-checkbox v-model="accepted" :rules="[v => !!v || 'Anda harus menyetujui ketentuan']">
              <template #label>
                <span>
                  Saya menyetujui
                  <router-link to="/terms" target="_blank" @click.stop>syarat dan ketentuan</router-link>
                  serta
                  <router-link to="/privacy" target="_blank" @click.stop>kebijakan privasi</router-link>.
                </span>
              </template>
            </v-checkbox>
            <v-btn type="submit" block color="primary" class="mt-3" :loading="submitting">Daftar sebagai Driver</v-btn>
          </v-form>
        </v-card-text>

        <v-card-text class="text-center">
          Sudah memiliki akun? <router-link to="/login">Masuk di sini</router-link>
        </v-card-text>
      </v-card>
    </div>
    <img class="auth-mask-bg" height="173" :src="require(`@/assets/images/misc/mask-${$vuetify.theme.dark ? 'dark':'light'}.png`)">
  </div>
</template>

<script>
import VueElementLoading from 'vue-element-loading'
import AuthService from '@/services/AuthService'

export default {
  components: { VueElementLoading },
  data() {
    return {
      valid: true,
      submitting: false,
      showPassword: false,
      accepted: false,
      serverError: null,
      form: { name: '', email: '', tel_number: '', password: '', password_confirmation: '' },
      nameRules: [v => !!v || 'Nama wajib diisi'],
      emailRules: [v => !!v || 'Email wajib diisi', v => /.+@.+\..+/.test(v) || 'Format email tidak valid'],
      passwordRules: [v => !!v || 'Password wajib diisi', v => (v && v.length >= 8) || 'Password minimal 8 karakter'],
    }
  },
  computed: {
    confirmationRules() {
      return [
        v => !!v || 'Konfirmasi password wajib diisi',
        v => v === this.form.password || 'Konfirmasi password tidak sama',
      ]
    },
  },
  methods: {
    async register() {
      if (!this.$refs.form.validate()) return
      this.submitting = true
      this.serverError = null
      try {
        const response = await AuthService.registerDriver(this.form)
        this.$notify({ type: 'success', title: 'Registrasi berhasil', text: response.data.message })
        await this.$router.push({ path: '/login', query: { registered: '1' } })
      } catch (error) {
        const data = error.response && error.response.data
        const errors = data && data.errors
        this.serverError = errors
          ? Object.values(errors).reduce((all, messages) => all.concat(messages), []).join(' ')
          : (data && data.message) || 'Registrasi gagal. Silakan coba kembali.'
      } finally {
        this.submitting = false
      }
    },
  },
}
</script>

<style lang="scss">
@import '~@/plugins/vuetify/default-preset/preset/pages/auth.scss';
</style>
