<template>
  <v-card flat outlined class="pa-5 readiness-form">
    <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4" />
    <v-alert v-if="error" type="error" text dense>{{ error }} <v-btn text small @click="load">Coba lagi</v-btn></v-alert>
    <template v-if="record">
      <v-chip :color="record.ready && !dirty ? 'success' : 'warning'" small class="mb-4">{{ record.ready && !dirty ? 'Siap Bertugas' : 'Belum Siap' }}</v-chip>
      <p class="text-caption">Kesiapan berdasarkan dokumen yang masih berlaku dan kondisi kesehatan yang dilaporkan driver.</p>
      <v-form ref="form" @submit.prevent="save">
        <section v-for="doc in documents" :key="doc.key" class="document-section mb-5">
          <h3 class="text-subtitle-1 font-weight-bold mb-2"><v-icon small :color="documentValid(doc.key) ? 'success' : 'warning'" class="mr-1">{{ documentValid(doc.key) ? 'mdi-check-circle' : 'mdi-alert-circle-outline' }}</v-icon>{{ doc.label }}</h3>
          <template v-if="doc.key === 'sim'">
            <v-text-field v-model.trim="form.sim_number" label="Nomor SIM" outlined dense :readonly="readonly" :disabled="saving" maxlength="100" @input="changed" />
          </template>
          <template v-if="doc.key === 'health'">
            <v-select v-model="form.health_status" :items="healthOptions" label="Kondisi kesehatan" outlined dense :readonly="readonly" :disabled="saving" :rules="[v => !!v || 'Pilih kondisi kesehatan']" @change="changed" />
            <v-textarea v-model.trim="form.health_notes" label="Penjelasan kondisi kesehatan untuk admin" outlined rows="3" :readonly="readonly" :disabled="saving" maxlength="2000" counter="2000" :rules="[v => form.health_status !== 'sick' || !!(v || '').trim() || 'Jelaskan kondisi sakit Anda']" @input="changed" />
          </template>
          <v-text-field v-model="form[doc.key + '_valid_until']" type="date" :label="doc.key === 'health' ? 'Surat kesehatan berlaku sampai' : 'Berlaku sampai'" outlined dense :readonly="readonly" :disabled="saving" @input="changed" />
          <div v-if="record.documents[doc.key].uploaded" class="d-flex align-center mb-2">
            <span class="text-caption">Dokumen tersimpan</span><v-btn text small color="primary" :loading="downloading === doc.key" @click="download(doc.key)">Unduh</v-btn>
          </div>
          <div v-else class="text-caption mb-2">Dokumen belum diunggah</div>
          <v-file-input v-if="!readonly" v-model="files[doc.key]" :label="'Unggah ' + doc.label" accept="image/jpeg,image/png,image/webp,application/pdf" outlined dense :disabled="saving" :rules="fileRules" hint="JPG, PNG, WEBP atau PDF, maksimal 5 MB" persistent-hint @change="changed" />
        </section>
        <template v-if="!readonly">
          <p v-if="dirty" class="text-caption warning--text">Perubahan belum disimpan.</p>
          <v-btn block color="primary" type="submit" :loading="saving" :disabled="loading || saving">Simpan kesiapan</v-btn>
        </template>
        <p v-if="record.updated_at" class="text-caption mt-3 mb-0">Diperbarui: {{ formatTime(record.updated_at) }}</p>
      </v-form>
    </template>
  </v-card>
</template>
<script>
import axios from 'axios'
export default {
  props: { driverId: { type: [Number, String], default: null }, readonly: Boolean },
  data: () => ({ record: null, loading: false, saving: false, dirty: false, error: '', downloading: null, form: {}, files: { sim: null, health: null, skck: null }, documents: [{ key: 'sim', label: 'SIM' }, { key: 'health', label: 'Kesehatan pengemudi / surat keterangan sehat' }, { key: 'skck', label: 'SKCK' }], healthOptions: [{ text: 'Sehat', value: 'healthy' }, { text: 'Sakit', value: 'sick' }], fileRules: [v => !v || v.size <= 5 * 1024 * 1024 || 'Ukuran maksimal 5 MB'] }),
  computed: { endpoint() { return this.readonly ? `/drivers/${this.driverId}/readiness` : '/drivers/readiness' } },
  created() { this.load() },
  watch: { driverId() { this.load() } },
  methods: {
    apply(record) {
      this.record = record
      this.form = { sim_number: record.sim_number || '', health_status: record.health_status === 'unknown' ? null : record.health_status, health_notes: record.health_notes || '' }
      this.documents.forEach(doc => { this.$set(this.form, doc.key + '_valid_until', record[doc.key + '_valid_until'] || '') })
      this.files = { sim: null, health: null, skck: null }; this.dirty = false
      this.$emit('readiness', record.ready)
    },
    changed() { this.dirty = true; this.$emit('readiness', false) },
    documentValid(kind) { return !this.dirty && this.record.documents[kind].valid && (kind !== 'health' || this.form.health_status === 'healthy') },
    async load() {
      this.loading = true; this.error = ''; this.$emit('readiness', false)
      try { const response = await axios.get(this.endpoint); this.apply(response.data.readiness) }
      catch (_) { this.error = 'Kesiapan driver tidak dapat dimuat.' }
      finally { this.loading = false }
    },
    async save() {
      if (this.readonly || this.saving || !this.$refs.form.validate()) return
      this.saving = true; this.error = ''
      const data = new FormData()
      Object.entries(this.form).forEach(([key, value]) => data.append(key, value || ''))
      Object.entries(this.files).forEach(([key, file]) => { if (file) data.append(key + '_file', file) })
      try {
        const response = await axios.post('/drivers/readiness', data)
        this.apply(response.data.readiness)
        this.$notify({ type: 'success', title: 'Kesiapan tersimpan', text: response.data.message })
      } catch (error) { const data = error.response && error.response.data; this.error = data && data.errors ? Object.values(data.errors).flat().join(' ') : 'Kesiapan tidak dapat disimpan. Silakan coba lagi.' }
      finally { this.saving = false }
    },
    async download(kind) {
      this.downloading = kind
      try {
        const response = await axios.get(`/drivers/${this.record.driver_id}/readiness/documents/${kind}`, { responseType: 'blob' })
        const url = URL.createObjectURL(response.data)
        const link = document.createElement('a'); link.href = url
        const extensions = { 'application/pdf': 'pdf', 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' }
        link.download = kind + '.' + (extensions[(response.headers['content-type'] || '').split(';')[0]] || 'bin')
        document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000)
      } catch (_) { this.$notify({ type: 'error', title: 'Dokumen tidak dapat diunduh' }) }
      finally { this.downloading = null }
    },
    formatTime(value) { return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) },
  },
}
</script>
<style scoped>
.readiness-form{border-radius:16px}.document-section+.document-section{border-top:1px solid #ececf3;padding-top:18px}
</style>
