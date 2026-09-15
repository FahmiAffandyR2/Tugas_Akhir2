<template>
  <div class="booking-page">
    <div class="mb-6"><h1 class="text-h4 font-weight-bold mb-2">Pesan Bus Pariwisata</h1><p class="grey--text">Pilih bus, lengkapi perjalanan, lalu periksa konfirmasi pemesanan.</p></div>
    <v-stepper v-model="step" class="booking-stepper" flat>
      <v-stepper-header><v-stepper-step :complete="step > 1" :step="1">Detail Perjalanan</v-stepper-step><v-divider /><v-stepper-step :step="2">Konfirmasi</v-stepper-step></v-stepper-header>
      <v-stepper-items>
        <v-stepper-content step="1">
          <v-form ref="tripForm" v-model="validTrip" lazy-validation>
            <booking-journey-fields v-model="form" :items="busItems" :loading="loadingOptions">
              <template #pickup><div class="pickup-map-label mb-2">
                  <v-icon small color="primary" class="mr-1">mdi-map-marker</v-icon>
                  <span class="font-weight-bold">Lokasi penjemputan</span>
                  <span class="grey--text caption ml-2">(klik peta atau cari alamat)</span>
                </div>
                <v-text-field
                  v-model.trim="form.origin"
                  :rules="[v => !!(v || '').trim() || 'Lokasi penjemputan wajib diisi', v => (v || '').length <= 255 || 'Maksimal 255 karakter']"
                  @input="form.pickupLat = null; form.pickupLng = null; pickupSearchResults = []; clearLocationMarker('pickup')"
                  outlined
                  dense
                  hide-details="auto"
                  prepend-inner-icon="mdi-magnify"
                  placeholder="Cari alamat penjemputan..."
                  class="mb-2"
                  @keyup.enter.prevent="searchPickupAddress"
                />
                <div v-if="pickupSearchResults.length" class="pickup-search-results mb-2">
                  <div
                    v-for="(r, i) in pickupSearchResults"
                    :key="i"
                    class="pickup-search-item"
                    @click="selectPickupSearchResult(r)"
                  >
                    <v-icon x-small class="mr-2">mdi-map-marker</v-icon>
                    {{ r.display_name }}
                  </div>
                </div>
                <div class="pickup-map-wrapper">
                  <div ref="pickupMap" class="pickup-map"></div>
                  <v-chip v-if="form.pickupLat && form.pickupLng" small color="success" text-color="white" class="pickup-coord-chip">
                    <v-icon left small>mdi-check-circle</v-icon>
                    {{ form.pickupLat.toFixed(5) }}, {{ form.pickupLng.toFixed(5) }}
                  </v-chip>
                  <v-chip v-else small color="grey lighten-1" text-color="white" class="pickup-coord-chip">
                    <v-icon left small>mdi-map-marker-account</v-icon>
                    Belum ditandai
                  </v-chip>
                </div></template>
              <template #destination>
                <booking-destinations v-model="form.destinations" :pickup="{ address: form.origin, lat: form.pickupLat, lng: form.pickupLng }" :load-map="loadDestinationMap" />
              </template>
            </booking-journey-fields>
            <v-textarea v-model="form.notes" outlined rows="3" label="Catatan perjalanan (opsional)" class="mt-5" />
          </v-form>
        </v-stepper-content>
        <v-stepper-content step="2">
          <h2 class="text-h6 font-weight-bold mb-5">Periksa kembali pemesanan</h2>
          <v-card flat class="summary pa-5"><v-row>
            <v-col cols="12" md="6"><div class="summary-label">Jenis perjalanan</div>{{ form.tripStyle === 'day_trip' ? 'Day Trip' : 'Menginap' }}<div>Lama penggunaan: {{ form.tripStyle === 'overnight' ? form.rentalDays : 1 }} hari</div></v-col>
            <v-col cols="12" md="6"><div class="summary-label">Bus pilihan</div>{{ selectedBusType.name }} · 1 unit</v-col>
            <v-col cols="12" md="6"><div class="summary-label">Data Pergi</div><strong>{{ form.origin }}</strong><div>{{ formattedDeparture }}</div></v-col>
            <v-col cols="12" md="6"><div class="summary-label">Data Pulang</div><div>{{ formattedReturn }}</div></v-col>
            <v-col cols="12" md="6"><div class="summary-label">Harga Dasar Bus</div><strong>{{ currency(selectedBusType.base_price) }}</strong></v-col>
            <v-col cols="12" md="6"><div class="summary-label">Estimasi total</div><strong class="primary--text">{{ currency(selectedBusType.estimated_price) }}</strong></v-col>
            <v-col cols="12"><div class="summary-label">Urutan tujuan</div><ol><li v-for="(stop, i) in form.destinations" :key="i">{{ stop.address }}</li></ol></v-col>
            <v-col cols="12"><div class="summary-label">Catatan</div>{{ form.notes || '-' }}</v-col>
          </v-row></v-card>
          <v-alert type="info" text class="mt-5">Anda memesan satu bus penuh. Harga total mengikuti rincian penawaran dan instruksi pembayaran dari admin.</v-alert>
        </v-stepper-content>
      </v-stepper-items>
      <div class="d-flex justify-space-between pa-5"><v-btn v-if="step > 1" text @click="step = 1">Kembali</v-btn><v-spacer /><v-btn color="primary" :loading="submitting || loadingOptions" :disabled="submitting || loadingOptions" @click="next">{{ step === 2 ? 'Kirim Permintaan' : 'Lanjut ke Konfirmasi' }}<v-icon right>{{ step === 2 ? 'mdi-send' : 'mdi-arrow-right' }}</v-icon></v-btn></div>
    </v-stepper>
  </div>
