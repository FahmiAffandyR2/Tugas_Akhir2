<template>
  <section class="destinations">
    <h3 class="text-h6 font-weight-bold mb-3">Mau ke mana?</h3>
    <div class="stops-panel pa-4">
      <div class="d-flex align-center mb-4"><v-avatar color="success" size="30" class="mr-3"><v-icon small color="white">mdi-arrow-up</v-icon></v-avatar><span>{{ pickup.address || 'Lokasi penjemputan belum diisi' }}</span></div>
      <div v-for="(stop, index) in stops" :key="stop.key" class="stop-row mb-3">
        <button type="button" :class="['stop-number', { selected: activeKey === stop.key }]" :aria-label="'Pilih titik tujuan ' + (index + 1)" @click="activeKey = stop.key">{{ index + 1 }}</button>
        <div class="stop-input">
          <v-text-field v-model.trim="stop.address" :label="'Tujuan ' + (index + 1)" outlined dense hide-details="auto" :rules="rules" placeholder="Cari alamat atau pilih titik di peta" append-icon="mdi-magnify" @focus="activeKey = stop.key" @input="edit(stop)" @keyup.enter.prevent="search(stop)" @click:append="search(stop)" :loading="searching === stop.key" />
          <div v-if="resultsKey === stop.key" class="search-results">
            <button v-for="(result, i) in results" :key="i" type="button" @click="choose(stop, result)">{{ result.display_name }}</button>
            <p v-if="searchMessage" role="status" class="text-caption pa-2 mb-0">{{ searchMessage }}</p>
          </div>
        </div>
        <div class="stop-actions">
          <v-btn icon small :disabled="index === 0" :aria-label="'Naikkan tujuan ' + (index + 1)" @click="move(index, -1)"><v-icon small>mdi-arrow-up</v-icon></v-btn>
          <v-btn icon small :disabled="index === stops.length - 1" :aria-label="'Turunkan tujuan ' + (index + 1)" @click="move(index, 1)"><v-icon small>mdi-arrow-down</v-icon></v-btn>
          <v-btn icon small :disabled="stops.length === 1" :aria-label="'Hapus tujuan ' + (index + 1)" @click="remove(index)"><v-icon small>mdi-minus-circle-outline</v-icon></v-btn>
        </div>
      </div>
      <div class="d-flex justify-end"><v-btn outlined small color="primary" :disabled="stops.length >= 10" @click="add"><v-icon left small>mdi-plus-circle</v-icon>Tambah tujuan</v-btn></div>
    </div>
    <p class="text-caption mt-3 mb-2">Klik peta untuk menentukan tujuan {{ activeIndex + 1 }}. Gunakan panah untuk mengubah urutan kunjungan.</p>
    <v-alert v-if="mapError" dense text type="info">Peta belum dapat dimuat. Anda tetap bisa mengisi alamat tujuan.</v-alert>
    <div ref="map" class="destinations-map" aria-label="Peta titik tujuan bernomor"></div>
  </section>
