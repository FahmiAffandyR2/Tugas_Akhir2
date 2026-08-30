<template>
  <div class="booking-page">
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold mb-2">Pesan Bus Pariwisata</h1>
      <p class="grey--text">Lengkapi kebutuhan perjalanan Anda. Sistem akan mengecek area layanan dan kapasitas armada.</p>
    </div>

    <v-stepper v-model="step" alt-labels class="booking-stepper" flat>
      <v-stepper-header>
        <template v-for="(label, i) in labels">
          <v-stepper-step :key="`s${i}`" :complete="step > i + 1" :step="i + 1">{{ label }}</v-stepper-step>
          <v-divider v-if="i < labels.length - 1" :key="`d${i}`" />
        </template>
      </v-stepper-header>

      <v-stepper-items>
        <v-stepper-content step="1">
          <v-form ref="tripForm" v-model="validTrip" lazy-validation>
            <h2 class="text-h6 font-weight-bold mb-5">Detail perjalanan</h2>
            <v-row>
              <v-col cols="12" md="4">
                <v-select v-model="form.tripType" outlined :items="tripTypes" label="Jenis perjalanan" :rules="required" />
              </v-col>
              <v-col cols="12" md="4">
                <v-menu v-model="dateMenu" :close-on-content-click="false" offset-y min-width="auto">
                  <template #activator="{ on, attrs }">
                    <v-text-field v-model="form.departureDate" outlined readonly label="Tanggal berangkat" prepend-inner-icon="mdi-calendar" v-bind="attrs" v-on="on" :rules="required" />
                  </template>
                  <v-date-picker v-model="form.departureDate" :min="today" @input="dateMenu = false" />
                </v-menu>
              </v-col>
              <v-col cols="12" md="4">
                <v-text-field v-model="form.departureTime" outlined type="time" label="Jam penjemputan" prepend-inner-icon="mdi-clock-outline" :rules="required" />
              </v-col>

              <template v-if="form.tripType === 'round_trip'">
                <v-col cols="12" md="6">
                  <v-text-field v-model="form.returnDate" outlined type="date" label="Tanggal pulang" :min="form.departureDate || today" :rules="required" />
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field v-model="form.returnTime" outlined type="time" label="Jam pulang" :rules="required" />
                </v-col>
              </template>

              <v-col cols="12" md="6">
                <v-select v-model="form.originAreaId" outlined :items="serviceAreas" item-text="name" item-value="id" label="Area penjemputan" prepend-inner-icon="mdi-map-marker" :rules="required" />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.trim="form.origin" outlined label="Detail lokasi penjemputan" placeholder="Contoh: SMK Negeri ..., Jakarta Selatan" :rules="required" />
              </v-col>
              <v-col cols="12" md="6">
                <div class="pickup-map-label mb-2">
                  <v-icon small color="primary" class="mr-1">mdi-map-marker</v-icon>
                  <span class="font-weight-bold">Lokasi penjemputan</span>
                  <span class="grey--text caption ml-2">(klik peta atau cari alamat)</span>
                </div>
                <v-text-field
                  v-model.trim="pickupSearch"
                  outlined
                  dense
                  hide-details
                  prepend-inner-icon="mdi-magnify"
                  placeholder="Cari alamat penjemputan..."
                  class="mb-2"
                  @keyup.enter="searchPickupAddress"
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
                </div>
              </v-col>
              <v-col cols="12" md="6">
                <v-select v-model="form.destinationAreaId" outlined :items="serviceAreas" item-text="name" item-value="id" label="Area tujuan" prepend-inner-icon="mdi-map-marker-check" :rules="required" />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.trim="form.destination" outlined label="Detail lokasi tujuan" placeholder="Contoh: Taman Mini Indonesia Indah" :rules="required" />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model.number="form.passengers" outlined type="number" min="1" label="Jumlah peserta" prepend-inner-icon="mdi-account-group" :rules="passengerRules" />
              </v-col>
              <v-col cols="12">
                <v-textarea v-model="form.notes" outlined rows="3" label="Catatan perjalanan (opsional)" placeholder="Contoh: study tour sekolah, membawa barang banyak, butuh mikrofon" />
              </v-col>
            </v-row>
          </v-form>
        </v-stepper-content>

        <v-stepper-content step="2">
          <h2 class="text-h6 font-weight-bold mb-2">Pilih rekomendasi armada</h2>
          <p class="grey--text mb-5">Sistem menghitung kebutuhan unit dan estimasi harga otomatis dari kategori bus.</p>
          <v-alert v-if="busTypes.length === 0 && !loadingOptions" type="warning" text>Belum ada kategori bus aktif.</v-alert>
          <v-row>
            <v-col v-for="bus in busTypes" :key="bus.id" cols="12" md="6">
              <v-card flat :class="['choice-card pa-5', { selected: form.busTypeId === bus.id, disabled: !bus.is_available }]" @click="selectBusType(bus)">
                <div class="d-flex align-start justify-space-between">
                  <div>
                    <h3 class="text-h6 font-weight-bold mb-1">{{ bus.name }}</h3>
                    <div class="primary--text body-2 mb-2">{{ bus.capacity }} kursi per bus · {{ currency(bus.base_price) }} dasar</div>
                  </div>
                  <v-icon :color="form.busTypeId === bus.id ? 'primary' : 'grey lighten-1'">{{ form.busTypeId === bus.id ? 'mdi-check-circle' : 'mdi-circle-outline' }}</v-icon>
                </div>
                <div class="body-2">
                  Tersedia {{ bus.available_buses }} unit · total {{ bus.total_capacity }} kursi
                  <span v-if="bus.required_buses">· butuh {{ bus.required_buses }} unit</span>
                </div>
                <div v-if="bus.estimated_price" class="estimate mt-4 pa-3">
                  <div><span>Estimasi jarak</span><strong>{{ bus.distance_km || 0 }} km</strong></div>
                  <div><span>Harga per unit</span><strong>{{ currency(bus.unit_price) }}</strong></div>
                  <div v-if="bus.required_buses > 1" class="capacity-warning">
                    <v-icon x-small color="warning">mdi-alert-outline</v-icon>
                    <span>{{ form.passengers }} peserta melebihi kapasitas 1 bus ({{ bus.capacity }} kursi). Diperlukan {{ bus.required_buses }} unit.</span>
                  </div>
                  <div class="total"><span>Total estimasi</span><strong>{{ currency(bus.estimated_price) }}</strong></div>
                </div>
                <v-alert v-if="!bus.is_available && bus.message" dense text type="error" class="mt-3 mb-0">{{ bus.message }}</v-alert>
              </v-card>
            </v-col>
          </v-row>
        </v-stepper-content>

        <v-stepper-content step="3">
          <h2 class="text-h6 font-weight-bold mb-5">Periksa kembali pemesanan</h2>
          <v-card flat class="summary pa-5">
            <v-row>
              <v-col cols="12" md="6"><div class="summary-label">Rute perjalanan</div><div class="font-weight-bold">{{ originAreaName }} - {{ destinationAreaName }}</div><div>{{ form.origin }} <v-icon small>mdi-arrow-right</v-icon> {{ form.destination }}</div></v-col>
              <v-col cols="6" md="3"><div class="summary-label">Berangkat</div><div class="font-weight-bold">{{ formattedDeparture }}</div></v-col>
              <v-col cols="6" md="3"><div class="summary-label">Pulang</div><div class="font-weight-bold">{{ formattedReturn }}</div></v-col>
              <v-col cols="6" md="3"><div class="summary-label">Peserta</div><div class="font-weight-bold">{{ form.passengers }} orang</div></v-col>
              <v-col cols="6" md="3"><div class="summary-label">Kategori bus</div><div class="font-weight-bold">{{ selectedBusType.name }}</div></v-col>
              <v-col cols="12" md="6"><div class="summary-label">Kebutuhan unit</div><div>{{ selectedBusType.required_buses || '-' }} unit dari {{ selectedBusType.available_buses || 0 }} unit tersedia</div></v-col>
              <v-col cols="12" md="6"><div class="summary-label">Estimasi jarak</div><div>{{ selectedBusType.distance_km || 0 }} km</div></v-col>
              <v-col cols="12" md="6"><div class="summary-label">Estimasi harga</div><div class="font-weight-bold primary--text">{{ currency(selectedBusType.estimated_price || 0) }}</div></v-col>
              <v-col v-if="selectedBusType.price_breakdown" cols="12">
                <div class="summary-label">Rincian harga</div>
                <div class="price-breakdown">
                  <div><span>Harga dasar</span><strong>{{ currency(selectedBusType.price_breakdown.base_price) }}</strong></div>
                  <div><span>Harga per km</span><strong>{{ currency(selectedBusType.price_breakdown.price_per_km) }} × {{ selectedBusType.price_breakdown.distance_km }} km</strong></div>
                  <div><span>Biaya jemput</span><strong>{{ currency(selectedBusType.price_breakdown.pickup_fee || 0) }}</strong></div>
                  <div><span>Harga minimum</span><strong>{{ currency(selectedBusType.price_breakdown.minimum_price) }}</strong></div>
                  <div><span>Jumlah unit</span><strong>{{ selectedBusType.price_breakdown.bus_count }} bus</strong></div>
                  <div class="breakdown-formula">{{ selectedBusType.price_breakdown.formula }}</div>
                </div>
              </v-col>
              <v-col cols="12"><div class="summary-label">Catatan</div><div>{{ form.notes || '-' }}</div></v-col>
            </v-row>
          </v-card>
          <v-alert type="info" text class="mt-5">Harga dihitung otomatis dari tipe bus, estimasi jarak, dan jumlah unit. Admin hanya akan melengkapi instruksi pembayaran.</v-alert>
        </v-stepper-content>
      </v-stepper-items>

      <div class="d-flex justify-space-between pa-5 pt-0">
        <v-btn v-if="step > 1" text @click="step--"><v-icon left>mdi-arrow-left</v-icon>Kembali</v-btn>
        <v-spacer />
        <v-btn color="primary" large :loading="submitting || loadingOptions" @click="next">
          {{ step === 3 ? 'Kirim Permintaan' : 'Lanjut' }}
          <v-icon right>{{ step === 3 ? 'mdi-send' : 'mdi-arrow-right' }}</v-icon>
        </v-btn>
      </div>
    </v-stepper>

    <v-snackbar v-model="saved" color="success" timeout="3500">
      Permintaan booking berhasil disimpan.
      <template #action="{ attrs }"><v-btn text v-bind="attrs" to="/customer/pemesanan">Lihat</v-btn></template>
    </v-snackbar>
  </div>
