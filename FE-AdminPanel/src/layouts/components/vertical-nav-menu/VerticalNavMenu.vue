<template>
  <v-navigation-drawer
    :value="isDrawerOpen"
    app
    floating
    width="260"
    class="app-navigation-menu"
    :right="$vuetify.rtl"
    @input="val => $emit('update:is-drawer-open', val)"
  >
    <!-- Navigation Header -->
    <div class="vertical-nav-header d-flex items-center ps-6 pe-5 pt-5 pb-2">
      <router-link
        to="/"
        class="d-flex align-center text-decoration-none"
      >
        <v-img
          :src="require('@/assets/images/logos/logo.png')"
          max-height="30px"
          max-width="30px"
          alt="logo"
          contain
          eager
          class="app-logo me-3"
        ></v-img>
        <v-slide-x-transition>
          <h2 class="app-title text--primary">
            {{ systemName }}
          </h2>
        </v-slide-x-transition>
      </router-link>
    </div>

    <!-- Navigation Items -->
    <v-list
      expand
      shaped
      class="vertical-nav-menu-items pr-5"
    >
      <nav-menu-link
        title="Dashboard"
        :to="{ name: 'dashboard' }"
        :icon="icons.mdiHomeOutline"
      ></nav-menu-link>
      <nav-menu-section-title title="USERS"></nav-menu-section-title>
      <nav-menu-group
        title="Users"
        icon="mdi-account-group"
      >
      <nav-menu-link
        title="Customers"
        :to="{ name: 'customers' }"
        icon="mdi-account"
      ></nav-menu-link>
      <nav-menu-link
        title="Drivers"
        :to="{ name: 'drivers' }"
        icon="mdi-account-tie-hat"
      ></nav-menu-link>
      </nav-menu-group>
      <nav-menu-section-title title="SYSTEM SETUP"></nav-menu-section-title>
      <nav-menu-link
        title="Buses"
        :to="{ name: 'buses' }"
        icon="mdi-bus-multiple"
      ></nav-menu-link>
      <nav-menu-link
        title="Fleet Depot"
        :to="{ name: 'fleet-depots' }"
        icon="mdi-truck"
      ></nav-menu-link>
      <nav-menu-link
        title="Shift Driver"
        :to="{ name: 'driver-shifts' }"
        icon="mdi-calendar-clock"
      ></nav-menu-link>
      <nav-menu-link
        title="Routes"
        :to="{ name: 'routes' }"
        icon="mdi-road-variant"
      ></nav-menu-link>
      <nav-menu-link
        title="Stops"
        :to="{ name: 'stops' }"
        icon="mdi-bus-stop"
      ></nav-menu-link>
      <nav-menu-link
        title="Jadwal Perjalanan"
        :to="{ name: 'trips' }"
        icon="mdi-bus-clock"
      ></nav-menu-link>
      <nav-menu-section-title title="LIVE"></nav-menu-section-title>
      <nav-menu-link
        title="Live Tracking"
        :to="{ name: 'live-tracking' }"
        icon="mdi-crosshairs-gps"
        ></nav-menu-link>
      <nav-menu-section-title title="REPORTS"></nav-menu-section-title>
      <nav-menu-link
        v-if="false"
        title="Driver Analytics"
        :to="{ name: 'driver-analytics' }"
        icon="mdi-chart-bar"
      ></nav-menu-link>
      <nav-menu-link
        v-if="false"
        title="Audit Log"
        :to="{ name: 'audit-logs' }"
        icon="mdi-history"
      ></nav-menu-link>
      <nav-menu-link
        v-if="false"
        title="Laporan Keuangan"
        :to="{ name: 'financial-reports' }"
        icon="mdi-cash-multiple"
      ></nav-menu-link>
      <nav-menu-link
        v-if="adminProfileStore.customerLocationsCount > 0"
        title="Customer Locations"
        :to="{ name: 'customer-locations' }"
        icon="mdi-map-marker-account"
      ></nav-menu-link>
      <nav-menu-link
        title="Booking Bus"
        :to="{ name: 'charter-bookings' }"
        icon="mdi-clipboard-text-clock-outline"
      ></nav-menu-link>
      <nav-menu-link
        title="Time Table"
        :to="{ name: 'planned-trips' }"
        icon="mdi-airplane-clock"
      ></nav-menu-link>
      <nav-menu-section-title title="SETTINGS"></nav-menu-section-title>
      <nav-menu-link
        title="Settings"
        :to="{ name: 'settings' }"
        icon="mdi-cog"
      ></nav-menu-link>
      <nav-menu-link
        v-if="false"
        title="Template Notifikasi"
        :to="{ name: 'notification-templates' }"
        icon="mdi-file-document-edit"
      ></nav-menu-link>
      <nav-menu-link
        v-if="false"
        title="Kirim Notifikasi"
        :to="{ name: 'notifications' }"
        icon="mdi-bell-ring"
      ></nav-menu-link>
      <!-- Privacy policy -->
      <nav-menu-link
        title="Privacy Policy"
        :to="{ name: 'privacy-policy' }"
        icon="mdi-shield-account"
      ></nav-menu-link>
      <!-- Terms and Conditions -->
      <nav-menu-link
        title="Terms & Conditions"
        :to="{ name: 'terms-and-conditions' }"
        icon="mdi-file-document-outline"
      ></nav-menu-link>
      <br/>
    </v-list>
  </v-navigation-drawer>
