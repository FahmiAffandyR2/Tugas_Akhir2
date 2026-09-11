<template>
  <div>
    <v-card>
      <v-card-title>
        <v-icon color="primary">mdi-road-variant</v-icon>
        <span class="pl-2">Routes</span>
        <v-spacer></v-spacer>
        <create-button @create="createRoute"></create-button>
      </v-card-title>

      <v-tabs v-model="activeTab" color="primary" class="px-4">
        <v-tab href="#berjalan">
          <v-icon small left>mdi-play-circle</v-icon>
          Berjalan
          <v-chip x-small class="ml-2" color="primary" dark v-if="berjalan.length">{{ berjalan.length }}</v-chip>
        </v-tab>
        <v-tab href="#selesai">
          <v-icon small left>mdi-check-circle</v-icon>
          Selesai
          <v-chip x-small class="ml-2" color="success" dark v-if="selesai.length">{{ selesai.length }}</v-chip>
        </v-tab>
      </v-tabs>

      <v-divider />

      <v-tabs-items v-model="activeTab">
        <!-- TAB BERJALAN -->
        <v-tab-item value="berjalan">
          <v-card flat>
            <v-card-text>
              <v-text-field v-model="searchBerjalan" label="Search" class="mx-4" prepend-icon="mdi-magnify"></v-text-field>
              <v-data-table
                :headers="headers"
                :items="berjalan"
                :search="searchBerjalan"
                :loading="isLoading"
                loading-text="Loading..."
                no-data-text="Tidak ada route aktif"
              >
                <template v-slot:item.created_at="{ item }">
                  <small>{{ item.created_at | moment("LL") }}</small>
                </template>
                <template v-slot:item.active_trips_count="{ item }">
                  <v-chip x-small color="warning" dark>{{ item.active_trips_count }}</v-chip>
                </template>
                <template v-slot:item.actions="{ item }">
                  <v-icon small class="mr-2" @click="viewRoute(item)">mdi-eye</v-icon>
                  <v-icon small class="mr-2" @click="editRoute(item)">mdi-pencil</v-icon>
                  <v-icon small color="error" @click="deleteRoute(item, berjalan.indexOf(item))">mdi-delete</v-icon>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-tab-item>

        <!-- TAB SELESAI -->
        <v-tab-item value="selesai">
          <v-card flat>
            <v-card-text>
              <v-text-field v-model="searchSelesai" label="Search" class="mx-4" prepend-icon="mdi-magnify"></v-text-field>
              <v-data-table
                :headers="headersSelesai"
                :items="selesai"
                :search="searchSelesai"
                :loading="isLoading"
                loading-text="Loading..."
                no-data-text="Tidak ada route selesai"
              >
                <template v-slot:item.created_at="{ item }">
                  <small>{{ item.created_at | moment("LL") }}</small>
                </template>
                <template v-slot:item.completed_trips_count="{ item }">
                  <v-chip x-small color="success" dark>{{ item.completed_trips_count }}</v-chip>
                </template>
                <template v-slot:item.actions="{ item }">
                  <v-icon small class="mr-2" @click="viewRoute(item)">mdi-eye</v-icon>
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-tab-item>
      </v-tabs-items>
    </v-card>
  </div>
</template>

<script>
import CreateButton from "@/components/CreateButton";
import auth from '@/services/AuthService'

export default {
  components: {
    CreateButton,
  },
  data() {
    return {
      activeTab: 'berjalan',
      berjalan: [],
      selesai: [],
      isLoading: false,
      searchBerjalan: "",
      searchSelesai: "",
      headers: [
        { text: "ID", value: "id", align: "start", filterable: false },
        { text: "Name", value: "name" },
        { text: "Stops", value: "stops_count" },
        { text: "Trip Aktif", value: "active_trips_count" },
        { text: "Created", value: "created_at" },
        { text: "Actions", value: "actions", sortable: false },
      ],
      headersSelesai: [
        { text: "ID", value: "id", align: "start", filterable: false },
        { text: "Name", value: "name" },
        { text: "Stops", value: "stops_count" },
        { text: "Trip Selesai", value: "completed_trips_count" },
        { text: "Created", value: "created_at" },
        { text: "Actions", value: "actions", sortable: false },
      ],
    };
  },
  mounted() {
    this.loadRoutes();
  },
  methods: {
    loadRoutes() {
      this.isLoading = true;
      axios
        .get('/routes/by-status')
        .then((response) => {
          this.berjalan = response.data.berjalan || [];
          this.selesai = response.data.selesai || [];
        })
        .catch((error) => {
          this.$notify({ title: "Error", text: "Gagal memuat data routes", type: 'error' });
          console.log(error);
          auth.checkError(error, this.$router, this.$swal);
        })
        .then(() => {
          this.isLoading = false;
        });
    },
    createRoute() {
      this.$swal
        .fire({
          title: "Enter route name",
          input: "text",
          inputPlaceholder: "New route",
          showCancelButton: true,
        })
        .then((result) => {
          if (result.isConfirmed) {
            const value = result.value.trim();
            this.$router.push({
              name: "create-route",
              params: { route_name: value ? value : "Untitled" },
            });
          }
        });
    },
    viewRoute(route) {
      this.$router.push({ name: "view-route", params: { route_id: route.id } });
    },
    editRoute(route) {
      this.$swal
        .fire({
          title: "Enter route name",
          input: "text",
          inputValue: route.name,
          showCancelButton: true,
        })
        .then((result) => {
          if (result.isConfirmed) {
            const value = result.value.trim();
            this.$router.push({
              name: "edit-route",
              params: { route_id: route.id, new_route_name: value ? value : "Untitled" },
            });
          }
        });
    },
    deleteRoute(route, index) {
      this.$swal
        .fire({
          title: "Delete route",
          text: "Yakin hapus route '" + route.name + "'? Semua data terkait akan dihapus!",
          icon: "error",
          showCancelButton: true,
          confirmButtonText: "Yes, delete it!",
        })
        .then((result) => {
          if (result.isConfirmed) {
            axios
              .delete(`/routes/${route.id}`)
              .then(() => {
                this.berjalan.splice(index, 1);
                this.$notify({ title: "Success", text: "Route berhasil dihapus!", type: "success" });
              })
              .catch((error) => {
                this.$notify({ title: "Error", text: "Gagal menghapus route", type: 'error' });
                this.$swal("Error", error.response?.data?.message || 'Terjadi kesalahan', "error");
              });
          }
        });
    },
  },
};
</script>
