<template>
  <div>
    <vue-element-loading :active="isSubmit" />
    <v-card>
      <v-card-title>
      <v-icon color="primary">
        {{getIcon(userType)}}
      </v-icon>
        <span class="pl-2">{{capitalizeFirstLetter(userType)}}</span>
        <v-spacer></v-spacer>
        <v-btn
          v-if="userType === 'drivers'"
          color="primary"
          @click="openCreateDriverDialog"
        >
          <v-icon left>mdi-plus</v-icon>
          Tambah Driver
        </v-btn>
      </v-card-title>
      <v-tabs v-model="active_tab" show-arrows class="my-2">
        <v-tab v-for="tab in tabs" :key="tab.idx">
          <v-icon size="20" class="me-3">
            {{ tab.icon }}
          </v-icon>
          <span>{{ tab.title }}</span>
        </v-tab>
      </v-tabs>
      <!-- tabs item -->
      <v-tabs-items  v-model="active_tab">
        <!-- active -->
        <v-tab-item>
          <users-table :users="activeUsers" :userType="userType"
          :tab="active_tab"
          @view-user="viewUser" @edit-user="editUser" @suspend-user="suspendActivateUser"
          @unassign-bus="unAssignBus" @assign-bus="assignBus"></users-table>
        </v-tab-item>

        <!-- suspended -->
        <v-tab-item>
          <users-table :users="suspendedUsers" :userType="userType"
          :tab="active_tab"
          @view-user="viewUser" @edit-user="editUser" @suspend-user="suspendActivateUser"
          @unassign-bus="unAssignBus" @assign-bus="assignBus"></users-table>
        </v-tab-item>

        <v-tab-item>
          <users-table v-if="userType === 'drivers'" :users="underReviewUsers"
          :userType="userType"
          :tab="active_tab"
          @view-user="viewUser"
          ></users-table>
        </v-tab-item>

      </v-tabs-items>
    </v-card>
    <v-dialog v-model="createDriverDialog" max-width="520" persistent>
      <v-card>
        <v-card-title>
          <v-icon color="primary" class="mr-2">mdi-account-plus</v-icon>
          Tambah Driver
        </v-card-title>
        <v-card-text>
          <v-form ref="createDriverForm" v-model="createDriverValid" @submit.prevent="createDriver">
            <v-text-field
              v-model="newDriver.name"
              label="Nama driver"
              :rules="[driverRules.required]"
              autocomplete="name"
              outlined
              dense
            ></v-text-field>
            <v-text-field
              v-model="newDriver.email"
              label="Email login"
              :rules="[driverRules.required, driverRules.email]"
              autocomplete="email"
              outlined
              dense
            ></v-text-field>
            <v-text-field
              v-model="newDriver.tel_number"
              label="Nomor telepon"
              autocomplete="tel"
              outlined
              dense
            ></v-text-field>
            <v-text-field
              v-model="newDriver.password"
              label="Password"
              :type="showDriverPassword ? 'text' : 'password'"
              :append-icon="showDriverPassword ? 'mdi-eye-off' : 'mdi-eye'"
              :rules="[driverRules.required, driverRules.password]"
              autocomplete="new-password"
              outlined
              dense
              @click:append="showDriverPassword = !showDriverPassword"
            ></v-text-field>
            <v-text-field
              v-model="newDriver.password_confirmation"
              label="Konfirmasi password"
              :type="showDriverPassword ? 'text' : 'password'"
              :rules="[
                driverRules.required,
                value => value === newDriver.password || 'Konfirmasi password tidak sama',
              ]"
              autocomplete="new-password"
              outlined
              dense
            ></v-text-field>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn text @click="closeCreateDriverDialog">Batal</v-btn>
          <v-btn color="primary" :loading="isSubmit" @click="createDriver">
            Simpan Driver
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
    <v-dialog v-if="selectedDriver" v-model="busesDialog" max-width="390">
      <v-card>
        <v-card-title class="text-h5"> Select bus for '{{ selectedDriver.name}}' </v-card-title>

        <v-card-text>
          <v-list dense>
            <v-subheader>Buses</v-subheader>
            <v-list-item-group>
              <v-list-item
                v-for="(bus, i) in availableBuses"
                :key="i"
              >
                <v-list-item-content @click="assignBusToDriver(bus)">
                  <v-list-item-title v-text="'License: ' + bus.license"></v-list-item-title>
                  <v-list-item-subtitle v-text="'Capacity: ' + bus.capacity"></v-list-item-subtitle>
                </v-list-item-content>
              </v-list-item>
            </v-list-item-group>
          </v-list>
        </v-card-text>
        <v-container style="height: 400px">
          <v-row
            v-show="loadingBuses"
            class="fill-height"
            align-content="center"
            justify="center"
          >
            <v-col class="text-subtitle-1 text-center" cols="12">
              Please wait ...
            </v-col>
            <v-col cols="6">
              <v-progress-linear
                :active="loadingBuses"
                color="primary"
                indeterminate
                rounded
                height="6"
              ></v-progress-linear>
            </v-col>
          </v-row>
        </v-container>
        <v-card-actions>
          <v-spacer></v-spacer>

          <v-btn
            color="green darken-1"
            text
            @click="closeBusDialog"
          >
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>

  import usersTable from './users-table.vue';