</template>

<script>
import axios from 'axios'
import BookingDestinations from '@/components/BookingDestinations.vue'
import BookingJourneyFields from '@/components/BookingJourneyFields.vue'
import { busOptions, draftKey, effectiveReturnDate, validateJourney } from '@/utils/bookingFlow'

const LEAFLET_CDN = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
const LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';

function loadLeaflet() {
  if (window.L) return Promise.resolve(window.L);
  if (window.__leafletLoader) return window.__leafletLoader;
  window.__leafletLoader = new Promise((resolve, reject) => {
    if (!document.querySelector(`link[href="${LEAFLET_CSS}"]`)) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = LEAFLET_CSS;
      document.head.appendChild(link);
    }
    const script = document.createElement('script');
    script.src = LEAFLET_CDN;
    script.onload = () => resolve(window.L);
    script.onerror = reject;
    document.head.appendChild(script);
  });
  return window.__leafletLoader;
}

export default {
  components: { BookingJourneyFields, BookingDestinations },
  data: () => ({
    step: 1,
    validTrip: false,
    saved: false,
    submitting: false,
    loadingOptions: false,
    optionsRequestId: 0,
    busTypes: [],
    pickupMap: null,
    pickupMarker: null,
    pickupSearchResults: [],
    form: {
      tripType: 'round_trip',
      tripStyle: 'day_trip',
      rentalDays: null,
      origin: '',
      destination: '',
      destinations: [{ address: '', lat: null, lng: null }],
      departureDate: '',
      departureTime: '',
      returnDate: '',
      returnTime: '',
      notes: '',
      busTypeId: null,
      pickupLat: null,
      pickupLng: null,
      destLat: null,
      destLng: null,
    },
  }),
  computed: {
    availabilityDates() { return `${this.form.departureDate}/${effectiveReturnDate(this.form)}`; },
    busItems() { return busOptions(this.busTypes); },
    selectedBusType() {
      return this.busItems.find(bus => bus.id === this.form.busTypeId) || {};
    },
    formattedDeparture() {
      if (!this.form.departureDate) return '-';
      return `${this.formatDate(this.form.departureDate)} ${this.form.departureTime || ''}`;
    },
    formattedReturn() {
      const date = effectiveReturnDate(this.form);
      return date ? `${this.formatDate(date)} ${this.form.returnTime || ''}` : '-';
    },
  },
  watch: {
    'form.destinations': { deep: true, handler(stops) {
      const last = stops[stops.length - 1] || {};
      this.form.destination = last.address || '';
      this.form.destLat = last.lat == null ? null : last.lat;
      this.form.destLng = last.lng == null ? null : last.lng;
    } },
    availabilityDates() { this.loadOptions(); },
    step() { this.$nextTick(() => { if (this.pickupMap) this.pickupMap.invalidateSize(); }); },
  },
  mounted() {
    this.initPickupMap();

  },
  beforeDestroy() {
    if (this.pickupMap) {
      this.pickupMap.remove();
      this.pickupMap = null;
    }
  },
  created() {
    try {
      const draft = JSON.parse(sessionStorage.getItem(draftKey) || 'null');
      if (draft) ['tripStyle', 'rentalDays', 'busTypeId', 'departureDate', 'departureTime', 'origin', 'destination', 'returnDate', 'returnTime'].forEach(key => { if (draft[key] !== undefined) this.form[key] = draft[key]; });
    } catch (_) { sessionStorage.removeItem(draftKey); }
    if (this.form.destination) this.form.destinations = [{ address: this.form.destination, lat: null, lng: null }];
    this.loadOptions();
  },
  methods: {
    loadDestinationMap() { return loadLeaflet(); },
    initPickupMap() {
      loadLeaflet().then((L) => {
        const defaultLat = parseFloat(process.env.VUE_APP_ORIGIN_LAT) || -6.2;
        const defaultLng = parseFloat(process.env.VUE_APP_ORIGIN_LNG) || 106.8;

        if (!this.$refs.pickupMap || this._isDestroyed) return;
        this.pickupMap = L.map(this.$refs.pickupMap, {
          center: [defaultLat, defaultLng],
          zoom: 12,
          zoomControl: true,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          attribution: '&copy; OpenStreetMap',
          maxZoom: 19,
        }).addTo(this.pickupMap);

        this.pickupMap.on('click', (e) => {
          const { lat, lng } = e.latlng;
          this.form.pickupLat = lat;
          this.form.pickupLng = lng;
          this.form.origin = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
          this.reverseGeocodeLocation(lat, lng, 'pickup');

          if (this.pickupMarker) {
            this.pickupMarker.setLatLng([lat, lng]);
          } else {
            this.pickupMarker = L.marker([lat, lng], {
              icon: L.icon({
                iconUrl: 'https://cdn-icons-png.flaticon.com/32/684/684908.png',
                iconSize: [28, 28],
                iconAnchor: [14, 28],
                popupAnchor: [0, -28],
              }),
            }).addTo(this.pickupMap);
          }

          this.pickupMarker.bindPopup('Lokasi penjemputan Anda').openPopup();
        });

        this.$nextTick(() => this.pickupMap.invalidateSize());
      });
    },
    async loadOptions() {
      const requestId = ++this.optionsRequestId;
      this.loadingOptions = true;
      try {
        const params = {
          departure_date: this.form.departureDate || undefined,
          return_date: effectiveReturnDate(this.form) || undefined,
          passenger_count: 1,
          trip_type: this.form.tripType,
        };
        const response = await axios.get('/charter-bookings/options', { params });
        if (requestId !== this.optionsRequestId) return false;
        this.busTypes = response.data.bus_types || [];
        if (this.form.busTypeId && (!this.selectedBusType.id || this.selectedBusType.disabled)) this.form.busTypeId = null;
        return true;
      } catch (error) {
        if (requestId !== this.optionsRequestId) return false;
        this.busTypes = [];
        this.notifyError(error, 'Opsi booking tidak dapat dimuat.');
        return false;
      } finally {
        if (requestId === this.optionsRequestId) this.loadingOptions = false;
      }
    },
    async next() {
      if (this.submitting || this.loadingOptions) return;
      if (this.step === 1) {
        if (!this.$refs.tripForm.validate()) return;
        const message = validateJourney(this.form);
        if (message) { this.$notify({ type: 'warning', title: 'Periksa perjalanan', text: message }); return; }
        if (!await this.loadOptions()) return;
        if (!this.form.busTypeId || this.selectedBusType.disabled) { this.$notify({ type: 'warning', title: 'Bus tidak tersedia pada tanggal pilihan' }); return; }
        this.step = 2;
        return;
      }
      await this.submit();
    },
    async submit() {
      this.submitting = true;
      try {
        const response = await axios.post('/charter-bookings', {
          origin: this.form.origin,
          destination: this.form.destination,
          destinations: this.form.destinations.map(({ address, lat, lng }) => ({ address, lat, lng })),
          trip_type: 'round_trip',
          trip_style: this.form.tripStyle,
          rental_days: this.form.tripStyle === 'overnight' ? Number(this.form.rentalDays) : 1,
          departure_date: this.form.departureDate,
          departure_time: this.form.departureTime,
          return_date: effectiveReturnDate(this.form),
          return_time: this.form.returnTime,
          requested_bus_count: 1,
          bus_type_id: this.form.busTypeId,
          notes: this.form.notes || null,
          pickup_lat: this.form.pickupLat,
          pickup_lng: this.form.pickupLng,
          dropoff_lat: this.form.destLat,
          dropoff_lng: this.form.destLng,
        });
        sessionStorage.removeItem(draftKey);
        this.saved = true;
        this.$notify({ type: 'success', title: 'Booking terkirim', text: response.data.message });
        setTimeout(() => this.$router.push('/customer/pemesanan'), 700);
      } catch (error) {
        this.notifyError(error, 'Permintaan booking gagal dikirim.');
      } finally {
        this.submitting = false;
      }
    },
    formatDate(date) {
      return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${date}T00:00:00`));
    },
    currency(value) {
      return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
    },
    notifyError(error, fallback) {
      const data = error.response && error.response.data;
      const errors = data && data.errors;
      const message = errors ? Object.values(errors).reduce((all, messages) => all.concat(messages), []).join(' ') : (data && data.message) || fallback;
      this.$notify({ type: 'error', title: 'Gagal', text: message });
    },
    async searchPickupAddress() {
      if (!this.form.origin) return;
      try {
        const r = await axios.get('https://nominatim.openstreetmap.org/search', {
          params: { q: this.form.origin, format: 'json', limit: 5, countrycodes: 'id' },
          headers: { 'Accept-Language': 'id' },
        });
        this.pickupSearchResults = r.data || [];
      } catch (e) {
        this.pickupSearchResults = [];
      }
    },
    selectPickupSearchResult(result) {
      const lat = parseFloat(result.lat);
      const lng = parseFloat(result.lon);
      this.form.pickupLat = lat;
      this.form.pickupLng = lng;
      this.form.origin = result.display_name.slice(0, 255);
      this.pickupSearchResults = [];

      if (this.pickupMap) {
        this.pickupMap.setView([lat, lng], 15);
        if (this.pickupMarker) {
          this.pickupMarker.setLatLng([lat, lng]);
        } else {
          this.pickupMarker = window.L.marker([lat, lng], {
            icon: window.L.icon({
              iconUrl: 'https://cdn-icons-png.flaticon.com/32/684/684908.png',
              iconSize: [28, 28],
              iconAnchor: [14, 28],
              popupAnchor: [0, -28],
            }),
          }).addTo(this.pickupMap);
        }
        this.pickupMarker.bindPopup('Lokasi penjemputan Anda').openPopup();
      }
    },
    clearLocationMarker(kind) {
      const key = kind === 'pickup' ? 'pickupMarker' : 'destMarker';
      if (this[key]) { this[key].remove(); this[key] = null; }
    },
    async reverseGeocodeLocation(lat, lng, kind) {
      const field = kind === 'pickup' ? 'origin' : 'destination';
      const latKey = kind === 'pickup' ? 'pickupLat' : 'destLat';
      const lngKey = kind === 'pickup' ? 'pickupLng' : 'destLng';
      const original = this.form[field];
      try {
        const r = await axios.get('https://nominatim.openstreetmap.org/reverse', {
          params: { lat, lon: lng, format: 'json', countrycodes: 'id' },
          headers: { 'Accept-Language': 'id' },
        });
        if (this.form[latKey] === lat && this.form[lngKey] === lng && this.form[field] === original && r.data && r.data.display_name) {
          this.form[field] = r.data.display_name.slice(0, 255);
        }
      } catch (e) { /* Keep the selected coordinates when address lookup fails. */ }
    },
  },
};
</script>

<style scoped>
.booking-page{max-width:1050px;margin:auto}.booking-stepper{border-radius:20px!important;border:1px solid #ececf3}.choice-card{height:100%;border:2px solid #ececf3;border-radius:16px!important;cursor:pointer;transition:.2s}.choice-card:hover,.choice-card.selected{border-color:#8b4df0;background:#faf7ff}.choice-card.disabled{cursor:not-allowed;opacity:.72;background:#fafafa}.estimate{border-radius:12px;background:#f8f7fb}.estimate div{display:flex;justify-content:space-between;gap:16px;font-size:.9rem}.estimate .total{margin-top:6px;padding-top:6px;border-top:1px solid #e6e2ee;color:#6f36d8}.capacity-warning{display:flex;align-items:center;gap:6px;margin-top:6px;padding:6px 8px;border-radius:8px;background:#fff8e1;color:#e65100;font-size:.82rem}.summary{background:#f8f7fb;border-radius:15px!important}.summary-label{font-size:.78rem;color:#8a8494;margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em}.price-breakdown{margin-top:6px;padding:12px;border-radius:10px;background:#f8f7fb;font-size:.88rem}.price-breakdown div{display:flex;justify-content:space-between;padding:3px 0}.price-breakdown .breakdown-formula{margin-top:8px;padding-top:8px;border-top:1px solid #e6e2ee;font-size:.78rem;color:#8a8494;font-style:italic}
.pickup-map-label{display:flex;align-items:center}.pickup-map-wrapper{position:relative;border-radius:12px;overflow:hidden;border:2px solid #ececf3}.pickup-map{width:100%;height:280px}.pickup-coord-chip{position:absolute;bottom:8px;left:8px;z-index:1000}.pickup-search-results{border:1px solid #ececf3;border-radius:8px;max-height:160px;overflow-y:auto;background:#fff}.pickup-search-item{padding:8px 12px;cursor:pointer;font-size:.85rem;display:flex;align-items:center;border-bottom:1px solid #f5f5f5}.pickup-search-item:hover{background:#f5f5f5}.pickup-search-item:last-child{border-bottom:none}
</style>
