<template>
  <div>
    <v-radio-group v-model="value.tripStyle" row label="Jenis perjalanan" class="mt-0">
      <v-radio label="Day Trip" value="day_trip" /><v-radio label="Menginap" value="overnight" />
    </v-radio-group>
    <v-text-field v-if="value.tripStyle === 'overnight'" v-model.number="value.rentalDays" type="number" min="2" max="365" step="1" outlined label="Lama penggunaan bus (hari)" suffix="hari" :rules="[v => Number.isInteger(Number(v)) && Number(v) >= 2 && Number(v) <= 365 || 'Isi 2?365 hari']" hint="Termasuk hari berangkat dan pulang. Contoh: 3 hari mulai tanggal 15, pulang tanggal 17." persistent-hint class="mb-4" />
    <h3 class="text-subtitle-1 font-weight-bold mb-3">Data Pergi</h3>
    <v-row dense>
      <v-col cols="7"><v-text-field v-model="value.departureDate" type="date" outlined label="Tanggal Berangkat" :min="today" :rules="required" /></v-col>
      <v-col cols="5"><v-text-field v-model="value.departureTime" type="time" outlined label="Jam jemput" :rules="required" /></v-col>
    </v-row>
    <slot name="pickup">
      <v-text-field v-model.trim="value.origin" outlined label="Lokasi penjemputan" prepend-inner-icon="mdi-map-marker" :rules="required" />
    </slot>
    <slot name="destination">
      <v-text-field v-model.trim="value.destination" outlined label="Lokasi tujuan" prepend-inner-icon="mdi-map-marker-check" :rules="required" />
    </slot>
    <div v-show="departureReady">
      <h3 class="text-subtitle-1 font-weight-bold mt-4 mb-3">Data Pulang</h3>
      <v-row dense>
        <v-col cols="7"><v-text-field :value="returnDate" type="date" outlined label="Tanggal Pulang" readonly :min="minReturn" :rules="departureReady ? required : []" /></v-col>
        <v-col cols="5"><v-text-field v-model="value.returnTime" type="time" outlined label="Jam pulang" :rules="departureReady ? required : []" /></v-col>
      </v-row>
    </div>
    <p v-if="!departureReady" class="text-caption grey--text mt-4">Lengkapi tanggal berangkat dan lokasi penjemputan untuk membuka Data Pulang.</p>
    <v-select v-model="value.busTypeId" class="mt-5" :items="items" item-text="text" item-value="id" item-disabled="disabled" outlined label="Pilih Bus" prepend-inner-icon="mdi-bus" :loading="loading" :rules="required" no-data-text="Belum ada armada aktif yang tersedia" />
  </div>
</template>

<script>
import { effectiveReturnDate, localDate, nextDate } from '@/utils/bookingFlow'
export default {
  props: { value: { type: Object, required: true }, items: { type: Array, default: () => [] }, areas: { type: Array, default: () => [] }, loading: Boolean },
  data: () => ({ today: localDate(), required: [value => !!value || 'Wajib diisi'] }),
  computed: {
    departureReady() { return Boolean(this.value.departureDate && this.value.origin.trim()) },
    returnDate() { return effectiveReturnDate(this.value) },
    minReturn() { return this.value.tripStyle === 'overnight' ? nextDate(this.value.departureDate) : this.value.departureDate },
  },
}
</script>
