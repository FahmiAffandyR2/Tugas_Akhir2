<template>
  <div class="profile-page">
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold mb-2">Profil Saya</h1>
      <p class="grey--text mb-0">Periksa dan perbarui informasi akun Anda.</p>
    </div>

    <v-row>
      <v-col cols="12" md="4">
        <v-card flat class="profile-card pa-6 text-center">
          <v-avatar size="104" color="deep-purple lighten-5" class="mb-4">
            <span class="primary--text text-h3 font-weight-bold">{{ initials }}</span>
          </v-avatar>
          <h2 class="text-h6 font-weight-bold mb-1">{{ form.name || roleLabel }}</h2>
          <p class="grey--text mb-4">{{ form.email || 'Email belum tersedia' }}</p>
          <v-chip color="deep-purple lighten-5" text-color="primary"><v-icon small left>mdi-shield-account-outline</v-icon>{{ roleLabel }}</v-chip>
          <v-divider class="my-6" />
          <div class="d-flex align-center text-left"><v-icon color="success" class="mr-3">mdi-check-decagram</v-icon><div><div class="font-weight-medium">Akun aktif</div><div class="caption grey--text">Akun Anda dapat digunakan</div></div></div>
        </v-card>
      </v-col>

      <v-col cols="12" md="8">
        <v-card flat class="profile-card pa-5 pa-md-7">
          <v-alert v-if="success" type="success" text dense dismissible @input="success=null">{{ success }}</v-alert>
          <v-alert v-if="serverError" type="error" text dense dismissible @input="serverError=null">{{ serverError }}</v-alert>
          <v-form ref="form" v-model="valid" lazy-validation @submit.prevent="save">
            <h2 class="text-h6 font-weight-bold mb-5">Informasi pribadi</h2>
            <v-row>
              <v-col cols="12" sm="6"><v-text-field v-model.trim="form.name" outlined label="Nama lengkap" prepend-inner-icon="mdi-account-outline" :rules="nameRules" /></v-col>
              <v-col cols="12" sm="6"><v-text-field v-model.trim="form.email" outlined label="Email" type="email" prepend-inner-icon="mdi-email-outline" :rules="emailRules" /></v-col>
              <v-col cols="12"><v-text-field v-model.trim="form.tel_number" outlined label="Nomor telepon" type="tel" prepend-inner-icon="mdi-phone-outline" :rules="phoneRules" /></v-col>
            </v-row>

            <v-divider class="my-4" />
            <div class="d-flex align-center justify-space-between mb-5"><div><h2 class="text-h6 font-weight-bold mb-1">Ubah password</h2><p class="caption grey--text mb-0">Kosongkan jika tidak ingin mengganti password.</p></div><v-btn text small color="primary" @click="changePassword=!changePassword">{{ changePassword?'Batal':'Ubah' }}</v-btn></div>
            <v-expand-transition><v-row v-if="changePassword">
              <v-col cols="12"><v-text-field v-model="form.current_password" outlined label="Password saat ini" :type="showPassword?'text':'password'" prepend-inner-icon="mdi-lock-outline" :rules="currentPasswordRules" /></v-col>
              <v-col cols="12" sm="6"><v-text-field v-model="form.password" outlined label="Password baru" :type="showPassword?'text':'password'" prepend-inner-icon="mdi-lock-plus-outline" :rules="passwordRules" /></v-col>
              <v-col cols="12" sm="6"><v-text-field v-model="form.password_confirmation" outlined label="Konfirmasi password baru" :type="showPassword?'text':'password'" :append-icon="showPassword?'mdi-eye-off-outline':'mdi-eye-outline'" prepend-inner-icon="mdi-lock-check-outline" :rules="confirmationRules" @click:append="showPassword=!showPassword" /></v-col>
            </v-row></v-expand-transition>

            <div class="d-flex justify-end mt-3"><v-btn color="primary" large type="submit" :loading="loading"><v-icon left>mdi-content-save-outline</v-icon>Simpan Perubahan</v-btn></div>
          </v-form>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script>
import AuthService from '@/services/AuthService'
export default {
  data: () => ({
    valid:true, loading:false, changePassword:false, showPassword:false, success:null, serverError:null,
    form:{name:'',email:'',tel_number:'',current_password:'',password:'',password_confirmation:''},
    role:null,
    nameRules:[v=>!!v||'Nama wajib diisi'],
    emailRules:[v=>!!v||'Email wajib diisi',v=>/.+@.+\..+/.test(v)||'Format email tidak valid'],
    phoneRules:[v=>!v||/^[0-9+()\-\s]{8,30}$/.test(v)||'Nomor telepon tidak valid'],
  }),
  computed:{
    roleLabel(){return Number(this.role)===2?'Driver':'Customer'},
    initials(){const name=this.form.name||this.roleLabel;return name.split(' ').filter(Boolean).map(v=>v[0]).slice(0,2).join('').toUpperCase()},
    currentPasswordRules(){return this.changePassword?[v=>!!v||'Password saat ini wajib diisi']:[]},
    passwordRules(){return this.changePassword?[v=>!!v||'Password baru wajib diisi',v=>(v&&v.length>=8)||'Password minimal 8 karakter']:[]},
    confirmationRules(){return this.changePassword?[v=>!!v||'Konfirmasi password wajib diisi',v=>v===this.form.password||'Konfirmasi password tidak sama']:[]},
  },
  async created(){await this.loadProfile()},
  methods:{
    async loadProfile(){try{const response=await AuthService.getAuthUser();const user=response.data.data;this.form.name=user.name||'';this.form.email=user.email||'';this.form.tel_number=user.tel_number||'';this.role=user.role}catch(error){this.serverError='Data profil gagal dimuat.'}},
    async save(){if(!this.$refs.form.validate())return;this.loading=true;this.serverError=null;this.success=null;try{const payload={name:this.form.name,email:this.form.email,tel_number:this.form.tel_number};if(this.changePassword){payload.current_password=this.form.current_password;payload.password=this.form.password;payload.password_confirmation=this.form.password_confirmation}const response=await AuthService.updateProfile(payload);this.success=response.data.message;this.form.current_password='';this.form.password='';this.form.password_confirmation='';this.changePassword=false;this.$notify({type:'success',title:'Berhasil',text:response.data.message})}catch(error){const data=error.response&&error.response.data;const errors=data&&data.errors;this.serverError=errors?Object.values(errors).reduce((all,messages)=>all.concat(Array.isArray(messages)?messages:[messages]),[]).join(' '):(data&&data.message)||'Profil gagal diperbarui.'}finally{this.loading=false}},
  },
}
</script>

<style scoped>
.profile-page{max-width:1120px;margin:auto}.profile-card{border-radius:18px!important;border:1px solid #ececf3;height:100%}
</style>