import auth from '@/services/AuthService'
  import {
    mdiAccountCheck,
    mdiAccountOff,
    mdiAirplane,
    mdiMotionPause,
    mdiAccountClock,
    mdiAccountQuestion
  } from '@mdi/js'

import VueElementLoading from "vue-element-loading";

export default {
  components: {
    VueElementLoading,
    usersTable
  },
  data() {
    return {
      userType:'',
      users: [],
      activeUsers: [],
      suspendedUsers: [],
      underReviewUsers: [],
      availableBuses: [],
      dialog: false,
      createDriverDialog: false,
      createDriverValid: false,
      busesDialog: false,
      loadingBuses: false,
      isLoading: false,
      isSubmit: false,
      selectedUser: null,
      selectedDriver: null,
      showDriverPassword: false,
      newDriver: {
        name: '',
        email: '',
        tel_number: '',
        password: '',
        password_confirmation: '',
      },
      driverRules: {
        required: value => !!value || 'Wajib diisi',
        email: value => /.+@.+\..+/.test(value) || 'Email tidak valid',
        password: value => (value && value.length >= 8) || 'Password minimal 8 karakter',
      },
      tabs: [],
      driversTabs: [
        { idx: 0, title: "Active", icon: mdiAirplane },
        { idx: 1, title: "Suspended", icon: mdiMotionPause },
        { idx: 2, title: "Pending / Under Review", icon: mdiAccountClock },
      ],
      customersTabs: [
        { idx: 0, title: "Active", icon: mdiAirplane },
        { idx: 1, title: "Suspended", icon: mdiMotionPause },
      ],
      active_tab: null,
    };
  },
  watch:{
      $route (to, from){
          this.userType = to.name;
          this.updateTabs();
          if(this.userType === 'drivers')
          {
            this.active_tab = parseInt(localStorage.tabIdxDrivers);
          }
          else if(this.userType === 'customers')
          {
            this.active_tab = parseInt(localStorage.tabIdxCustomers);
          }
          this.loadUsers();
      },
      active_tab: function (newVal, oldVal) {
        if(this.userType === 'drivers')
        {
          localStorage.tabIdxDrivers = newVal;
        }
        else if(this.userType === 'customers')
        {
          localStorage.tabIdxCustomers = newVal;
        }
      },
  },
  mounted() {
    this.userType = this.$router.currentRoute.name
    this.updateTabs();
    if(this.userType === 'drivers')
    {
      this.active_tab = parseInt(localStorage.tabIdxDrivers);
    }
    else if(this.userType === 'customers')
    {
      this.active_tab = parseInt(localStorage.tabIdxCustomers);
    }
    this.loadUsers();
    //load buses for drivers
    if(this.userType === 'drivers'){
      this.loadAvailableBuses();
    }
  },
  methods: {
    updateTabs()
    {
      if(this.userType === 'drivers')
      {
        this.tabs = this.driversTabs;
      }
      else
      {
        this.tabs = this.customersTabs;
      }
    },
    capitalizeFirstLetter(string) {
      return string.charAt(0).toUpperCase() + string.slice(1);
    },
    getIcon(userType) {
      switch (userType) {
        case 'admin':
          return 'mdi-account-lock'
          break;
        case 'customers':
          return 'mdi-account'
          break;
        case 'drivers':
          return 'mdi-account-tie-hat'
          break;
        default:
          break;
      }
    },
    loadUsers() {
      this.isLoading = true;
      this.users = [];
      axios
        .get('/users/all', {params: {
            userType: this.userType
          }
        })
        .then((response) => {
          this.users = response.data;
          this.activeUsers = this.users.filter(user => user.status_id === 1);
          this.suspendedUsers = this.users.filter(user => user.status_id === 3);
          this.underReviewUsers = this.users.filter(user => user.status_id === 2 || user.status_id === 4);
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while retrieving users",
            type: "error",
          });
          console.log(error);
          auth.checkError(error.response.data.message, this.$router, this.$swal);
        })
        .then(() => {
          this.isLoading = false;
        });
    },
    openCreateDriverDialog() {
      this.createDriverDialog = true;
    },
    closeCreateDriverDialog() {
      this.createDriverDialog = false;
      this.showDriverPassword = false;
      this.newDriver = {
        name: '',
        email: '',
        tel_number: '',
        password: '',
        password_confirmation: '',
      };
      this.$nextTick(() => {
        if (this.$refs.createDriverForm) this.$refs.createDriverForm.resetValidation();
      });
    },
    getErrorMessage(error) {
      const data = error && error.response ? error.response.data : null;
      if (data && data.errors) {
        const firstKey = Object.keys(data.errors)[0];
        if (firstKey && data.errors[firstKey] && data.errors[firstKey][0]) {
          return data.errors[firstKey][0];
        }
      }
      return (data && data.message) || (error && error.message) || 'Terjadi kesalahan.';
    },
    createDriver() {
      if (this.$refs.createDriverForm && !this.$refs.createDriverForm.validate()) return;

      this.isSubmit = true;
      auth.registerDriver({
        name: this.newDriver.name,
        email: this.newDriver.email,
        tel_number: this.newDriver.tel_number,
        password: this.newDriver.password,
        password_confirmation: this.newDriver.password_confirmation,
      })
        .then((response) => {
          this.$notify({
            title: "Success",
            text: response.data.message || "Driver berhasil dibuat",
            type: "success",
          });
          this.active_tab = 0;
          this.closeCreateDriverDialog();
          this.loadUsers();
        })
        .catch((error) => {
          const message = this.getErrorMessage(error);
          this.$notify({
            title: "Error",
            text: message,
            type: "error",
          });
          this.$swal("Error", message, "error");
        })
        .then(() => {
          this.isSubmit = false;
        });
    },
    viewUser(user)
    {
      this.$router.push({
        name: "view-user",
        params: {
          user_id: user.id,
        },
      });
    },
    editUser(user)
    {
      this.$router.push({
        name: "edit-user",
        params: {
          user_id: user.id,
        },
      });
    },
    suspendActivateUser(user, index) {
      if (user.status_id == 1) {
        // Suspending - ask for reason
        this.$swal
          .fire({
            title: "Suspend user",
            text: "Are you sure to suspend the user '" + user.name + "' for 3 days?",
            icon: "warning",
            input: "text",
            inputLabel: "Alasan suspensi (opsional)",
            inputPlaceholder: "Contoh: Pelanggaran peraturan",
            showCancelButton: true,
            confirmButtonText: "Ya, Suspend",
            confirmButtonColor: "#d33",
          })
          .then((result) => {
            if (result.isConfirmed) {
              this.suspendActivateUserServer(user, index, result.value || null);
            }
          });
      } else {
        // Activating
        this.$swal
          .fire({
            title: "Activate user",
            text: "Are you sure to activate the user '" + user.name + "'?",
            icon: "success",
            showCancelButton: true,
            confirmButtonText: "Yes",
          })
          .then((result) => {
            if (result.isConfirmed) {
              this.suspendActivateUserServer(user, index, null);
            }
          });
      }
    },
    suspendActivateUserServer(user, indexx, reason) {
      this.isSubmit = true;
      axios
        .post('/users/suspend-activate', {
          user_id: user.id,
          reason: reason,
        })
        .then((response) => {
          this.isSubmit = false;
          let index = this.users.indexOf(user);
          this.users[index].status_id = user.status_id != 1 ? 1 : 3;
          if (user.status_id == 1) {
            // Was active, now suspended
            this.users[index].suspended_until = new Date(Date.now() + 3 * 24 * 60 * 60 * 1000).toISOString();
          } else {
            // Was suspended, now active
            this.users[index].suspended_until = null;
            this.users[index].suspension_reason = null;
          }
          this.activeUsers = this.users.filter(u => u.status_id === 1);
          this.suspendedUsers = this.users.filter(u => u.status_id === 3);
          this.$notify({
            title: "Success",
            text: "User " + (user.status_id != 1 ? "activated" : "suspended") + " successfully",
            type: "success",
          });
        })
        .catch((error) => {
          this.isSubmit = false;
          this.$notify({
            title: "Error",
            text: "Error",
            type: "error",
          });
          this.$swal("Error", error.response.data.message, "error");
        });
    },
    loadAvailableBuses() {
      this.loadingBuses = true;
      this.availableBuses = [];
      axios
        .get('/drivers/available-buses')
        .then((response) => {
          this.availableBuses = response.data;
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while retrieving buses",
            type: "error",
          });
          console.log(error);
          this.$swal("Error", error.response.data.message, "error");
        })
        .then(() => {
          this.loadingBuses = false;
        });
    },
    assignBus(item) {
      this.selectedDriver = item;
      this.busesDialog = true;
      this.loadAvailableBuses()
    },
    closeBusDialog() {
      this.busesDialog = false;
      this.loadingBuses = false;
      this.availableBuses = [];
    },
    assignBusToDriver(bus) {
      this.loadingBuses = true;
      axios
        .post('/drivers/assign-bus', {
          driver_id: this.selectedDriver.id,
          bus_id: bus.id,
        })
        .then((response) => {
          console.log(response);
          this.loadingBuses = false;
          this.selectedDriver.bus = bus;
          this.closeBusDialog();
          this.$notify({
            title: "Success",
            text: "Bus assigned to driver",
            type: "success",
          });
        })
        .catch((error) => {
          this.isSubmit = false;
          this.$notify({
            title: "Error",
            text: "Error",
            type: "error",
          });
          console.log(error);
          this.$swal("Error", error.response.data.message, "error");
        })
        .then(() => {
          this.closeBusDialog();
        });
    },
    unAssignBus(item)
    {
      this.$swal
        .fire({
          title: "Un-assign bus",
          text: "Are you sure to un-assign the driver ' " + item.name + " ' from the bus '" + item.bus.license + "' ? You won't be able to revert this!",
          icon: "error",
          showCancelButton: true,
          confirmButtonText: "Yes, delete it!",
        })
        .then((result) => {
          if (result.isConfirmed) {
            this.unassignBusFromDriver(item);
          }
        });
    },
    unassignBusFromDriver(driver) {
      this.isLoading = true;
      axios
        .post('/drivers/unassign-bus', {
          driver_id: driver.id,
        })
        .then((response) => {
          this.isLoading = false;
          driver.bus = null;
          this.$notify({
            title: "Success",
            text: "Bus unassigned from driver",
            type: "success",
          });
        })
        .catch((error) => {
          this.isSubmit = false;
          this.$notify({
            title: "Error",
            text: "Error",
            type: "error",
          });
          console.log(error);
          this.$swal("Error", error.response.data.message, "error");
        })
        .then(() => {
          this.closeBusDialog();
        });
    },
  },
};
</script>
