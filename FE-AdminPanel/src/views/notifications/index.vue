<template>
  <div>
    <v-card class="rounded-xl">
      <v-card-title>
        <v-icon color="primary" class="mr-2">mdi-bell-ring</v-icon>
        Notifikasi Umum
        <v-spacer />
        <v-btn color="primary" @click="openSend"><v-icon left>mdi-send</v-icon>Kirim Notifikasi</v-btn>
      </v-card-title>
      <v-card-text>
        <v-row class="mb-4">
          <v-col cols="12" sm="4">
            <v-text-field v-model="filters.search" outlined dense hide-details prepend-inner-icon="mdi-magnify" label="Cari penerima..." @keyup.enter="loadLogs" />
          </v-col>
          <v-col cols="12" sm="2">
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
            <span v-else class="grey--text">Unknown</span>
          </template>
          <template v-slot:item.seen="{ item }">
            <v-chip :color="item.seen ? 'success' : 'warning'" small dark>
              {{ item.seen ? 'Dibaca' : 'Belum dibaca' }}
            </v-chip>
          </template>
          <template v-slot:item.created_at="{ item }">
            <span class="caption">{{ formatDateTime(item.created_at) }}</span>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="sendDialog" max-width="600" persistent>
      <v-card>
        <v-card-title>
          Kirim Notifikasi
          <v-spacer />
          <v-btn icon @click="sendDialog = false"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>
        <v-card-text>
          <v-form ref="sendForm" v-model="sendValid">
            <v-autocomplete
              v-model="sendForm.recipient_ids"
              :items="allRecipients"
              item-text="name"
              item-value="id"
              multiple
              outlined
              label="Penerima"
              :rules="[v => v.length > 0 || 'Pilih minimal 1 penerima']"
            >
              <template v-slot:selection="data">
                <v-chip small class="mr-1 mb-1">
                  {{ data.item.name }}
                  <span class="caption grey--text ml-1">({{ data.item.type }})</span>
                </v-chip>
              </template>
              <template v-slot:item="data">
                <v-list-item-content>
                  <v-list-item-title>{{ data.item.name }}</v-list-item-title>
                  <v-list-item-subtitle>{{ data.item.email }} - {{ data.item.type }}</v-list-item-subtitle>
                </v-list-item-content>
              </template>
            </v-autocomplete>

            <v-select
              v-model="sendForm.template_id"
              :items="templateOptions"
              outlined
              dense
              label="Gunakan template (opsional)"
              clearable
              class="mb-3"
            />

            <v-textarea
              v-model="sendForm.message"
              outlined
              rows="4"
              label="Pesan"
              :rules="[v => !!v || 'Wajib diisi']"
            />
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn text @click="sendDialog = false">Batal</v-btn>
          <v-btn color="primary" :loading="sending" @click="sendNotif">Kirim</v-btn>
        </v-card-actions>
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
      filters: { search: '' },
      sendDialog: false,
      sendValid: true,
      sending: false,
      allRecipients: [],
      templateOptions: [],
      sendForm: { recipient_ids: [], template_id: null, message: '' },
      headers: [
        { text: 'Penerima', value: 'user', sortable: false },
        { text: 'Pesan', value: 'message', sortable: false },
        { text: 'Status', value: 'seen', align: 'center' },
        { text: 'Waktu', value: 'created_at' },
      ],
    }
  },
  created() {
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
        const r = await axios.get('/notifications', { params })
        this.logs = r.data.data || []
        this.totalLogs = r.data.total || 0
      } catch (e) {
        this.$notify({ type: 'error', title: 'Gagal', text: 'Notifikasi tidak dapat dimuat.' })
      } finally { this.loading = false }
    },
    async openSend() {
      this.sendForm = { recipient_ids: [], template_id: null, message: '' }
      this.sendDialog = true
      try {
        const [recRes, tplRes] = await Promise.all([
          axios.get('/notifications/recipients'),
          axios.get('/notification-templates'),
        ])
        const drivers = (recRes.data.drivers || []).map(d => ({ ...d, type: 'Driver' }))
        const customers = (recRes.data.customers || []).map(c => ({ ...c, type: 'Customer' }))
        this.allRecipients = [...drivers, ...customers]
        this.templateOptions = (tplRes.data || []).map(t => ({ text: `${t.name} (${t.type})`, value: t.id }))
      } catch (e) { /* ignore */ }
    },
    async sendNotif() {
      if (!this.$refs.sendForm.validate()) return
      this.sending = true
      try {
        const payload = {
          recipient_ids: this.sendForm.recipient_ids,
          message: this.sendForm.message,
        }
        if (this.sendForm.template_id) payload.template_id = this.sendForm.template_id
        await axios.post('/notifications/send', payload)
        this.$notify({ type: 'success', title: 'Berhasil', text: 'Notifikasi terkirim.' })
        this.sendDialog = false
        await this.loadLogs()
      } catch (e) {
        const msg = e.response && e.response.data && e.response.data.message
        this.$notify({ type: 'error', title: 'Gagal', text: msg || 'Notifikasi gagal dikirim.' })
      } finally { this.sending = false }
    },
    formatDateTime(dt) {
      if (!dt) return '-'
      return new Date(dt).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    },
  },
}
</script>
