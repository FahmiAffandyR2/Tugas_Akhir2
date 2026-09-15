<template>
  <div>
    <v-card class="booking-card">
      <v-card-title class="pa-5">
        <v-icon color="primary" class="mr-2">mdi-clipboard-text-clock-outline</v-icon>
        <span>Booking Bus Pariwisata</span>
        <v-spacer />
        <v-chip color="orange lighten-5" text-color="orange darken-3">{{ waitingCount }} menunggu</v-chip>
      </v-card-title>

      <v-card-text>
        <div class="d-flex flex-wrap align-center mb-4">
          <v-text-field
            v-model="search"
            outlined
            dense
            hide-details
            prepend-inner-icon="mdi-magnify"
            label="Cari customer, rute, atau nomor booking"
            class="search mr-3"
          />
          <v-select
            v-model="statusFilter"
            :items="statusOptions"
            outlined
            dense
            hide-details
            label="Status"
            clearable
            class="status-filter mr-3"
          />
          <v-select
            v-model="paymentStatusFilter"
            :items="paymentStatusOptions"
            outlined
            dense
            hide-details
            label="Status Pembayaran"
            clearable
            class="status-filter"
          />
        </div>

        <v-data-table :headers="headers" :items="filteredBookings" :loading="loading" :search="search" item-key="id">
          <template #item.reference_code="{ item }">
            <span class="font-weight-bold primary--text">{{ item.reference_code }}</span>
          </template>
          <template #item.customer="{ item }">
            <div class="font-weight-medium">{{ item.customer.name }}</div>
            <small class="grey--text">{{ item.customer.email }}</small>
          </template>
          <template #item.route="{ item }">
            <div class="route-summary">
              <div class="route-location" :title="item.origin">{{ shortLocation(item.origin) }}</div>
              <div class="d-flex align-center grey--text">
                <v-icon x-small class="mr-1">mdi-arrow-right</v-icon>
                <small class="route-location" :title="item.destination">{{ shortLocation(item.destination) }}</small>
              </div>
              <small v-if="item.destinations && item.destinations.length > 1" class="grey--text">{{ item.destinations.length }} titik tujuan</small>
            </div>
          </template>
          <template #item.departure_date="{ item }">{{ formatDate(item.departure_date) }}</template>
          <template #item.bus_type="{ item }">
            {{ busName(item) }}<br>
            <small>{{ item.passenger_count }} peserta - {{ item.requested_bus_count || 1 }} unit</small>
          </template>
          <template #item.quoted_price="{ item }">{{ item.quoted_price ? currency(item.quoted_price) : '-' }}</template>
          <template #item.payment_status="{ item }">
            <v-chip small :color="paymentInfo(item.payment_status).color" dark>{{ paymentInfo(item.payment_status).label }}</v-chip>
          </template>
          <template #item.status="{ item }">
            <v-chip small :color="statusInfo(item.status).color" dark>{{ statusInfo(item.status).label }}</v-chip>
          </template>
          <template #item.actions="{ item }">
            <v-btn small color="primary" text @click="openBooking(item)">
              <v-icon small left>mdi-eye-outline</v-icon>Proses
            </v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="dialog" max-width="820" persistent>
      <v-card v-if="selected">
        <v-card-title class="pa-5">
          <div>
            <div class="text-h6 font-weight-bold">{{ selected.reference_code }}</div>
            <div class="caption grey--text">Permintaan dari {{ selected.customer.name }}</div>
          </div>
          <v-spacer />
          <v-btn icon @click="dialog = false"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>

        <v-divider />

        <v-card-text class="pa-6">
          <v-row>
            <v-col cols="12" sm="6">
              <div class="label">Rute perjalanan</div><div v-if="selected.rental_days">Lama penggunaan: {{ selected.rental_days }} hari</div>
              <strong>{{ areaName(selected.origin_area) }} - {{ areaName(selected.destination_area) }}</strong>
              <div>{{ selected.origin }} - {{ selected.destination }}</div><ol v-if="selected.destinations"><li v-for="(stop, i) in selected.destinations" :key="i">{{ stop.address }}</li></ol>
            </v-col>
            <v-col cols="6" sm="3">
              <div class="label">Berangkat</div>
              <strong>{{ formatDate(selected.departure_date) }} {{ selected.departure_time ? selected.departure_time.slice(0, 5) : '' }}</strong>
            </v-col>
            <v-col cols="6" sm="3">
              <div class="label">Pulang</div>
              <strong>{{ selected.return_date ? `${formatDate(selected.return_date)} ${selected.return_time ? selected.return_time.slice(0, 5) : ''}` : '-' }}</strong>
            </v-col>
            <v-col cols="6" sm="3">
              <div class="label">{{ selected.price_breakdown && selected.price_breakdown.booking_mode === 'whole_bus' ? 'Kapasitas dipesan' : 'Peserta' }}</div>
              <strong>{{ selected.passenger_count }} orang</strong>
            </v-col>
            <v-col cols="6" sm="3">
              <div class="label">Kebutuhan unit</div>
              <strong>{{ selected.requested_bus_count || 1 }} bus</strong>
            </v-col>
            <v-col cols="12" sm="6">
              <div class="label">Bus yang diminta</div>
              <strong>{{ busName(selected) }}</strong>
            </v-col>
            <v-col cols="12" sm="6">
              <div class="label">Kontak customer</div>
              <strong>{{ selected.customer.tel_number || '-' }}</strong>
            </v-col>
            <v-col cols="12">
              <div class="label">Catatan customer</div>
              <div>{{ selected.notes || '-' }}</div>
            </v-col>
          </v-row>

          <v-alert v-if="selected.payment_status === 'pending_verification'" type="warning" text class="mt-5">
            <div class="font-weight-bold mb-1">Pembayaran menunggu verifikasi</div>
            <div>Metode: {{ paymentMethod(selected.payment_method) }} - Referensi: {{ selected.payment_reference }}</div>
            <v-btn small outlined color="primary" class="mt-3" :loading="openingProof" @click="viewProof">
              <v-icon left small>mdi-file-eye-outline</v-icon>Lihat Bukti
            </v-btn>
            <div class="mt-4">
              <v-btn small color="success" :loading="reviewing" @click="reviewPayment('verify')">
                <v-icon left small>mdi-check-decagram</v-icon>Verifikasi Lunas
              </v-btn>
              <v-btn small color="error" outlined class="ml-2" @click="rejectDialog = true">
                <v-icon left small>mdi-close-circle-outline</v-icon>Tolak
              </v-btn>
            </div>
          </v-alert>
          <v-alert v-else-if="selected.payment_status === 'paid'" type="success" text class="mt-5">
            <strong>Pembayaran lunas</strong>
            <div class="caption">Diverifikasi {{ formatDateTime(selected.paid_at) }}</div>
          </v-alert>
          <v-alert v-else-if="selected.payment_status === 'rejected'" type="error" text class="mt-5">
            <strong>Pembayaran ditolak</strong>
            <div>{{ selected.payment_rejection_reason }}</div>
          </v-alert>

          <v-alert v-if="selected.payment_deadline && selected.payment_status !== 'paid' && ['quote_sent', 'approved'].includes(selected.status)" type="warning" text dense class="mt-5">
            <strong>Batas waktu pembayaran:</strong> {{ formatDateTime(selected.payment_deadline) }}
            <span v-if="isPaymentOverdue(selected)"> — <strong class="error--text">Sudah lewat</strong></span>
          </v-alert>

          <v-alert v-if="selected.operational_planned_trip_id" type="info" text class="mt-5">
            <strong>Jadwal operasional sudah dibuat</strong>
            <div class="caption">Perjalanan #{{ selected.operational_planned_trip_id }} sudah masuk ke jadwal driver dan live tracking.</div>
          </v-alert>

          <v-divider class="my-5" />

          <div v-if="selected.price_breakdown" class="mb-4">Harga Dasar Bus: <strong>{{ currency(selected.price_breakdown.base_price) }}</strong></div>
          <v-form ref="form" v-model="valid">
            <v-row>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model.number="edit.quoted_price"
                  outlined
                  type="number"
                  min="0"
                  label="Harga otomatis (IDR)"
                  prepend-inner-icon="mdi-cash"
                  readonly
                  :hint="selected.payment_status === 'paid' ? 'Pembayaran lunas' : 'Dihitung dari tipe bus, estimasi jarak, dan jumlah unit'"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select v-model="edit.status" outlined label="Status booking" :items="statusOptions" :rules="[v => !!v || 'Status wajib dipilih']" />
              </v-col>
              <v-col cols="12">
                <v-textarea v-model="edit.admin_notes" outlined rows="2" label="Catatan untuk customer" />
              </v-col>
              <v-col cols="12">
                <div class="text-subtitle-1 font-weight-bold mb-2">Rekening tujuan pembayaran</div>
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model.trim="edit.payment_bank_name" outlined label="Nama bank" :rules="bankRules" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model.trim="edit.payment_account_number" outlined label="Nomor rekening" :rules="bankRules" />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field v-model.trim="edit.payment_account_holder" outlined label="Nama pemilik rekening" :rules="bankRules" />
              </v-col>

              <v-col cols="12">
                <v-divider class="mb-5" />
                <div class="text-subtitle-1 font-weight-bold">Penugasan operasional</div>
                <div class="caption grey--text mb-3">Bus dan driver baru diteruskan ke customer setelah booking disetujui dan pembayaran lunas.</div>
              </v-col>

              <v-col v-if="!canAssignOperational" cols="12">
                <v-alert type="info" text class="assignment-waiting">
                  <div class="font-weight-bold mb-1">Penugasan belum dibuka</div>
                  <div>{{ assignmentLockedMessage }}</div>
                </v-alert>
              </v-col>

              <template v-else>
                <v-col v-for="(assignment, index) in edit.assignments" :key="`assignment-${index}`" cols="12">
                  <v-card outlined class="assignment-row pa-4">
                    <div class="font-weight-bold mb-3">Unit {{ index + 1 }}</div>
                    <v-row>
                      <v-col cols="12" sm="6">
                        <v-select v-model="assignment.bus_id" :items="busOptions" outlined clearable label="Pilih bus" :loading="loadingOptions" />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select v-model="assignment.driver_id" :items="driverOptions" outlined clearable label="Pilih driver" :loading="loadingOptions" />
                      </v-col>
                    </v-row>
                  </v-card>
                </v-col>
                <v-col cols="12" sm="4">
                  <v-text-field v-model="edit.departure_time" type="time" outlined label="Jam berangkat" />
                </v-col>
                <v-col cols="12" sm="4">
                  <v-text-field v-model="edit.return_date" type="date" outlined label="Tanggal kembali" :min="selected.departure_date" />
                </v-col>
                <v-col cols="12" sm="4">
                  <v-text-field v-model="edit.return_time" type="time" outlined label="Jam kembali" />
                </v-col>
              </template>
            </v-row>
          </v-form>
        </v-card-text>

        <v-card-actions class="pa-5 pt-0">
          <v-btn v-if="canCancel" color="error" outlined :loading="cancelling" @click="cancelDialog = true">
            <v-icon left>mdi-close-circle-outline</v-icon>Batalkan
          </v-btn>
          <v-spacer />
          <v-btn text @click="dialog = false">Batal</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">
            <v-icon left>mdi-send-outline</v-icon>Simpan & Kirim
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="rejectDialog" max-width="500" persistent>
      <v-card>
        <v-card-title>Tolak pembayaran?</v-card-title>
        <v-card-text>
          <v-textarea
            v-model.trim="rejectionReason"
            outlined
            label="Alasan penolakan"
            placeholder="Contoh: nominal tidak sesuai atau bukti tidak terbaca"
            :rules="[v => !!v || 'Alasan wajib diisi']"
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn text @click="rejectDialog = false">Batal</v-btn>
          <v-btn color="error" :loading="reviewing" @click="reviewPayment('reject')">Tolak Pembayaran</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="cancelDialog" max-width="500" persistent>
      <v-card>
        <v-card-title class="text-h6">Batalkan Booking?</v-card-title>
        <v-card-text>
          <p class="mb-3">Booking <strong>{{ selected ? selected.reference_code : '' }}</strong> akan dibatalkan.</p>
          <v-textarea v-model.trim="cancelReason" outlined rows="2" label="Alasan pembatalan (opsional)" placeholder="Contoh: permintaan customer, armada tidak tersedia" />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn text @click="cancelDialog = false">Kembali</v-btn>
          <v-btn color="error" :loading="cancelling" @click="cancelBooking">Ya, Batalkan</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
