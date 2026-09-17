<template>
  <div>
    <div class="d-flex align-center flex-wrap mb-4"><div><h1 class="text-h5 font-weight-bold">Jadwal Mingguan</h1><p class="grey--text mb-0">Otomatis dari booking customer. Pilih booking untuk menetapkan bus dan driver.</p></div><v-spacer/><v-btn text @click="shift(-7)"><v-icon>mdi-chevron-left</v-icon>Minggu sebelumnya</v-btn><v-btn text @click="resetWeek">Minggu ini</v-btn><v-btn text @click="shift(7)">Berikutnya<v-icon>mdi-chevron-right</v-icon></v-btn><v-btn icon :loading="loading" @click="load"><v-icon>mdi-refresh</v-icon></v-btn></div>
    <v-alert v-if="error" type="error" text>{{ error }}</v-alert>
    <v-progress-linear v-if="loading" indeterminate />
    <div class="week-grid">
      <section v-for="day in days" :key="day.date" class="day-column">
        <h2 class="text-subtitle-1 font-weight-bold pa-3">{{ day.label }}</h2>
        <div v-if="!day.bookings.length" class="pa-3 text-caption grey--text">Belum ada booking</div>
        <v-card v-for="booking in day.bookings" :key="booking.id" outlined class="ma-2 pa-3" :class="{ 'closed-booking': ['cancelled','rejected'].includes(booking.status) }">
          <div class="font-weight-bold">{{ booking.reference_code }}</div>
          <v-chip x-small class="my-2" :color="booking.status === 'cancelled' ? 'grey' : 'primary'" outlined>{{ statusLabel(booking.status) }}</v-chip>
          <div class="text-caption">{{ booking.customer && booking.customer.name }}</div>
          <div class="text-caption mt-2" :title="booking.origin + ' → ' + booking.destination">{{ short(booking.origin) }} → {{ short(booking.destination) }}</div>
          <div class="text-caption mt-2">{{ booking.departure_date }} {{ (booking.departure_time || '').slice(0,5) }}<br>hingga {{ booking.return_date || booking.departure_date }} {{ (booking.return_time || '').slice(0,5) }}</div>
          <div class="text-caption mt-2">{{ booking.requested_bus_count || 1 }} unit • {{ booking.rental_days }} hari</div>
          <div v-for="(assignment,i) in booking.assignments" :key="assignment.id" class="text-caption mt-2"><strong>Unit {{ i+1 }}: {{ assignment.bus && assignment.bus.license }}</strong><br>{{ assignment.driver && assignment.driver.name }}</div>
          <div v-if="!booking.assignments.length && !['cancelled','rejected','completed'].includes(booking.status)" class="text-caption warning--text mt-2">Belum ditugaskan</div>
          <v-btn small text color="primary" class="mt-2" :to="{ name: 'charter-bookings', query: { booking: booking.id } }">Detail / Penugasan</v-btn>
          <v-btn v-if="!['cancelled','rejected','completed'].includes(booking.status)" small text @click="editSchedule(booking)">Ubah tanggal</v-btn>
        </v-card>
      </section>
    </div>
    <v-dialog v-model="dialog" max-width="550" :persistent="saving"><v-card><v-card-title>Perubahan tanggal customer</v-card-title><v-card-text><v-alert v-if="saveError" type="error" text dense>{{ saveError }}</v-alert><v-form ref="scheduleForm"><v-text-field v-model="form.departure_date" type="date" label="Tanggal berangkat" :rules="required" outlined/><v-text-field v-model="form.departure_time" type="time" label="Jam berangkat" :rules="required" outlined/><v-text-field v-model="form.return_date" type="date" label="Tanggal pulang" :min="form.departure_date" :rules="required" outlined/><v-text-field v-model="form.return_time" type="time" label="Jam pulang" :rules="required" outlined/></v-form><p class="text-caption">Perubahan memperbarui booking dan semua penugasan. Jadwal yang berbenturan atau sudah dimulai tidak dapat diubah.</p></v-card-text><v-card-actions><v-spacer/><v-btn text :disabled="saving" @click="dialog=false">Batal</v-btn><v-btn color="primary" :loading="saving" @click="save">Simpan</v-btn></v-card-actions></v-card></v-dialog>
  </div>
</template>
<script>
import axios from 'axios'
import { localDate } from '@/utils/bookingFlow'
export default {
 data: () => ({ start: '', bookings: [], loading: false, error: '', dialog: false, saving: false, saveError: '', selected: null, form: {}, timer: null, requestId: 0, required: [v => !!v || 'Wajib diisi'] }),
 computed: { days() { return Array.from({length:7}, (_,i) => { const date=new Date(this.start+'T12:00:00');date.setDate(date.getDate()+i);const key=localDate(date);return { date:key,label:new Intl.DateTimeFormat('id-ID',{weekday:'short',day:'numeric',month:'short'}).format(date),bookings:this.bookings.filter(b=>b.departure_date<=key && (b.return_date||b.departure_date)>=key) } }) } },
 created() { this.resetWeek() },
 mounted() { this.timer=window.setInterval(()=>{if(!document.hidden && !this.dialog)this.load()},30000) },
 beforeDestroy() { window.clearInterval(this.timer);++this.requestId },
 methods: {
  resetWeek() { const d=new Date();d.setDate(d.getDate()-((d.getDay()+6)%7));this.start=localDate(d);this.load() },
  shift(days) { const d=new Date(this.start+'T12:00:00');d.setDate(d.getDate()+days);this.start=localDate(d);this.load() },
  async load() { const id=++this.requestId;this.loading=true;try { const r=await axios.get('/charter-bookings/weekly-schedule',{params:{start:this.start}});if(id!==this.requestId)return;this.bookings=r.data.bookings||[];this.error='' } catch(e){if(id===this.requestId)this.error='Jadwal tidak dapat dimuat.'}finally{if(id===this.requestId)this.loading=false} },
  short(text) { return String(text||'-').split(',')[0] },
  statusLabel(s) { return {waiting_quote:'Menunggu konfirmasi',quote_sent:'Menunggu pembayaran',approved:'Disetujui',cancelled:'Dibatalkan',rejected:'Ditolak',completed:'Selesai'}[s]||s },
  editSchedule(b) {this.selected=b;this.form={departure_date:b.departure_date,departure_time:(b.departure_time||'').slice(0,5),return_date:b.return_date||b.departure_date,return_time:(b.return_time||'').slice(0,5)};this.saveError='';this.dialog=true},
  async save(){if(!this.$refs.scheduleForm.validate())return;this.saving=true;try{await axios.put(`/charter-bookings/${this.selected.id}/schedule`,this.form);this.dialog=false;await this.load();this.$notify({type:'success',title:'Jadwal diperbarui'})}catch(e){const d=e.response&&e.response.data;this.saveError=d&&d.errors?Object.values(d.errors).flat().join(' '):'Jadwal tidak dapat diperbarui.'}finally{this.saving=false}},
 },
}
</script>
<style scoped>
.week-grid{display:grid;grid-template-columns:repeat(7,minmax(205px,1fr));gap:10px;overflow-x:auto;padding-bottom:16px}.day-column{background:#f3f1f8;border-radius:12px;min-height:240px}.day-column .v-card{overflow-wrap:anywhere}.closed-booking{opacity:.65}
</style>
