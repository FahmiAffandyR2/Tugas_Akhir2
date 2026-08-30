<template>
  <div>
    <v-card class="rounded-xl">
      <v-card-title>
        <v-icon color="primary" class="mr-2">mdi-file-document-edit</v-icon>
        Template Notifikasi
        <v-spacer />
        <v-btn color="primary" @click="openCreate"><v-icon left>mdi-plus</v-icon>Tambah</v-btn>
      </v-card-title>
      <v-card-text>
        <v-data-table :headers="headers" :items="templates" :loading="loading" class="elevation-0">
          <template v-slot:item.type="{ item }">
            <v-chip small :color="typeColor(item.type)" dark>{{ item.type.toUpperCase() }}</v-chip>
          </template>
          <template v-slot:item.is_active="{ item }">
            <v-icon :color="item.is_active ? 'success' : 'grey'" small>
              {{ item.is_active ? 'mdi-check-circle' : 'mdi-close-circle' }}
            </v-icon>
          </template>
          <template v-slot:item.actions="{ item }">
            <v-icon small class="mr-1" @click="openEdit(item)">mdi-pencil</v-icon>
            <v-icon small color="error" @click="remove(item)">mdi-delete</v-icon>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="dialog" max-width="680" persistent>
      <v-card>
        <v-card-title>
          {{ form.id ? 'Edit' : 'Tambah' }} Template
          <v-spacer />
          <v-btn icon @click="dialog = false"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>
        <v-card-text>
          <v-form ref="form" v-model="valid">
            <v-row>
              <v-col cols="12" sm="6">
                <v-text-field v-model.trim="form.name" outlined label="Nama template" :rules="[v=>!!v||'Wajib']" />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select v-model="form.type" :items="typeOptions" outlined label="Tipe" :rules="[v=>!!v||'Wajib']" />
              </v-col>
              <v-col cols="12" v-if="form.type === 'email'">
                <v-text-field v-model.trim="form.subject" outlined label="Subjek" />
              </v-col>
              <v-col cols="12">
                <v-textarea v-model="form.body" outlined rows="5" label="Isi pesan" :rules="[v=>!!v||'Wajib']" :hint="hintText" persistent-hint />
              </v-col>
              <v-col cols="12">
                <v-combobox v-model="form.variables" :items="[]" multiple outlined label="Variabel (opsional)" hint="Tekan Enter untuk menambah variabel" persistent-hint small-chips deletable-chips />
              </v-col>
              <v-col cols="12">
                <v-switch v-model="form.is_active" label="Aktif" />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn text @click="dialog = false">Batal</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">Simpan</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
export default {
  data() {
    return {
      templates: [],
      loading: false,
      saving: false,
      dialog: false,
      valid: true,
      form: { id: null, name: '', type: 'push', subject: '', body: '', variables: [], is_active: true },
      typeOptions: [
        { text: 'Push Notification', value: 'push' },
        { text: 'SMS', value: 'sms' },
        { text: 'Email', value: 'email' },
      ],
      headers: [
        { text: 'Nama', value: 'name' },
        { text: 'Tipe', value: 'type' },
        { text: 'Subjek', value: 'subject' },
        { text: 'Variabel', value: 'variables', sortable: false },
        { text: 'Aktif', value: 'is_active', align: 'center' },
        { text: 'Aksi', value: 'actions', sortable: false, align: 'right' },
      ],
    }
  },
  computed: {
    hintText() { return 'Gunakan {{ nama }} untuk variabel' },
  },
  created() { this.loadTemplates() },
  methods: {
    async loadTemplates() {
      this.loading = true
      try { const r = await axios.get('/notification-templates'); this.templates = r.data || [] }
      catch (e) { this.$notify({ type: 'error', title: 'Gagal', text: 'Template tidak dapat dimuat.' }) }
      finally { this.loading = false }
    },
    typeColor(type) {
      return { push: 'info', sms: 'success', email: 'warning' }[type] || 'grey'
    },
    openCreate() {
      this.form = { id: null, name: '', type: 'push', subject: '', body: '', variables: [], is_active: true }
      this.dialog = true
    },
    openEdit(t) {
      this.form = { ...t, variables: t.variables || [] }
      this.dialog = true
    },
    async save() {
      if (!this.$refs.form.validate()) return
      this.saving = true
      try {
        if (this.form.id) {
          await axios.put(`/notification-templates/${this.form.id}`, this.form)
        } else {
          await axios.post('/notification-templates', this.form)
        }
        this.$notify({ type: 'success', title: 'Berhasil', text: 'Template tersimpan.' })
        this.dialog = false
        await this.loadTemplates()
      } catch (e) {
        const msg = e.response && e.response.data && e.response.data.message
        this.$notify({ type: 'error', title: 'Gagal', text: msg || 'Template gagal disimpan.' })
      } finally { this.saving = false }
    },
    async remove(t) {
      const result = await this.$swal.fire({ title: 'Hapus template?', text: t.name, icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' })
      if (!result.isConfirmed) return
      try {
        await axios.delete(`/notification-templates/${t.id}`)
        this.$notify({ type: 'success', title: 'Berhasil', text: 'Template dihapus.' })
        await this.loadTemplates()
      } catch (e) { this.$notify({ type: 'error', title: 'Gagal', text: 'Template gagal dihapus.' }) }
    },
  },
}
</script>
