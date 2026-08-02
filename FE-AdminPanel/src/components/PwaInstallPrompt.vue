<template>
  <v-dialog v-model="visible" max-width="430">
    <v-card class="install-card">
      <v-card-text class="pa-6">
        <div class="install-icon mb-4">
          <v-icon color="primary" size="34">mdi-cellphone-arrow-down</v-icon>
        </div>
        <h2 class="text-h6 font-weight-bold mb-2">Install aplikasi EZBus?</h2>
        <p class="grey--text mb-4">
          Buka EZBus lebih cepat dari layar utama perangkat tanpa mengetik alamat web lagi.
        </p>

        <v-alert v-if="showManualSteps" text dense color="primary" class="mb-0">
          <div class="font-weight-bold mb-1">Cara install</div>
          <div class="caption">{{ manualInstallText }}</div>
        </v-alert>
      </v-card-text>

      <v-card-actions class="px-6 pb-6 pt-0">
        <v-btn text color="grey darken-1" @click="dismiss">Tidak sekarang</v-btn>
        <v-spacer />
        <v-btn color="primary" depressed @click="install">
          {{ deferredPrompt ? 'Install' : 'Mengerti' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script>
const DISMISSED_KEY = 'ezbusPwaInstallDismissedAt'
const DISMISS_DURATION = 1000 * 60 * 60 * 24 * 7
const PROMPT_DELAY = 1200

function isStandalone() {
  return window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true
}

function isIos() {
  return /iphone|ipad|ipod/i.test(window.navigator.userAgent)
}

function isAndroid() {
  return /android/i.test(window.navigator.userAgent)
}

export default {
  data: () => ({
    deferredPrompt: null,
    visible: false,
    installPromptSupported: false,
  }),
  computed: {
    showManualSteps() {
      return !this.deferredPrompt
    },
    manualInstallText() {
      if (isIos()) return 'Tekan tombol Share di browser, lalu pilih Add to Home Screen.'
      if (isAndroid()) return 'Buka menu browser, lalu pilih Install app atau Add to Home screen.'
      return 'Buka menu browser, lalu pilih Install EZBus atau Create shortcut.'
    },
  },
  mounted() {
    window.addEventListener('beforeinstallprompt', this.onBeforeInstallPrompt)
    window.addEventListener('appinstalled', this.onAppInstalled)
    window.setTimeout(this.showManualInstallChoice, PROMPT_DELAY)
  },
  beforeDestroy() {
    window.removeEventListener('beforeinstallprompt', this.onBeforeInstallPrompt)
    window.removeEventListener('appinstalled', this.onAppInstalled)
  },
  methods: {
    onBeforeInstallPrompt(event) {
      event.preventDefault()
      this.deferredPrompt = event
      this.installPromptSupported = true

      if (isStandalone() || this.wasRecentlyDismissed()) return
      this.visible = true
    },
    showManualInstallChoice() {
      if (this.installPromptSupported || this.visible || isStandalone() || this.wasRecentlyDismissed()) return
      this.visible = true
    },
    async install() {
      if (!this.deferredPrompt) {
        this.visible = false
        return
      }

      this.visible = false
      this.deferredPrompt.prompt()
      await this.deferredPrompt.userChoice
      this.deferredPrompt = null
    },
    dismiss() {
      localStorage.setItem(DISMISSED_KEY, String(Date.now()))
      this.visible = false
    },
    onAppInstalled() {
      this.deferredPrompt = null
      this.visible = false
      localStorage.removeItem(DISMISSED_KEY)
    },
    wasRecentlyDismissed() {
      const dismissedAt = Number(localStorage.getItem(DISMISSED_KEY) || 0)
      return dismissedAt && Date.now() - dismissedAt < DISMISS_DURATION
    },
  },
}
</script>

<style scoped>
.install-card {
  border-radius: 16px;
}

.install-icon {
  width: 58px;
  height: 58px;
  border-radius: 16px;
  background: #f2eaff;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