</template>

<script>
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
  data: () => ({
    step: 1,
    labels: ['Perjalanan', 'Pilih Bus', 'Konfirmasi'],
    validTrip: false,
    dateMenu: false,
    saved: false,
    submitting: false,
    loadingOptions: false,
    today: new Date().toISOString().slice(0, 10),
    required: [v => !!v || 'Wajib diisi'],
    passengerRules: [v => !!v || 'Wajib diisi', v => Number(v) > 0 || 'Minimal 1 peserta'],
    tripTypes: [{ text: 'Sekali jalan', value: 'one_way' }, { text: 'Pulang pergi', value: 'round_trip' }],
    serviceAreas: [],
    busTypes: [],
    pickupMap: null,
    pickupMarker: null,
    pickupSearch: '',
    pickupSearchResults: [],
    form: {
      tripType: 'one_way',
      originAreaId: null,
      destinationAreaId: null,
      origin: '',
      destination: '',
      departureDate: '',
      departureTime: '',
      returnDate: '',
      returnTime: '',
      passengers: null,
      notes: '',
      busTypeId: null,
      pickupLat: null,
      pickupLng: null,
    },
  }),
  computed: {
    selectedBusType() {
      return this.busTypes.find(bus => bus.id === this.form.busTypeId) || {};
    },
    originAreaName() {
      const area = this.serviceAreas.find(item => item.id === this.form.originAreaId);
      return area ? area.name : '-';
    },
    destinationAreaName() {
      const area = this.serviceAreas.find(item => item.id === this.form.destinationAreaId);
      return area ? area.name : '-';
    },
    formattedDeparture() {
      if (!this.form.departureDate) return '-';
      return `${this.formatDate(this.form.departureDate)} ${this.form.departureTime || ''}`;
    },
    formattedReturn() {
      if (this.form.tripType === 'one_way') return '-';
      if (!this.form.returnDate) return '-';
      return `${this.formatDate(this.form.returnDate)} ${this.form.returnTime || ''}`;
    },
  },
  mounted() {
    this.initPickupMap();
  },
  created() {
    this.loadOptions();
  },
  methods: {
    initPickupMap() {
      loadLeaflet().then((L) => {
        const defaultLat = parseFloat(process.env.VUE_APP_ORIGIN_LAT) || -6.2;
        const defaultLng = parseFloat(process.env.VUE_APP_ORIGIN_LNG) || 106.8;

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
    async loadOptions(withAvailability = false) {
      this.loadingOptions = true;
      try {
        const params = withAvailability ? {
          departure_date: this.form.departureDate,
          return_date: this.form.tripType === 'round_trip' ? this.form.returnDate : this.form.departureDate,
          passenger_count: this.form.passengers,
          trip_type: this.form.tripType,
          origin_area_id: this.form.originAreaId,
          destination_area_id: this.form.destinationAreaId,
        } : {};
        const response = await axios.get('/charter-bookings/options', { params });
        this.serviceAreas = response.data.service_areas || [];
        this.busTypes = response.data.bus_types || [];
        if (this.form.busTypeId && !this.selectedBusType.is_available) this.form.busTypeId = null;
      } catch (error) {
        this.notifyError(error, 'Opsi booking tidak dapat dimuat.');
      } finally {
        this.loadingOptions = false;
      }
    },
    selectBusType(bus) {
      if (!bus.is_available) {
        this.$notify({ type: 'warning', title: 'Armada tidak cukup', text: bus.message || 'Kategori bus ini tidak tersedia.' });
        return;
      }
      this.form.busTypeId = bus.id;
    },
    async next() {
      if (this.step === 1) {
        if (!this.$refs.tripForm.validate()) return;
        await this.loadOptions(true);
        this.step = 2;
        return;
      }
      if (this.step === 2) {
        if (!this.form.busTypeId) {
          this.$notify({ type: 'warning', title: 'Pilih kategori bus terlebih dahulu' });
          return;
        }
        this.step = 3;
        return;
      }
      await this.submit();
    },
    async submit() {
      this.submitting = true;
      try {
        const response = await axios.post('/charter-bookings', {
          origin_area_id: this.form.originAreaId,
          destination_area_id: this.form.destinationAreaId,
          origin: this.form.origin,
          destination: this.form.destination,
          trip_type: this.form.tripType,
          departure_date: this.form.departureDate,
          departure_time: this.form.departureTime,
          return_date: this.form.tripType === 'round_trip' ? this.form.returnDate : null,
          return_time: this.form.tripType === 'round_trip' ? this.form.returnTime : null,
          passenger_count: this.form.passengers,
          bus_type_id: this.form.busTypeId,
          notes: this.form.notes || null,
          pickup_lat: this.form.pickupLat || null,
          pickup_lng: this.form.pickupLng || null,
        });
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
      if (!this.pickupSearch) return;
      try {
        const r = await axios.get('https://nominatim.openstreetmap.org/search', {
          params: { q: this.pickupSearch, format: 'json', limit: 5, countrycodes: 'id' },
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
      this.form.origin = result.display_name;
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
  },
};
</script>

<style scoped>
.booking-page{max-width:1050px;margin:auto}.booking-stepper{border-radius:20px!important;border:1px solid #ececf3}.choice-card{height:100%;border:2px solid #ececf3;border-radius:16px!important;cursor:pointer;transition:.2s}.choice-card:hover,.choice-card.selected{border-color:#8b4df0;background:#faf7ff}.choice-card.disabled{cursor:not-allowed;opacity:.72;background:#fafafa}.estimate{border-radius:12px;background:#f8f7fb}.estimate div{display:flex;justify-content:space-between;gap:16px;font-size:.9rem}.estimate .total{margin-top:6px;padding-top:6px;border-top:1px solid #e6e2ee;color:#6f36d8}.capacity-warning{display:flex;align-items:center;gap:6px;margin-top:6px;padding:6px 8px;border-radius:8px;background:#fff8e1;color:#e65100;font-size:.82rem}.summary{background:#f8f7fb;border-radius:15px!important}.summary-label{font-size:.78rem;color:#8a8494;margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em}.price-breakdown{margin-top:6px;padding:12px;border-radius:10px;background:#f8f7fb;font-size:.88rem}.price-breakdown div{display:flex;justify-content:space-between;padding:3px 0}.price-breakdown .breakdown-formula{margin-top:8px;padding-top:8px;border-top:1px solid #e6e2ee;font-size:.78rem;color:#8a8494;font-style:italic}
.pickup-map-label{display:flex;align-items:center}.pickup-map-wrapper{position:relative;border-radius:12px;overflow:hidden;border:2px solid #ececf3}.pickup-map{width:100%;height:280px}.pickup-coord-chip{position:absolute;bottom:8px;left:8px;z-index:1000}.pickup-search-results{border:1px solid #ececf3;border-radius:8px;max-height:160px;overflow-y:auto;background:#fff}.pickup-search-item{padding:8px 12px;cursor:pointer;font-size:.85rem;display:flex;align-items:center;border-bottom:1px solid #f5f5f5}.pickup-search-item:hover{background:#f5f5f5}.pickup-search-item:last-child{border-bottom:none}
</style>
