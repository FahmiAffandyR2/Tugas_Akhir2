<template>
  <div class="login-page">
    <vue-element-loading :active="submiting" />
    <v-card class="login-shell overflow-hidden" elevation="12">
      <v-row no-gutters>
        <v-col cols="12" md="6" class="login-hero">
          <div class="hero-overlay">
            <div class="brand-row">
              <v-img
                :src="require('@/assets/images/logos/logo.png')"
                max-height="42"
                max-width="42"
                alt="logo"
                contain
                class="mr-3"
              />
              <span class="text-h6 font-weight-bold">{{ systemName }}</span>
            </div>
            <div>
              <p class="overline font-weight-bold mb-2">SEWA BUS PARIWISATA</p>
              <h1 class="hero-title mb-4">Perjalanan rombongan terasa lebih mudah.</h1>
              <p class="hero-copy mb-0">Pesan bus, pantau status perjalanan, dan nikmati layanan armada yang siap menemani agenda Anda.</p>
            </div>
          </div>
        </v-col>

        <v-col cols="12" md="6" class="login-panel pa-7 pa-sm-10 pa-md-12">
          <div class="d-flex align-center mb-8 d-md-none">
            <v-img :src="require('@/assets/images/logos/logo.png')" max-width="40" contain class="mr-3" />
            <span class="text-h6 font-weight-bold">{{ systemName }}</span>
          </div>

          <p class="overline primary--text font-weight-bold mb-1">LOGIN</p>
          <h2 class="text-h4 font-weight-bold mb-2">Selamat datang</h2>
          <p class="grey--text mb-7">Masuk untuk memesan bus pariwisata dan memantau perjalanan Anda.</p>

          <v-alert v-if="loginError" type="error" dense dismissible @input="loginError = null">
            {{ loginError }}
          </v-alert>

          <v-form ref="form" v-model="valid" lazy-validation @submit.prevent="login">
            <v-text-field
              v-model.trim="email"
              outlined
              label="Email"
              placeholder="nama@email.com"
              required
              :rules="emailRules"
              prepend-inner-icon="mdi-email-outline"
              class="mb-3"
            />

            <v-text-field
              v-model="password"
              outlined
              :type="isPasswordVisible ? 'text' : 'password'"
              label="Password"
              placeholder="Masukkan password"
              :append-icon="isPasswordVisible ? icons.mdiEyeOffOutline : icons.mdiEyeOutline"
              required
              :rules="passRules"
              prepend-inner-icon="mdi-lock-outline"
              @click:append="isPasswordVisible = !isPasswordVisible"
            />

            <div class="d-flex align-center justify-space-between flex-wrap">
              <v-checkbox label="Remember Me" hide-details class="me-3 mt-1" />
              <router-link class="pt-1" to="/forgot-password">Forgot Password?</router-link>
            </div>

            <v-btn block large color="primary" class="login-btn mt-6" type="submit">
              Login
            </v-btn>

            <template v-if="canUseGoogleLogin">
              <div class="divider-row my-5">
                <span>atau</span>
              </div>

              <v-btn
                block
                large
                outlined
                color="primary"
                class="login-btn google-login-btn"
                :loading="googleLoading"
                @click="loginWithGoogle"
              >
                <v-icon left>mdi-google</v-icon>
                Masuk dengan Google
              </v-btn>
            </template>

            <div class="text-center mt-5">
              Belum memiliki akun customer?
              <router-link to="/customer/register" class="font-weight-bold">Daftar customer</router-link>
            </div>
          </v-form>
        </v-col>
      </v-row>
    </v-card>
  </div>
</template>

<script>
import { mdiEyeOutline, mdiEyeOffOutline } from '@mdi/js'
import { ref } from '@vue/composition-api'
import AuthService from "@/services/AuthService";
import VueElementLoading from "vue-element-loading";
import { Keys } from '/src/config.js';

