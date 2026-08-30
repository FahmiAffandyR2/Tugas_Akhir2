<template>
  <div>
    <v-card class="rounded-xl">
      <v-card-title>
        <v-icon color="primary" class="mr-2">mdi-history</v-icon>
        Audit Log
        <v-spacer />
        <v-btn icon :loading="loading" @click="loadLogs"><v-icon>mdi-refresh</v-icon></v-btn>
      </v-card-title>
      <v-card-text>
        <v-row class="mb-4">
          <v-col cols="12" sm="3">
            <v-text-field v-model="filters.search" outlined dense hide-details prepend-inner-icon="mdi-magnify" label="Cari..." @keyup.enter="loadLogs" />
          </v-col>
          <v-col cols="12" sm="2">
            <v-select v-model="filters.action" :items="actionOptions" outlined dense hide-details label="Aksi" clearable />
          </v-col>
          <v-col cols="12" sm="2">
            <v-select v-model="filters.entity_type" :items="entityOptions" outlined dense hide-details label="Entity" clearable />
          </v-col>
          <v-col cols="12" sm="2">
            <v-text-field v-model="filters.from_date" outlined dense hide-details type="date" label="Dari" />
          </v-col>
          <v-col cols="12" sm="2">
            <v-text-field v-model="filters.to_date" outlined dense hide-details type="date" label="Sampai" />
          </v-col>
          <v-col cols="12" sm="1">
            <v-btn color="primary" block @click="loadLogs"><v-icon>mdi-magnify</v-icon></v-btn>
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="logs"
          :loading="loading"
          :server-items-length="totalLogs"
          :options.sync="options"
          @update:options="loadLogs"
          class="elevation-0"
        >
          <template v-slot:item.user="{ item }">
            <template v-if="item.user">
              <div class="font-weight-bold">{{ item.user.name }}</div>
              <div class="caption grey--text">{{ item.user.email }}</div>
            </template>
            <span v-else class="grey--text">System</span>
          </template>
          <template v-slot:item.action="{ item }">
            <v-chip :color="actionColor(item.action)" small dark>{{ item.action }}</v-chip>
          </template>
          <template v-slot:item.entity_type="{ item }">
            <v-chip small outlined>{{ item.entity_type }}</v-chip>
          </template>
          <template v-slot:item.entity_id="{ item }">
            <span class="font-weight-bold">#{{ item.entity_id }}</span>
          </template>
          <template v-slot:item.description="{ item }">
            <span class="caption">{{ item.description }}</span>
          </template>
          <template v-slot:item.created_at="{ item }">
            <div class="caption">{{ formatDateTime(item.created_at) }}</div>
          </template>
          <template v-slot:item.changes="{ item }">
            <v-btn v-if="item.old_values || item.new_values" x-small icon @click="showChanges(item)">
              <v-icon small>mdi-code-json</v-icon>
            </v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="changesDialog" max-width="700" scrollable>
      <v-card>
        <v-card-title>
          Detail Perubahan
          <v-spacer />
          <v-btn icon @click="changesDialog = false"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>
        <v-card-text>
          <div v-if="selectedLog">
            <v-alert type="info" dense text class="mb-3">{{ selectedLog.description }}</v-alert>
            <div v-if="selectedLog.old_values">
              <h4 class="mb-2">Nilai Lama:</h4>
              <pre class="changes-pre">{{ JSON.stringify(selectedLog.old_values, null, 2) }}</pre>
            </div>
            <div v-if="selectedLog.new_values" class="mt-3">
              <h4 class="mb-2">Nilai Baru:</h4>
              <pre class="changes-pre">{{ JSON.stringify(selectedLog.new_values, null, 2) }}</pre>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
export default {
  data() {
    return {
      logs: [],
      totalLogs: 0,
      loading: false,
      options: { page: 1, itemsPerPage: 50 },
      filters: { search: '', action: null, entity_type: null, from_date: null, to_date: null },
      actionOptions: ['created', 'updated', 'deleted'],
      entityOptions: [],
      changesDialog: false,
      selectedLog: null,
      headers: [
        { text: 'User', value: 'user', sortable: false },
        { text: 'Aksi', value: 'action' },
        { text: 'Entity', value: 'entity_type' },
        { text: 'ID', value: 'entity_id', align: 'center' },
        { text: 'Deskripsi', value: 'description', sortable: false },
        { text: 'Waktu', value: 'created_at' },
        { text: '', value: 'changes', sortable: false, align: 'center' },
      ],
    }
  },
  created() {
    this.loadEntities()
    this.loadLogs()
  },
  methods: {
    async loadLogs() {
      this.loading = true
      try {
        const params = {
          page: this.options.page,
          per_page: this.options.itemsPerPage,
          ...this.filters,
        }
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
        const r = await axios.get('/audit-logs', { params })
        this.logs = r.data.data || []
        this.totalLogs = r.data.total || 0
      } catch (e) {
        this.$notify({ type: 'error', title: 'Gagal', text: 'Audit log tidak dapat dimuat.' })
      } finally { this.loading = false }
    },
    async loadEntities() {
      try {
        const r = await axios.get('/audit-logs/entities')
        this.entityOptions = r.data || []
      } catch (e) { /* ignore */ }
    },
    actionColor(action) {
      return { created: 'success', updated: 'info', deleted: 'error' }[action] || 'grey'
    },
    formatDateTime(dt) {
      if (!dt) return '-'
      return new Date(dt).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    },
    showChanges(log) {
      this.selectedLog = log
      this.changesDialog = true
    },
  },
}
</script>

<style scoped>
.changes-pre { background:#f5f5f5; padding:12px; border-radius:8px; font-size:12px; overflow-x:auto; max-height:300px; overflow-y:auto; }
</style>
