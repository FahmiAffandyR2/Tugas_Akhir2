<template>
  <div>
    <v-card class="rounded-xl mb-4">
      <v-card-title>
        <v-icon color="primary" class="mr-2">mdi-calendar-clock</v-icon>
        Shift Driver
        <v-spacer />
        <v-btn color="primary" @click="openCreate"><v-icon left>mdi-plus</v-icon>Tambah Shift</v-btn>
      </v-card-title>
      <v-card-text>
        <v-row class="mb-4">
          <v-col cols="12" sm="3">
            <v-select v-model="selectedDriver" :items="drivers" item-text="name" item-value="id" outlined dense hide-details label="Driver" clearable @change="loadShifts" />
          </v-col>
          <v-col cols="12" sm="2">
            <v-btn-toggle v-model="viewMode" dense mandatory>
              <v-btn small value="calendar">Kalender</v-btn>
              <v-btn small value="list">Daftar</v-btn>
            </v-btn-toggle>
          </v-col>
          <v-col cols="12" sm="2">
            <v-btn icon @click="prevMonth"><v-icon>mdi-chevron-left</v-icon></v-btn>
            <span class="mx-2 font-weight-bold">{{ monthLabel }}</span>
            <v-btn icon @click="nextMonth"><v-icon>mdi-chevron-right</v-icon></v-btn>
          </v-col>
        </v-row>

        <div v-if="viewMode === 'calendar'" class="calendar-grid">
          <div v-for="day in daysInMonth" :key="day.date" class="calendar-cell" :class="{ 'today': isToday(day.date), 'other-month': !day.currentMonth }" @click="day.currentMonth && openCreateForDate(day.date)">
            <div class="calendar-day">{{ day.day }}</div>
            <div v-for="shift in getShiftsForDate(day.date)" :key="shift.id" class="calendar-event" :class="'status-' + shift.status" @click.stop="openEdit(shift)">
              <span class="event-time">{{ shift.start_time }}</span>
              <span class="event-name">{{ shift.driver ? shift.driver.name : '-' }}</span>
            </div>
          </div>
        </div>

        <v-data-table v-else :headers="listHeaders" :items="shifts" :loading="loading" class="elevation-0">
          <template v-slot:item.driver="{ item }">
            {{ item.driver ? item.driver.name : '-' }}
          </template>
          <template v-slot:item.shift_date="{ item }">
            {{ formatDate(item.shift_date) }}
          </template>
          <template v-slot:item.time_range="{ item }">
            {{ item.start_time }} - {{ item.end_time }}
          </template>
          <template v-slot:item.status="{ item }">
            <v-chip small :color="statusColor(item.status)" dark>{{ statusLabel(item.status) }}</v-chip>
          </template>
          <template v-slot:item.actions="{ item }">
            <v-icon small class="mr-1" @click="openEdit(item)">mdi-pencil</v-icon>
            <v-icon small color="error" @click="remove(item)">mdi-delete</v-icon>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="dialog" max-width="550" persistent>
      <v-card>
        <v-card-title>
          {{ form.id ? 'Edit' : 'Tambah' }} Shift
          <v-spacer />
          <v-btn icon @click="dialog = false"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>
        <v-card-text>
          <v-form ref="form" v-model="valid">
            <v-autocomplete v-model="form.driver_id" :items="drivers" item-text="name" item-value="id" outlined label="Driver" :rules="[v => !!v || 'Wajib']" />
            <v-text-field v-model.trim="form.shift_name" outlined label="Nama shift" :rules="[v => !!v || 'Wajib']" hint="Contoh: Shift Pagi" persistent-hint />
            <v-row>
              <v-col cols="6">
                <v-text-field v-model="form.start_time" outlined label="Jam mulai" type="time" :rules="[v => !!v || 'Wajib']" />
              </v-col>
              <v-col cols="6">
                <v-text-field v-model="form.end_time" outlined label="Jam selesai" type="time" :rules="[v => !!v || 'Wajib']" />
              </v-col>
            </v-row>
            <v-text-field v-model="form.shift_date" outlined type="date" label="Tanggal" :rules="[v => !!v || 'Wajib']" />
            <v-select v-if="form.id" v-model="form.status" :items="statusOptions" outlined label="Status" />
            <v-textarea v-model="form.notes" outlined rows="2" label="Catatan (opsional)" />
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
      shifts: [],
      drivers: [],
      selectedDriver: null,
      loading: false,
      saving: false,
      dialog: false,
      valid: true,
      currentYear: new Date().getFullYear(),
      currentMonth: new Date().getMonth() + 1,
      viewMode: 'calendar',
      form: { id: null, driver_id: null, shift_name: '', start_time: '', end_time: '', shift_date: '', status: 'scheduled', notes: '' },
      statusOptions: [
        { text: 'Dijadwalkan', value: 'scheduled' },
        { text: 'Aktif', value: 'active' },
        { text: 'Selesai', value: 'completed' },
        { text: 'Tidak Hadir', value: 'absent' },
      ],
      listHeaders: [
        { text: 'Driver', value: 'driver' },
        { text: 'Shift', value: 'shift_name' },
        { text: 'Tanggal', value: 'shift_date' },
        { text: 'Jam', value: 'time_range' },
        { text: 'Status', value: 'status' },
        { text: 'Aksi', value: 'actions', sortable: false, align: 'right' },
      ],
    }
  },
  computed: {
    monthLabel() {
      const d = new Date(this.currentYear, this.currentMonth - 1, 1)
      return d.toLocaleString('id-ID', { month: 'long', year: 'numeric' })
    },
    daysInMonth() {
      const days = []
      const first = new Date(this.currentYear, this.currentMonth - 1, 1)
      const startDay = first.getDay() === 0 ? 6 : first.getDay() - 1
      const totalDays = new Date(this.currentYear, this.currentMonth, 0).getDate()
      const prevDays = new Date(this.currentYear, this.currentMonth - 1, 0).getDate()

      for (let i = startDay - 1; i >= 0; i--) {
        const d = prevDays - i
        const date = `${this.currentYear}-${String(this.currentMonth - 1 || 12).padStart(2, '0')}-${String(d).padStart(2, '0')}`
        days.push({ day: d, date, currentMonth: false })
      }
      for (let d = 1; d <= totalDays; d++) {
        const date = `${this.currentYear}-${String(this.currentMonth).padStart(2, '0')}-${String(d).padStart(2, '0')}`
        days.push({ day: d, date, currentMonth: true })
      }
      while (days.length < 42) {
        const d = days.length - startDay - totalDays + 1
        const nextMonth = this.currentMonth === 12 ? 1 : this.currentMonth + 1
        const nextYear = this.currentMonth === 12 ? this.currentYear + 1 : this.currentYear
        const date = `${nextYear}-${String(nextMonth).padStart(2, '0')}-${String(d).padStart(2, '0')}`
        days.push({ day: d, date, currentMonth: false })
      }
      return days
    },
  },
  created() {
    this.loadDrivers()
    this.loadShifts()
  },
  methods: {
    async loadDrivers() {
      try { const r = await axios.get('/driver-shifts/drivers'); this.drivers = r.data || [] }
      catch (e) { /* ignore */ }
    },
    async loadShifts() {
      this.loading = true
      try {
        const params = { year: this.currentYear, month: this.currentMonth }
        if (this.selectedDriver) params.driver_id = this.selectedDriver
        const r = await axios.get('/driver-shifts', { params })
        this.shifts = r.data || []
      } catch (e) {
        this.$notify({ type: 'error', title: 'Gagal', text: 'Shift tidak dapat dimuat.' })
      } finally { this.loading = false }
    },
    prevMonth() {
      if (this.currentMonth === 1) { this.currentMonth = 12; this.currentYear-- }
      else this.currentMonth--
      this.loadShifts()
    },
    nextMonth() {
      if (this.currentMonth === 12) { this.currentMonth = 1; this.currentYear++ }
      else this.currentMonth++
      this.loadShifts()
    },
    getShiftsForDate(date) {
      return this.shifts.filter(s => s.shift_date === date)
    },
    isToday(date) {
      return date === new Date().toISOString().slice(0, 10)
    },
    formatDate(dt) {
      if (!dt) return '-'
      return new Date(dt).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
    },
    statusColor(s) {
      return { scheduled: 'info', active: 'success', completed: 'grey', absent: 'error' }[s] || 'grey'
    },
    statusLabel(s) {
      return { scheduled: 'Dijadwalkan', active: 'Aktif', completed: 'Selesai', absent: 'Tidak Hadir' }[s] || s
    },
    openCreate() {
      this.form = { id: null, driver_id: null, shift_name: '', start_time: '08:00', end_time: '16:00', shift_date: '', status: 'scheduled', notes: '' }
      this.dialog = true
    },
    openCreateForDate(date) {
      this.form = { id: null, driver_id: null, shift_name: '', start_time: '08:00', end_time: '16:00', shift_date: date, status: 'scheduled', notes: '' }
      this.dialog = true
    },
    openEdit(s) {
      this.form = { ...s, shift_date: s.shift_date ? s.shift_date.slice(0, 10) : '' }
      this.dialog = true
    },
    async save() {
      if (!this.$refs.form.validate()) return
      this.saving = true
      try {
        if (this.form.id) {
          await axios.put(`/driver-shifts/${this.form.id}`, this.form)
        } else {
          await axios.post('/driver-shifts', this.form)
        }
        this.$notify({ type: 'success', title: 'Berhasil', text: 'Shift tersimpan.' })
        this.dialog = false
        await this.loadShifts()
      } catch (e) {
        const msg = e.response && e.response.data && e.response.data.message
        this.$notify({ type: 'error', title: 'Gagal', text: msg || 'Shift gagal disimpan.' })
      } finally { this.saving = false }
    },
    async remove(s) {
      const result = await this.$swal.fire({ title: 'Hapus shift?', text: `${s.shift_name} - ${s.driver ? s.driver.name : ''}`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal' })
      if (!result.isConfirmed) return
      try {
        await axios.delete(`/driver-shifts/${s.id}`)
        this.$notify({ type: 'success', title: 'Berhasil', text: 'Shift dihapus.' })
        await this.loadShifts()
      } catch (e) { this.$notify({ type: 'error', title: 'Gagal', text: 'Shift gagal dihapus.' }) }
    },
  },
}
</script>

<style scoped>
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1px; background: #e0e0e0; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; }
.calendar-cell { min-height: 100px; background: white; padding: 4px; cursor: pointer; }
.calendar-cell:hover { background: #f5f5f5; }
.calendar-cell.today { background: #e3f2fd; }
.calendar-cell.other-month { background: #fafafa; opacity: 0.6; }
.calendar-day { font-weight: bold; font-size: 13px; margin-bottom: 4px; }
.calendar-event { font-size: 11px; padding: 2px 4px; border-radius: 4px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: white; }
.status-scheduled { background: #2196f3; }
.status-active { background: #4caf50; }
.status-completed { background: #9e9e9e; }
.status-absent { background: #f44336; }
.event-time { font-weight: bold; margin-right: 4px; }
</style>