export default {
  components: {
    VueElementLoading,
  },
  setup() {
    const isPasswordVisible = ref(false)
    const email = ref('')
    const password = ref('')

    return {
      isPasswordVisible,
      email,
      password,
      icons: {
        mdiEyeOutline,
        mdiEyeOffOutline,
      },
    }
  },
  data() {
    return {
      systemName: Keys.VUE_APP_SYSTEM_NAME,
      valid: true,
      loginError: null,
      submiting: false,
      googleLoading: false,
      canUseGoogleLogin: AuthService.canUseGoogleLogin(),
      emailRules: [
        v => !!v || 'E-mail is required',
        v => /.+@.+\..+/.test(v) || 'E-mail must be valid',
      ],
      passRules: [(v) => !!v || "Password is required"],
    };
  },
  methods: {
    redirectAfterLogin() {
      const role = Number(localStorage.getItem('internalRole') || localStorage.getItem('customerRole') || localStorage.getItem('userRole'))
      const destination = role === 1
        ? '/customer/beranda'
        : role === 2
          ? '/driver/beranda'
          : (this.$router.currentRoute.query.to || '/dashboard')

      this.$router.push(destination).catch(error => {
        if (error && error.name !== 'NavigationDuplicated') throw error
      })
    },
    validate() {
      return this.$refs.form.validate();
    },
    async login() {
      if (!this.validate()) return;
      const payload = {
        email: this.email,
        password: this.password,
        notify: this.$notify,
        portal: 'all',
      };
      this.loginError = null;
      try {
        this.submiting = true;
        const isLoggedIn = await AuthService.login(payload);
        this.submiting = false;
        if (isLoggedIn === true) {
          this.redirectAfterLogin()
        } else if (isLoggedIn && isLoggedIn.message) {
          this.loginError = isLoggedIn.message
        }
      } catch (error) {
        console.log(error);
        this.submiting = false;
      }
    },
    async loginWithGoogle() {
      this.loginError = null
      this.googleLoading = true
      const result = await AuthService.loginWithGoogle({
        notify: this.$notify,
        portal: 'all',
      })
      this.googleLoading = false

      if (result === 'redirecting') return

      if (result === true) {
        this.redirectAfterLogin()
      } else if (result && result.message) {
        this.loginError = result.message
      }
    },
  },
}
</script>

<style lang="scss" scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
  background: #f5f6fa;
}

.login-shell {
  width: 100%;
  max-width: 1120px;
  border-radius: 20px;
}

.login-hero {
  min-height: 680px;
  color: #fff;
  background-image: linear-gradient(90deg, rgba(18, 14, 28, .68), rgba(18, 14, 28, .16)), url('~@/assets/images/auth/bus-rental-hero.jpg');
  background-size: cover;
  background-position: center;
}

.hero-overlay {
  min-height: 680px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 42px;
}

.brand-row {
  display: flex;
  align-items: center;
}

.hero-title {
  max-width: 460px;
  font-size: 42px;
  line-height: 1.12;
  font-weight: 800;
}

.hero-copy {
  max-width: 440px;
  color: rgba(255, 255, 255, .86);
  font-size: 18px;
  line-height: 1.65;
}

.login-panel {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.login-btn {
  border-radius: 11px;
  text-transform: none;
}

.divider-row {
  display: flex;
  align-items: center;
  color: #7b8090;
  font-size: 13px;
}

.divider-row::before,
.divider-row::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #dfe3eb;
}

.divider-row span {
  padding: 0 14px;
}

.google-login-btn {
  background: #fff;
}

@media (max-width: 960px) {
  .login-hero,
  .hero-overlay {
    min-height: 320px;
  }

  .hero-overlay {
    padding: 28px;
  }

  .hero-title {
    font-size: 30px;
  }
}

@media (max-width: 600px) {
  .login-page {
    padding: 0;
  }

  .login-shell {
    min-height: 100vh;
    border-radius: 0;
  }

  .login-hero {
    display: none;
  }
}
</style>
