<template>
  <component :is="resolveLayout">
    <transition name="fade" mode="out-in">
      <router-view></router-view>
    </transition>
    <vue-progress-bar></vue-progress-bar>
    <notifications position="bottom right"/>
    <pwa-install-prompt />
  </component>
</template>

<script>
import { computed } from '@vue/composition-api'
import { useRouter } from '@/utils'
import LayoutBlank from '@/layouts/Blank.vue'
import LayoutContent from '@/layouts/Content.vue'
import LayoutDriver from '@/layouts/Driver.vue'
import LayoutCustomer from '@/layouts/Customer.vue'
import LayoutStaff from '@/layouts/Staff.vue'
import PwaInstallPrompt from '@/components/PwaInstallPrompt.vue'
import AuthService from '@/services/AuthService'

export default {
  components: {
    LayoutBlank,
    LayoutContent,
    LayoutDriver,
    LayoutCustomer,
    LayoutStaff,
    PwaInstallPrompt,
  },
  setup() {
    const { route } = useRouter()

    const resolveLayout = computed(() => {
      // Handles initial route
      if (route.value.name === null) return 'layout-blank'

      if (route.value.meta.layout === 'blank') return 'layout-blank'
      if (route.value.meta.layout === 'driver') return 'layout-driver'
      if (route.value.meta.layout === 'customer') return 'layout-customer'
      if (route.value.meta.layout === 'staff') return 'layout-staff'

      return 'layout-content'
    })

    return {
      resolveLayout,
    }
  },
mounted () {
    //  [App.vue specific] When App.vue is finish loading finish the progress bar
    this.$Progress.finish()
    window.addEventListener('beforeunload', this.confirmAppExit)
  },
  beforeDestroy() {
    window.removeEventListener('beforeunload', this.confirmAppExit)
  },
  created () {
    //  [App.vue specific] When App.vue is first loaded start the progress bar
    this.$Progress.start()
    //  hook the progress bar to start before we move router-view
    this.$router.beforeEach((to, from, next) => {
      //  does the page we want to go to have a meta.progress object
      if (to.meta.progress !== undefined) {
        let meta = to.meta.progress
        // parse meta tags
        this.$Progress.parseMeta(meta)
      }
      //  start the progress bar
      this.$Progress.start()
      //  continue to next page
      next()
    })
    //  hook the progress bar to finish after we've finished moving router-view
    this.$router.afterEach((to, from) => {
      //  finish the progress bar
      this.$Progress.finish()
    })
  },
  methods: {
    confirmAppExit(event) {
      if (!AuthService.isUserLoggedIn('all')) return

      event.preventDefault()
      event.returnValue = ''
    },
  }
}
</script>
<style>
.fade-enter-active, .fade-leave-active {
  transition: opacity .5s;
}
.fade-enter, .fade-leave-to /* .fade-leave-active below version 2.1.8 */ {
  opacity: 0;
}
</style>