</template>

<script>
// eslint-disable-next-line object-curly-newline
import {
  mdiHomeOutline,
  mdiAlphaTBoxOutline,
  mdiEyeOutline,
  mdiCreditCardOutline,
  mdiTable,
  mdiFileOutline,
  mdiFormSelect,
  mdiAccountCogOutline,
  mdiMapMarkerPath,
  mdiCrosshairsGps
} from '@mdi/js'
import NavMenuSectionTitle from './components/NavMenuSectionTitle.vue'
import NavMenuGroup from './components/NavMenuGroup.vue'
import NavMenuLink from './components/NavMenuLink.vue'
import {Keys} from '/src/config.js'
import { adminProfileStore } from '@/utils/helpers'

export default {
  components: {
    NavMenuSectionTitle,
    NavMenuGroup,
    NavMenuLink,
    Keys
  },
  props: {
    isDrawerOpen: {
      type: Boolean,
      default: null,
    },
  },
  setup() {
    return {
      icons: {
        mdiHomeOutline,
        mdiAlphaTBoxOutline,
        mdiEyeOutline,
        mdiCreditCardOutline,
        mdiTable,
        mdiFileOutline,
        mdiFormSelect,
        mdiAccountCogOutline,
        mdiMapMarkerPath,
        mdiCrosshairsGps
      },
      systemName: Keys.VUE_APP_SYSTEM_NAME,
      adminProfileStore,
    }
  },
}
</script>

<style lang="scss" scoped>
.app-title {
  font-size: 1.25rem;
  font-weight: 700;
  font-stretch: normal;
  font-style: normal;
  line-height: normal;
  letter-spacing: 0.3px;
}

// ? Adjust this `translateX` value to keep logo in center when vertical nav menu is collapsed (Value depends on your logo)
.app-logo {
  transition: all 0.18s ease-in-out;
  .v-navigation-drawer--mini-variant & {
    transform: translateX(-4px);
  }
}

@include theme(app-navigation-menu) using ($material) {
  background-color: map-deep-get($material, 'background');
}

.app-navigation-menu {
  .v-list-item {
    &.vertical-nav-menu-link {
      ::v-deep .v-list-item__icon {
        .v-icon {
          transition: none !important;
        }
      }
    }
  }
}

// You can remove below style
// Upgrade Banner
.app-navigation-menu {
  .upgrade-banner {
    position: absolute;
    bottom: 13px;
    left: 50%;
    transform: translateX(-50%);
  }
}
</style>
