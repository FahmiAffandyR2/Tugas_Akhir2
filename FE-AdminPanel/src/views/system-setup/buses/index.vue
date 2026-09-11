<template>
  <div>
    <v-card>
      <v-card-title>
      <v-icon color="primary">
        mdi-bus-multiple
      </v-icon>
        <span class="pl-2">Buses</span>
        <v-spacer></v-spacer>
        <create-button @create="showBusDialog"></create-button>
        <activation-tool-tip model="buses"></activation-tool-tip>
      </v-card-title>
      <v-data-table
        item-key="id"
        :loading="isLoading"
        loading-text="Loading... Please wait"
        :headers="headers"
        :items="buses"
        :search="search"
      >
        <template v-slot:top>
          <v-text-field
            v-model="search"
            label="Search"
            class="mx-4"
          ></v-text-field>
        </template>
        <template v-slot:item.driver="{ item }">
          <div>
            <v-chip :color="getDriverAssignmentColor(item.driver)" dark @click="assignDriver(item)">
              {{ getDriver(item.driver) }}
            </v-chip>
            <div v-if="item.driver && item.driver.status_id == 3" class="mt-1">
              <v-alert type="warning" dense text class="mb-0" style="font-size:11px;">
                Ditangguhkan{{ item.driver.suspended_until ? ' sampai ' + formatSuspendedUntil(item.driver.suspended_until) : '' }}
                <br v-if="item.driver.suspension_reason" />
                <small v-if="item.driver.suspension_reason">Alasan: {{ item.driver.suspension_reason }}</small>
              </v-alert>
            </div>
          </div>
        </template>
        <template v-slot:item.depot="{ item }">
          <v-chip v-if="item.depot" small color="purple lighten-5" text-color="primary"><v-icon left x-small>mdi-garage-variant</v-icon>{{ item.depot.name }}</v-chip>
          <span v-else class="grey--text">Belum ditentukan</span>
        </template>
        <template v-slot:item.created_at="{ item }">
          <small>{{ item.created_at | moment("LL") }}</small> -
          <small class="text-muted">{{ item.created_at | moment("LT") }}</small>
        </template>
        <template v-slot:item.actions="{ item }">
          <v-icon v-if="item.driver" small class="mr-2" @click="unAssignDriver(item)">
            mdi-account-off
          </v-icon>
          <v-icon v-else small class="mr-2" @click="assignDriver(item)">
            mdi-account-tie-hat
          </v-icon>
          <v-icon small class="mr-2" @click="editBus(item)">
            mdi-pencil
          </v-icon>
          <v-icon small @click="deleteBus(item, buses.indexOf(item))">
            mdi-delete
          </v-icon>
        </template>
      </v-data-table>
    </v-card>
    <v-row justify="center">
      <v-dialog
        v-model="busDialog"
        persistent
        max-width="900px"
      >
        <v-form
          ref="form"
          v-model="valid"
          lazy-validation>
          <v-card>
            <v-card-title>
              <span class="text-h5">Bus data</span>
            </v-card-title>
            <v-card-text>
              <v-container>
                <v-row>
                  <v-col
                    cols="12"
                    sm="6"
                    md="3"
                  >
                    <v-text-field
                      v-model.trim="fleetNumber"
                      :rules="fleetNumberRules"
                      label="Nomor armada*"
                      hint="Contoh: 72"
                      required
                    ></v-text-field>
                  </v-col>
                  <v-col
                    cols="12"
                    sm="6"
                    md="3"
                  >
                    <v-text-field
                      v-model="license"
                      :rules="licenseRules"
                      label="License plate*"
                      hint="license plate of the bus"
                      required
                    ></v-text-field>
                  </v-col>
                  <v-col cols="12" sm="6" md="3">
                    <v-select
                      v-model="depotId"
                      :items="depots"
                      item-text="name"
                      item-value="id"
                      clearable
                      label="Depo armada"
                      hint="Lokasi asal bus"
                      persistent-hint
                    ></v-select>
                  </v-col>
                  <v-col cols="12" sm="6" md="3">
                    <v-select
                      v-model="busTypeId"
                      :items="busTypes"
                      item-text="name"
                      item-value="id"
                      :rules="busTypeRules"
                      label="Kategori bus*"
                      hint="Kapasitas mengikuti kategori"
                      persistent-hint
                      required
                      @change="applySelectedBusType"
                    ></v-select>
                  </v-col>
                  <v-col
                    cols="12"
                    sm="6"
                    md="3"
                  >
                    <v-text-field
                      v-model="capacity"
                      label="Capacity"
                      hint="number of seats in the bus"
                      disabled
                    ></v-text-field>
                  </v-col>
                  <v-col
                    cols="12"
                    sm="6"
                    md="3"
                  >
                    <v-text-field
                      v-model="priceFactor"
                      label="Pricing Factor"
                      hint="Diambil dari kategori bus"
                      disabled
                    ></v-text-field>
                  </v-col>
                  <v-col cols="12" sm="6" md="3">
                    <v-switch
                      v-model="isActive"
                      label="Bus aktif"
                    ></v-switch>
                  </v-col>
                </v-row>
              </v-container>
            </v-card-text>
            <v-card-actions>
              <v-spacer></v-spacer>
              <v-btn
                color="blue darken-1"
                text
                @click="busDialog = false"
              >
                Close
              </v-btn>
              <v-btn
                color="blue darken-1"
                text
                @click="createBus"
              >
                Save
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-form>
      </v-dialog>
    </v-row>
    <v-dialog v-if="selectedBus" v-model="driversDialog" max-width="390">
      <v-card>
        <v-card-title class="text-h5"> Select driver for '{{ selectedBus.license}}' </v-card-title>

        <v-card-text>
          <v-list dense>
            <v-subheader>Drivers</v-subheader>
            <v-list-item-group>
              <v-list-item
                v-for="(driver, i) in availableDrivers"
                :key="i"
              >
                <v-list-item-content>
                  <v-list-item-title v-text="driver.name" @click="assignDriverToBus(driver)"></v-list-item-title>
                </v-list-item-content>
              </v-list-item>
            </v-list-item-group>
          </v-list>
        </v-card-text>
        <v-container style="height: 400px">
          <v-row
            v-show="loadingDrivers"
            class="fill-height"
            align-content="center"
            justify="center"
          >
            <v-col class="text-subtitle-1 text-center" cols="12">
              Please wait ...
            </v-col>
            <v-col cols="6">
              <v-progress-linear
                :active="loadingDrivers"
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
            @click="closeDriverDialog"
          >
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
import ActivationToolTip from "@/components/ActivationToolTip";
import CreateButton from "@/components/CreateButton";
import auth from '@/services/AuthService'
export default {
  components: {
    ActivationToolTip,
    CreateButton,
  },
  data() {
    return {
      buses: [],
      depots: [],
      busTypes: [],
      availableDrivers: [],
      isLoading: false,
      search: "",
      busDialog: false,
      driversDialog: false,
      loadingDrivers: false,
      valid: true,
      id: null,
      selectedBus: null,
      depotId: null,
      busTypeId: null,
      fleetNumber: '',
      license: '',
      isActive: true,
      fleetNumberRules: [
        v => !!v || 'Nomor armada wajib diisi',
        v => (v && v.length <= 30) || 'Nomor armada maksimal 30 karakter',
      ],
      licenseRules: [
        v => !!v || 'License plate is required',
        v => (v && v.length <= 15) || 'License plate must be less than 15 characters',
      ],
      busTypeRules: [
        v => !!v || 'Kategori bus wajib dipilih',
      ],
      capacity: 20,
      capacityRules: [
        v => /^[0-9]+$/.test(v) || 'Capacity is not valid',
      ],
      priceFactor: '1',
      headers: [
        { text: "ID", value: "id", align: "start", filterable: false },
        { text: "Nomor Armada", value: "fleet_number" },
        { text: "License", value: "license" },
        { text: "Kategori", value: "busType.name" },
        { text: "Capacity", value: "capacity" },
        { text: "Pricing Factor", value: "price_factor" },
        { text: "Driver", value: "driver" },
        { text: "Depo Armada", value: "depot" },
        { text: "Created", value: "created_at" },
        { text: "Actions", value: "actions", sortable: false },
      ],
      seatConfig: {
        totalRows: 5,
        totalColumns: 4,
        seatGrid: [
            [true, true, true, true],
            [true, true, true, true],
            [true, true, true, true],
            [true, true, true, true],
            [true, true, true, true],
        ],
      },
    };
  },
  mounted() {
    this.loadBuses();
    this.loadDepots();
    this.loadBusTypes();
  },
  methods: {
    loadBuses() {
      this.isLoading = true;
      this.buses = [];
      axios
        .get(`/buses/all`)
        .then((response) => {
          this.buses = response.data;
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while retrieving buses",
            type: 'error'
          });
          console.log(error);
          auth.checkError(error, this.$router, this.$swal);
        })
        .then(() => {
          this.isLoading = false;
        });
    },
    loadDepots() {
      axios.get('/fleet-depots').then(response => {
        this.depots = (response.data.depots || []).filter(depot => depot.is_active)
      }).catch(() => {
        this.$notify({ title: 'Error', text: 'Lokasi depo tidak dapat dimuat', type: 'error' })
      })
    },
    loadBusTypes() {
      axios.get('/buses/types').then(response => {
        this.busTypes = response.data.bus_types || []
      }).catch(() => {
        this.$notify({ title: 'Error', text: 'Kategori bus tidak dapat dimuat', type: 'error' })
      })
    },
    validate () {
      return this.$refs.form.validate()
    },
    createBus() {
      if(this.validate())
      {
        this.isLoading = true;
        this.busDialog = false;
        axios
          .post(`/buses/create-edit`, {
            bus: {
              id: this.id,
              fleet_number: this.fleetNumber,
              license: this.license,
              bus_type_id: this.busTypeId,
              seat_config: JSON.stringify(this.seatConfig),
              depot_id: this.depotId,
              is_active: this.isActive,
            },
          })
          .then((response) => {
            this.loadBuses();
            this.$notify({
              title: "Success",
              text: this.id? "Bus updated!" : "Bus created!",
              type: 'success'
            });
            this.$swal("Success", "Bus " + (this.id? "updated" : "created") + " successfully", "success");
          })
          .catch((error) => {
            this.$notify({
              title: "Error",
              text: "Error while creating bus",
              type: 'error'
            });
            console.log(error);
            this.$swal("Error", error.response?.data?.message || 'Terjadi kesalahan', "error");
          })
          .then(() => {
            this.isLoading = false;
          });
      }
    },
    showBusDialog() {
      this.fleetNumber = '';
      this.license = '';
      this.id = null;
      this.depotId = null;
      this.busTypeId = this.busTypes.length ? this.busTypes[0].id : null;
      this.isActive = true;
      this.applySelectedBusType();
      this.busDialog = true;
    },
    editBus(bus) {
      this.id = bus.id;
      this.fleetNumber = bus.fleet_number || '';
      this.depotId = bus.depot_id || null;
      this.busTypeId = bus.bus_type_id || null;
      this.license = bus.license;
      this.capacity = bus.capacity;
      this.priceFactor = bus.price_factor;
      this.isActive = bus.is_active !== false;
      this.seatConfig = bus.seat_config ? JSON.parse(bus.seat_config) : this.generateSeatConfig(this.capacity);
      this.busDialog = true;
    },
    deleteBus(bus, index) {
      this.$swal
        .fire({
          title: "Delete bus",
          text: "Are you sure to delete the bus ' " + bus.license + " ' ? You won't be able to revert this!",
          icon: "error",
          showCancelButton: true,
          confirmButtonText: "Yes, delete it!",
        })
        .then((result) => {
          if (result.isConfirmed) {
            this.deleteBusServer(bus.id, index);
          }
        });
    },
    deleteBusServer(bus_id, index) {
      axios
        .delete(`/buses/${bus_id}`)
        .then((response) => {
          this.buses.splice(index, 1);
          this.$notify({
            title: "Success",
            text: "Bus deleted!",
            type: "success",
          });
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while deleting buses",
            type: 'error'
          });
          this.$swal("Error", error.response?.data?.message || 'Terjadi kesalahan', "error");
        })
        .then(() => {
          //this.isDeleting = false;
        });
    },
    getDriverAssignmentColor(driver) {
      if (!driver) return "error";
      if (driver.status_id == 3) return "warning";
      return "success";
    },
    getDriver(driver) {
      if (driver) return driver.name;
      else return "none";
    },
    formatSuspendedUntil(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    },
    assignDriver(item) {
      this.selectedBus = item;
      this.driversDialog = true;
      this.loadAvailableDrivers()
    },
    loadAvailableDrivers() {
      this.loadingDrivers = true;
      this.availableDrivers = [];
      axios
        .get('/buses/available-drivers')
        .then((response) => {
          this.availableDrivers = response.data;
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while retrieving drivers",
            type: 'error'
          });
          console.log(error);
          this.$swal("Error", error.response?.data?.message || 'Terjadi kesalahan', "error");
        })
        .then(() => {
          this.loadingDrivers = false;
        });
    },
    assignDriverToBus(driver) {
      this.loadingDrivers = true;
      axios
        .post(`/buses/assign-driver`, {
          bus_id: this.selectedBus.id,
          driver_id: driver.id,
        })
        .then((response) => {
          this.loadBuses();
          this.$notify({
            title: "Success",
            text: "Driver assigned to bus!",
            type: 'success'
          });
          this.$swal("Success", "Driver assigned to bus successfully", "success");
        })
        .catch((error) => {
          const errorMsg = error.response?.data?.error || "Error while assigning driver to bus";
          this.$notify({
            title: "Error",
            text: errorMsg,
            type: 'error'
          });
          this.$swal("Error", errorMsg, "error");
        })
        .then(() => {
          this.loadingDrivers = false;
          this.closeDriverDialog();
        });
    },
    unAssignDriver(item)
    {
      this.$swal
        .fire({
          title: "Un-assign driver from bus",
          text: "Are you sure to un-assign the driver ' " + item.driver.name + " ' from the bus '" + item.license + "' ? You won't be able to revert this!",
          icon: "error",
          showCancelButton: true,
          confirmButtonText: "Yes, delete it!",
        })
        .then((result) => {
          if (result.isConfirmed) {
            this.unAssignDriverFromBus(item);
          }
        });
    },
    unAssignDriverFromBus(item) {
      this.isLoading = true;
      axios
        .post(`/buses/unassign-driver`, {
          bus_id: item.id,
        })
        .then((response) => {
          this.loadBuses();
          this.$notify({
            title: "Success",
            text: "Driver unassigned from bus!",
            type: 'success'
          });
          this.$swal("Success", "Driver unassigned from bus successfully", "success");
        })
        .catch((error) => {
          this.$notify({
            title: "Error",
            text: "Error while un-assigning driver from bus",
            type: 'error'
          });
          console.log(error);
          this.$swal("Error", error.response?.data?.message || 'Terjadi kesalahan', "error");
        })
        .then(() => {
          this.isLoading = false;
        });
    },
    closeDriverDialog() {
      this.driversDialog = false;
      this.loadingDrivers = false;
      this.availableDrivers = [];
    },
    updateSeats(seatConfig) {
      this.seatConfig = seatConfig;
      this.updateCapacity();
    },
    applySelectedBusType() {
      const busType = this.busTypes.find(type => type.id === this.busTypeId);
      if (!busType) return;
      this.capacity = busType.capacity;
      this.priceFactor = busType.price_factor;
      this.seatConfig = this.generateSeatConfig(busType.capacity);
    },
    generateSeatConfig(capacity) {
      const columns = 4;
      const rows = Math.ceil(Number(capacity || 0) / columns);
      let remaining = Number(capacity || 0);
      const seatGrid = [];
      for (let row = 0; row < rows; row++) {
        const current = [];
        for (let column = 0; column < columns; column++) {
          current.push(remaining > 0);
          remaining--;
        }
        seatGrid.push(current);
      }
      return {
        totalRows: rows,
        totalColumns: columns,
        rows,
        columns,
        seatGrid,
      };
    },
    updateCapacity() {
      let totalSeats = 0;
      for (let i = 0; i < this.seatConfig.seatGrid.length; i++) {
        for (let j = 0; j < this.seatConfig.seatGrid[i].length; j++) {
          if (this.seatConfig.seatGrid[i][j]) {
            totalSeats++;
          }
        }
      }
      this.capacity = totalSeats;
    },
  },
};
</script>