export default {
  data: () => ({
    bookings: [],
    buses: [],
    drivers: [],
    loading: false,
    loadingOptions: false,
    saving: false,
    reviewing: false,
    openingProof: false,
    search: '',
    statusFilter: null,
    paymentStatusFilter: null,
    dialog: false,
    rejectDialog: false,
    rejectionReason: '',
    cancelDialog: false,
    cancelReason: '',
    cancelling: false,
    selected: null,
    valid: true,
    edit: {
      status: '',
      quoted_price: null,
      admin_notes: '',
      payment_bank_name: '',
      payment_account_number: '',
      payment_account_holder: '',
      bus_id: null,
      driver_id: null,
      assignments: [],
      departure_time: '',
      return_date: '',
      return_time: '',
    },
    headers: [
      { text: 'No. Booking', value: 'reference_code' },
      { text: 'Customer', value: 'customer' },
      { text: 'Rute', value: 'route', width: '240px' },
      { text: 'Berangkat', value: 'departure_date' },
      { text: 'Armada', value: 'bus_type' },
      { text: 'Penawaran', value: 'quoted_price' },
      { text: 'Pembayaran', value: 'payment_status' },
      { text: 'Status', value: 'status' },
      { text: '', value: 'actions', sortable: false },
    ],
    statusOptions: [
      { text: 'Menunggu penawaran', value: 'waiting_quote' },
      { text: 'Penawaran dikirim', value: 'quote_sent' },
      { text: 'Disetujui', value: 'approved' },
      { text: 'Ditolak', value: 'rejected' },
      { text: 'Dibatalkan', value: 'cancelled' },
      { text: 'Selesai', value: 'completed' },
    ],
    paymentStatusOptions: [
      { text: 'Belum dibayar', value: 'unpaid' },
      { text: 'Menunggu verifikasi', value: 'pending_verification' },
      { text: 'Lunas', value: 'paid' },
      { text: 'Ditolak', value: 'rejected' },
    ],
  }),
  computed: {
    filteredBookings() {
      let result = this.bookings;
      if (this.statusFilter) result = result.filter(item => item.status === this.statusFilter);
      if (this.paymentStatusFilter) result = result.filter(item => item.payment_status === this.paymentStatusFilter);
      return result;
    },
    waitingCount() {
      return this.bookings.filter(item => item.status === 'waiting_quote').length;
    },
    requiresPaymentDetails() {
      return ['quote_sent', 'approved'].includes(this.edit.status);
    },
    bankRules() {
      return this.requiresPaymentDetails ? [v => !!v || 'Detail rekening wajib diisi'] : [];
    },
    canAssignOperational() {
      return this.selected && this.edit.status === 'approved' && this.selected.payment_status === 'paid';
    },
    canCancel() {
      return this.selected && !['cancelled', 'completed'].includes(this.selected.status);
    },
    assignmentLockedMessage() {
      if (!this.selected) return '';
      if (this.selected.payment_status !== 'paid') return 'Tunggu customer melakukan pembayaran dan admin memverifikasi bukti transfer terlebih dahulu.';
      if (this.edit.status !== 'approved') return 'Ubah status booking menjadi Disetujui untuk membuka pilihan bus dan driver.';
      return 'Lengkapi persetujuan booking terlebih dahulu.';
    },
    busOptions() {
      return this.buses.map(bus => ({
        value: bus.id,
        text: `${bus.fleet_number ? `#${bus.fleet_number} - ` : ''}${bus.license} - ${bus.bus_type ? bus.bus_type.name : `${bus.capacity} kursi`} - ${bus.depot ? bus.depot.name : 'Tanpa depo'}`,
      }));
    },
    driverOptions() {
      return this.drivers.map(driver => ({ value: driver.id, text: driver.name }));
    },
  },
  created() {
    this.loadBookings();
    this.loadAssignmentOptions();
  },
  methods: {
    shortLocation(address) {
      const value = String(address || '').trim();
      if (!value) return '-';
      // Keep coordinate-only locations intact; otherwise show the place name.
      if (/^-?\d+(\.\d+)?\s*,\s*-?\d+(\.\d+)?$/.test(value)) return value;
      return value.split(',').find(part => part.trim())?.trim() || value;
    },
    async loadBookings() {
      this.loading = true;
      try {
        const response = await axios.get('/charter-bookings/admin');
        this.bookings = response.data.bookings || [];
      } catch (error) {
        this.notifyError(error, 'Booking tidak dapat dimuat.');
      } finally {
        this.loading = false;
      }
    },
    async loadAssignmentOptions() {
      this.loadingOptions = true;
      try {
        const response = await axios.get('/charter-bookings/admin-assignment-options');
        this.buses = response.data.buses || [];
        this.drivers = response.data.drivers || [];
      } catch (error) {
        this.notifyError(error, 'Pilihan armada dan driver tidak dapat dimuat.');
      } finally {
        this.loadingOptions = false;
      }
    },
    openBooking(item) {
      this.selected = item;
      const existingAssignments = item.assignments && item.assignments.length
        ? item.assignments.map(assignment => ({ bus_id: assignment.bus_id, driver_id: assignment.driver_id }))
        : [{ bus_id: item.bus_id || null, driver_id: item.driver_id || null }];
      const requiredCount = Number(item.requested_bus_count || 1);
      while (existingAssignments.length < requiredCount) existingAssignments.push({ bus_id: null, driver_id: null });
      this.edit = {
        status: item.status,
        quoted_price: item.quoted_price ? Number(item.quoted_price) : null,
        admin_notes: item.admin_notes || '',
        payment_bank_name: item.payment_bank_name || '',
        payment_account_number: item.payment_account_number || '',
        payment_account_holder: item.payment_account_holder || '',
        bus_id: item.bus_id || null,
        driver_id: item.driver_id || null,
        assignments: existingAssignments.slice(0, requiredCount),
        departure_time: item.departure_time ? item.departure_time.slice(0, 5) : '',
        return_date: item.return_date || '',
        return_time: item.return_time ? item.return_time.slice(0, 5) : '',
      };
      this.rejectionReason = '';
      this.cancelReason = '';
      this.cancelDialog = false;
      this.dialog = true;
    },
    buildPayload() {
      const payload = { ...this.edit };
      if (!this.canAssignOperational) {
        delete payload.bus_id;
        delete payload.driver_id;
        delete payload.assignments;
        delete payload.departure_time;
        delete payload.return_date;
        delete payload.return_time;
      } else {
        payload.assignments = (payload.assignments || []).map(assignment => ({
          bus_id: assignment.bus_id,
          driver_id: assignment.driver_id,
        }));
      }
      return payload;
    },
    async save() {
      if (!this.$refs.form.validate()) return;
      this.saving = true;
      try {
        const response = await axios.put(`/charter-bookings/admin/${this.selected.id}`, this.buildPayload());
        this.replaceBooking(response.data.booking);
        this.selected = response.data.booking;
        this.$notify({ type: 'success', title: 'Berhasil', text: response.data.message });
        this.dialog = false;
      } catch (error) {
        this.notifyError(error, 'Booking gagal diperbarui.');
      } finally {
        this.saving = false;
      }
    },
    async reviewPayment(action) {
      if (action === 'reject' && !this.rejectionReason) return this.$notify({ type: 'warning', title: 'Alasan wajib diisi' });
      this.reviewing = true;
      try {
        const response = await axios.post(`/charter-bookings/admin/${this.selected.id}/payment-review`, {
          action,
          reason: action === 'reject' ? this.rejectionReason : null,
        });
        this.replaceBooking(response.data.booking);
        this.selected = response.data.booking;
        this.edit.status = response.data.booking.status;
        this.rejectDialog = false;
        this.$notify({ type: 'success', title: 'Berhasil', text: response.data.message });
      } catch (error) {
        this.notifyError(error, 'Pembayaran gagal diproses.');
      } finally {
        this.reviewing = false;
      }
    },
    async cancelBooking() {
      this.cancelling = true;
      try {
        const response = await axios.post(`/charter-bookings/admin/${this.selected.id}/cancel`, {
          reason: this.cancelReason || null,
        });
        this.replaceBooking(response.data.booking);
        this.selected = response.data.booking;
        this.edit.status = response.data.booking.status;
        this.cancelDialog = false;
        this.cancelReason = '';
        this.$notify({ type: 'success', title: 'Berhasil', text: response.data.message });
      } catch (error) {
        this.notifyError(error, 'Booking gagal dibatalkan.');
      } finally {
        this.cancelling = false;
      }
    },
    isPaymentOverdue(booking) {
      if (!booking || !booking.payment_deadline) return false;
      return new Date(booking.payment_deadline) < new Date();
    },
    async viewProof() {
      this.openingProof = true;
      try {
        const response = await axios.get(`/charter-bookings/admin/${this.selected.id}/payment-proof`, { responseType: 'blob' });
        const type = response.headers['content-type'] || 'application/octet-stream';
        const url = URL.createObjectURL(new Blob([response.data], { type }));
        window.open(url, '_blank', 'noopener');
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      } catch (error) {
        this.notifyError(error, 'Bukti pembayaran tidak dapat dibuka.');
      } finally {
        this.openingProof = false;
      }
    },
    replaceBooking(booking) {
      const index = this.bookings.findIndex(item => item.id === booking.id);
      if (index !== -1) this.$set(this.bookings, index, booking);
    },
    notifyError(error, fallback) {
      const data = error.response && error.response.data;
      const errors = data && data.errors;
      this.$notify({
        type: 'error',
        title: 'Gagal',
        text: errors ? Object.values(errors).reduce((a, m) => a.concat(m), []).join(' ') : (data && data.message) || fallback,
      });
    },
    formatDate(date) {
      return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(`${date}T00:00:00`));
    },
    formatDateTime(date) {
      return date ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date)) : '-';
    },
    currency(value) {
      return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
    },
    areaName(area) {
      return area && area.name ? area.name : '-';
    },
    busName(item) {
      if (item && item.bus_type && item.bus_type.name) return item.bus_type.name;
      if (item && item.busType && item.busType.name) return item.busType.name;
      const type = typeof item === 'string' ? item : item && item.bus_type;
      return ({
        medium: 'Medium Bus',
        large: 'Large Bus',
        luxury: 'Luxury Bus',
        medium_25: 'Medium Bus 25 Seat',
        medium_26: 'Medium Bus 26 Seat',
        big_45: 'Big Bus 45 Seat',
        big_50: 'Big Bus 50 Seat',
      })[type] || type || '-';
    },
    paymentMethod(method) {
      return ({ mobile_banking: 'Mobile Banking', atm: 'Transfer ATM', bank_transfer: 'Transfer Bank' })[method] || method;
    },
    statusInfo(status) {
      return ({
        waiting_quote: { label: 'Menunggu', color: 'orange' },
        quote_sent: { label: 'Penawaran dikirim', color: 'primary' },
        approved: { label: 'Disetujui', color: 'success' },
        rejected: { label: 'Ditolak', color: 'error' },
        cancelled: { label: 'Dibatalkan', color: 'grey' },
        completed: { label: 'Selesai', color: 'teal' },
      })[status] || { label: status, color: 'grey' };
    },
    paymentInfo(status) {
      return ({
        unpaid: { label: 'Belum dibayar', color: 'grey' },
        pending_verification: { label: 'Perlu verifikasi', color: 'orange' },
        paid: { label: 'Lunas', color: 'success' },
        rejected: { label: 'Ditolak', color: 'error' },
      })[status] || { label: status, color: 'grey' };
    },
  },
};
</script>

<style scoped>
.route-summary { width: 208px; max-width: 100%; padding: 8px 0; }
.route-location { display: block; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.booking-card {
  border-radius: 14px;
}

.search {
  max-width: 440px;
}

.status-filter {
  max-width: 230px;
}

.label {
  font-size: .75rem;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: #8a8494;
  margin-bottom: 5px;
}

.assignment-waiting {
  border-radius: 12px;
}

.assignment-row {
  border-radius: 12px;
}

@media(max-width: 600px) {
  .search,
  .status-filter {
    max-width: none;
    width: 100%;
    margin: 0 0 12px !important;
  }
}
</style>
