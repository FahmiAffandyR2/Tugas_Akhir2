<template>
  <div class="customer-register d-flex align-center justify-center pa-4 py-8">
    <v-card class="register-card overflow-hidden" elevation="12">
      <v-row no-gutters>
        <v-col cols="12" md="5" class="hero-panel pa-8 pa-md-11 d-none d-md-flex flex-column justify-space-between">
          <div>
            <div class="d-flex align-center mb-10"><v-img :src="require('@/assets/images/logos/logo.png')" max-width="44" class="mr-3"/><span class="text-h6 font-weight-bold">EZBus Pariwisata</span></div>
            <h1 class="text-h3 font-weight-bold mb-5">Mulai perjalanan bersama kami.</h1>
            <p class="text-h6 hero-copy">Satu akun untuk merencanakan, memesan, dan memantau perjalanan rombongan Anda.</p>
          </div>
          <div>
            <div v-for="benefit in benefits" :key="benefit" class="d-flex align-center mb-3"><v-icon color="white" class="mr-3">mdi-check-circle-outline</v-icon><span>{{ benefit }}</span></div>
          </div>
        </v-col>
        <v-col cols="12" md="7" class="pa-7 pa-sm-10 pa-md-12">
          <div class="d-flex align-center d-md-none mb-7"><v-img :src="require('@/assets/images/logos/logo.png')" max-width="40" class="mr-3"/><span class="text-h6 font-weight-bold">Portal Customer</span></div>
          <p class="overline primary--text font-weight-bold mb-1">PORTAL CUSTOMER</p>
          <h2 class="text-h4 font-weight-bold mb-2">Buat akun customer</h2>
          <p class="grey--text mb-7">Lengkapi data berikut untuk mulai memesan bus pariwisata.</p>

          <v-alert v-if="serverError" type="error" dense text>{{ serverError }}</v-alert>
          <v-form ref="form" v-model="valid" lazy-validation @submit.prevent="register">
            <v-row dense>
              <v-col cols="12"><v-text-field v-model.trim="form.name" outlined label="Nama lengkap" prepend-inner-icon="mdi-account-outline" :rules="nameRules" /></v-col>
              <v-col cols="12" sm="6"><v-text-field v-model.trim="form.email" outlined label="Email" type="email" prepend-inner-icon="mdi-email-outline" :rules="emailRules" /></v-col>
              <v-col cols="12" sm="6"><v-text-field v-model.trim="form.tel_number" outlined label="Nomor telepon" type="tel" prepend-inner-icon="mdi-phone-outline" :rules="phoneRules" /></v-col>
              <v-col cols="12" sm="6"><v-text-field v-model="form.password" outlined label="Password" :type="showPassword?'text':'password'" :append-icon="showPassword?'mdi-eye-off-outline':'mdi-eye-outline'" prepend-inner-icon="mdi-lock-outline" :rules="passwordRules" @click:append="showPassword=!showPassword" /></v-col>
              <v-col cols="12" sm="6"><v-text-field v-model="form.password_confirmation" outlined label="Konfirmasi password" :type="showPassword?'text':'password'" prepend-inner-icon="mdi-lock-check-outline" :rules="confirmationRules" /></v-col>
            </v-row>
            <v-checkbox v-model="accepted" :rules="[v=>!!v||'Anda harus menyetujui ketentuan']" class="mt-0">
              <template #label><span class="body-2">Saya menyetujui <router-link to="/terms" target="_blank" @click.stop>syarat dan ketentuan</router-link> serta kebijakan privasi.</span></template>
            </v-checkbox>
            <v-btn type="submit" block large color="primary" class="register-btn mt-2" :loading="submitting">Daftar sebagai Customer</v-btn>
          </v-form>
          <div class="text-center mt-6">Sudah memiliki akun? <router-link to="/login" class="font-weight-bold">Masuk di sini</router-link></div>
        </v-col>
      </v-row>
    </v-card>
  </div>
</template>

<script>
import AuthService from '@/services/AuthService'
export default {
  data: () => ({
    valid: true, submitting: false, showPassword: false, accepted: false, serverError: null,
    form: { name:'', email:'', tel_number:'', password:'', password_confirmation:'' },
    benefits: ['Pemesanan bus lebih praktis', 'Penawaran harga transparan', 'Pantau status perjalanan'],
    nameRules: [v=>!!v||'Nama wajib diisi'],
    emailRules: [v=>!!v||'Email wajib diisi',v=>/.+@.+\..+/.test(v)||'Format email tidak valid'],
    phoneRules: [v=>!!v||'Nomor telepon wajib diisi',v=>/^[0-9+()\-\s]{8,30}$/.test(v)||'Nomor telepon tidak valid'],
    passwordRules: [v=>!!v||'Password wajib diisi',v=>(v&&v.length>=8)||'Password minimal 8 karakter'],
  }),
  computed: { confirmationRules() { return [v=>!!v||'Konfirmasi password wajib diisi',v=>v===this.form.password||'Konfirmasi password tidak sama'] } },
  methods: {
    async register() {
      if (!this.$refs.form.validate()) return
      this.submitting=true; this.serverError=null
      try {
        const response=await AuthService.registerCustomer(this.form)
        this.$notify({type:'success',title:'Registrasi berhasil',text:response.data.message})
        await this.$router.push({path:'/login',query:{registered:'1'}})
      } catch(error) {
        const data=error.response&&error.response.data; const errors=data&&data.errors
        this.serverError=errors?Object.values(errors).reduce((all,messages)=>all.concat(messages),[]).join(' '):(data&&data.message)||'Registrasi gagal. Silakan coba kembali.'
      } finally { this.submitting=false }
    },
  },
}
</script>

<style scoped>
.customer-register{min-height:100vh;background:#f5f6fa}.register-card{width:100%;max-width:1120px;border-radius:22px}.hero-panel{min-height:690px;color:#fff;background-image:linear-gradient(90deg,rgba(18,14,28,.72),rgba(18,14,28,.22)),url('~@/assets/images/auth/bus-rental-hero.jpg');background-size:cover;background-position:center}.hero-copy{color:rgba(255,255,255,.86);line-height:1.6}.register-btn{border-radius:11px;text-transform:none}@media(max-width:600px){.register-card{border-radius:16px}}
</style>