</template>
<script>
import axios from 'axios'
let nextKey = 0
export default {
  props: { value: { type: Array, required: true }, pickup: { type: Object, required: true }, loadMap: { type: Function, required: true } },
  data: () => ({ stops: [], activeKey: null, map: null, markers: [], results: [], resultsKey: null, searching: null, searchMessage: '', mapError: false, requestId: 0, rules: [v => !!(v || '').trim() || 'Lokasi tujuan wajib diisi', v => (v || '').length <= 255 || 'Maksimal 255 karakter'] }),
  computed: { activeIndex() { return Math.max(0, this.stops.findIndex(stop => stop.key === this.activeKey)) } },
  watch: { pickup: { deep: true, handler() { this.renderMarkers() } } },
  created() {
    this.stops = (this.value.length ? this.value : [{ address: '', lat: null, lng: null }]).map(stop => ({ ...stop, key: ++nextKey }))
    this.activeKey = this.stops[0].key
  },
  async mounted() {
    try {
      const L = await this.loadMap()
      if (this._isDestroyed) return
      this.map = L.map(this.$refs.map).setView([-6.2, 106.8], 11)
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap', maxZoom: 19 }).addTo(this.map)
      this.map.on('click', event => this.setPoint(this.stops[this.activeIndex], event.latlng.lat, event.latlng.lng))
      this.renderMarkers()
      if (window.ResizeObserver) { this.mapObserver = new window.ResizeObserver(() => { if (this.map) this.map.invalidateSize() }); this.mapObserver.observe(this.$refs.map) }
      this.$nextTick(() => { if (this.map) this.map.invalidateSize() })
    } catch (_) { this.mapError = true }
  },
  beforeDestroy() { if (this.mapObserver) this.mapObserver.disconnect(); ++this.requestId; if (this.map) this.map.remove() },
  methods: {
    publish() { this.$emit('input', this.stops.map(({ address, lat, lng }) => ({ address, lat, lng }))); this.renderMarkers() },
    clearResults() { ++this.requestId; this.results = []; this.resultsKey = null; this.searching = null; this.searchMessage = '' },
    edit(stop) { stop.lat = null; stop.lng = null; this.clearResults(); this.publish() },
    add() { if (this.stops.length >= 10) return; const stop = { key: ++nextKey, address: '', lat: null, lng: null }; this.stops.push(stop); this.activeKey = stop.key; this.publish() },
    remove(index) { if (this.stops.length === 1) return; this.stops.splice(index, 1); if (!this.stops.some(s => s.key === this.activeKey)) this.activeKey = this.stops[0].key; this.clearResults(); this.publish() },
    move(index, direction) { const target = index + direction; if (target < 0 || target >= this.stops.length) return; const [stop] = this.stops.splice(index, 1); this.stops.splice(target, 0, stop); this.publish() },
    async search(stop) {
      this.clearResults()
      if (!stop.address) return
      const id = this.requestId
      this.searching = stop.key
      try {
        const response = await axios.get('https://nominatim.openstreetmap.org/search', { params: { q: stop.address, format: 'json', limit: 5, countrycodes: 'id' } })
        if (id !== this.requestId || this._isDestroyed) return
        this.resultsKey = stop.key; this.results = response.data || []; this.searchMessage = this.results.length ? '' : 'Alamat tidak ditemukan. Coba alamat lain atau klik peta.'
      } catch (_) { if (id === this.requestId) { this.resultsKey = stop.key; this.searchMessage = 'Pencarian belum tersedia. Isi alamat langsung atau klik peta.' } }
      finally { if (id === this.requestId) this.searching = null }
    },
    choose(stop, result) { this.clearResults(); stop.address = result.display_name.slice(0, 255); stop.lat = Number(result.lat); stop.lng = Number(result.lon); this.activeKey = stop.key; this.publish(); if (this.map) this.map.setView([stop.lat, stop.lng], 14) },
    async setPoint(stop, lat, lng) {
      this.clearResults(); stop.lat = lat; stop.lng = lng; stop.address = `${lat.toFixed(5)}, ${lng.toFixed(5)}`; const address = stop.address; this.publish()
      try {
        const response = await axios.get('https://nominatim.openstreetmap.org/reverse', { params: { lat, lon: lng, format: 'json' } })
        if (!this._isDestroyed && this.stops.includes(stop) && stop.lat === lat && stop.lng === lng && stop.address === address && response.data.display_name) { stop.address = response.data.display_name.slice(0, 255); this.publish() }
      } catch (_) { /* Coordinates remain usable when address lookup fails. */ }
    },
    renderMarkers() {
      if (!this.map) return
      this.markers.forEach(marker => marker.remove()); this.markers = []
      const L = window.L
      const points = []
      const add = (point, label, color, title) => {
        if (point.lat == null || point.lng == null || !Number.isFinite(Number(point.lat)) || !Number.isFinite(Number(point.lng))) return
        const latlng = [Number(point.lat), Number(point.lng)]; points.push(latlng)
        const icon = L.divIcon({ className: '', html: `<span style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;border:3px solid white;border-radius:50%;background:${color};color:white;font-weight:bold;box-shadow:0 2px 6px #777">${label}</span>`, iconSize: [32, 32], iconAnchor: [16, 16] })
        this.markers.push(L.marker(latlng, { icon, title }).addTo(this.map))
      }
      add(this.pickup, '↑', '#16a34a', 'Penjemputan')
      this.stops.forEach((stop, index) => add(stop, index + 1, '#f07812', `Tujuan ${index + 1}`))
      if (points.length) this.map.fitBounds(points, { padding: [35, 35], maxZoom: 14 })
    },
  },
}
</script>
<style scoped>
.stops-panel{border:1px solid #e5e1eb;border-radius:20px;background:#faf9fc}.stop-row{display:flex;align-items:flex-start;gap:10px}.stop-input{flex:1;min-width:0}.stop-number{flex:none;width:30px;height:30px;margin-top:5px;border-radius:50%;background:#f07812;color:white;font-weight:bold}.stop-number.selected{outline:3px solid #ffdbb4}.stop-actions{display:flex;padding-top:4px}.destinations-map{height:320px;border-radius:16px;border:1px solid #e5e1eb;z-index:0}.search-results button{display:block;width:100%;text-align:left;padding:10px;border-bottom:1px solid #eee;background:white}.search-results button:hover{background:#f1ebff}@media(max-width:600px){.stop-row{flex-wrap:wrap}.stop-actions{margin-left:auto;padding-top:0}.destinations-map{height:270px}}
</style>
