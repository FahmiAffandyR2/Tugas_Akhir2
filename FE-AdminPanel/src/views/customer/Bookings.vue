<template>
  <div>
    <div class="d-flex flex-column flex-sm-row justify-space-between align-sm-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-2">Pemesanan Saya</h1>
        <p class="grey--text mb-0">Pantau permintaan, penawaran, pembayaran, dan armada perjalanan Anda.</p>
      </div>
      <v-btn color="primary" large class="mt-4 mt-sm-0" to="/customer/pesan">
        <v-icon left>mdi-plus</v-icon>Pesan Bus
      </v-btn>
    </div>

    <v-skeleton-loader v-if="loading" type="list-item-three-line@3" />
    <v-alert v-else-if="error" type="error" text>
      {{ error }} <v-btn text small @click="loadBookings">Coba lagi</v-btn>
    </v-alert>

    <div v-else-if="bookings.length" class="booking-grid">
      <v-card v-for="booking in bookings" :key="booking.id" flat class="booking-card pa-5 pa-md-6">
        <div class="d-flex align-start">
          <div class="booking-icon mr-4">
            <v-icon color="primary">mdi-bus</v-icon>
          </div>
          <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-center mb-2">
              <h2 class="text-h6 font-weight-bold mb-0 mr-3">{{ booking.origin }} - {{ booking.destination }}</h2>
              <v-chip small :color="statusInfo(booking.status).color" dark class="mr-2">{{ statusInfo(booking.status).label }}</v-chip>
              <v-chip v-if="booking.quoted_price" small :color="paymentInfo(booking.payment_status).color" outlined>
                {{ paymentInfo(booking.payment_status).label }}
              </v-chip>
            </div>

            <div class="booking-meta">
              <span><v-icon x-small>mdi-calendar</v-icon> {{ formatDate(booking.departure_date) }}</span>
              <span>{{ busName(booking) }}</span>
              <span>{{ booking.passenger_count }} peserta</span>
            </div>
            <div class="caption grey--text mt-1">No. {{ booking.reference_code }}</div>

            <div class="progress-track mt-5">
              <div
                v-for="step in bookingSteps(booking)"
                :key="step.key"
                :class="['progress-step', step.state]"
              >
                <div class="step-dot"><v-icon x-small>{{ step.icon }}</v-icon></div>
                <div>
                  <div class="step-title">{{ step.title }}</div>
                  <small>{{ step.text }}</small>
                </div>
              </div>
            </div>

            <v-alert v-if="booking.status === 'waiting_quote'" type="info" text dense class="mt-4 mb-0 waiting-alert">
              <strong>Permintaan sedang diperiksa admin.</strong>
              <div class="caption">Estimasi harga sudah dihitung otomatis. Tim admin akan mengecek armada dan melengkapi instruksi pembayaran.</div>
            </v-alert>

            <div v-if="booking.quoted_price" class="quote mt-4 pa-4">
              <span>Estimasi Harga</span>
              <strong>{{ currency(booking.quoted_price) }}</strong>
              <small v-if="booking.distance_km">{{ booking.distance_km }} km · {{ booking.requested_bus_count || 1 }} unit</small>
              <small v-if="booking.admin_notes">{{ booking.admin_notes }}</small>
              <div v-if="booking.payment_deadline && booking.payment_status !== 'paid' && ['quote_sent', 'approved'].includes(booking.status)" class="payment-deadline mt-2">
                <v-icon x-small>mdi-clock-outline</v-icon>
                Bayar sebelum {{ formatDateTime(booking.payment_deadline) }}
                <span v-if="isPaymentOverdue(booking)" class="overdue-text"> — Sudah lewat</span>
              </div>
            </div>

            <v-card v-if="showAssignment(booking)" outlined class="assignment mt-4 pa-4">
              <div class="font-weight-bold mb-2">
                <v-icon small color="primary" class="mr-1">mdi-bus-check</v-icon>Armada telah ditetapkan
              </div>
              <template v-if="booking.assignments && booking.assignments.length">
                <div v-for="(assignment, index) in booking.assignments" :key="assignment.id || index" class="caption mt-1">
                  Unit {{ index + 1 }}:
                  <strong>{{ assignment.bus ? assignment.bus.license : '-' }}</strong>
                  - Driver: <strong>{{ assignment.driver ? assignment.driver.name : '-' }}</strong>
                  <span v-if="assignment.bus && assignment.bus.depot"> · Depo {{ assignment.bus.depot.name }}</span>
                </div>
              </template>
              <template v-else>
                <div class="caption">Bus: <strong>{{ booking.bus.license }}</strong> - Driver: <strong>{{ booking.driver.name }}</strong></div>
                <div v-if="booking.bus.depot" class="caption mt-1">Depo asal: {{ booking.bus.depot.name }}</div>
              </template>
              <div v-if="booking.departure_time" class="caption mt-1">
                Berangkat {{ booking.departure_time.slice(0, 5) }}
                <span v-if="booking.return_date && booking.return_time"> - Kembali {{ formatDate(booking.return_date) }} {{ booking.return_time.slice(0, 5) }}</span>
              </div>
            </v-card>

            <v-alert v-if="booking.payment_status === 'rejected'" type="error" text dense class="mt-4 mb-0">
              Pembayaran ditolak: {{ booking.payment_rejection_reason }}
            </v-alert>

            <div v-if="canPay(booking)" class="payment-actions mt-4">
              <v-btn color="success" @click="openPayment(booking)">
                <v-icon left>mdi-credit-card-outline</v-icon>{{ booking.payment_status === 'rejected' ? 'Kirim Ulang Pembayaran' : 'Bayar Sekarang' }}
              </v-btn>
              <v-btn v-if="canCancelRejectedPayment(booking)" color="error" outlined :loading="cancelingId === booking.id" @click="cancelRejectedPayment(booking)">
                <v-icon left>mdi-close-circle-outline</v-icon>Batalkan Pesanan
              </v-btn>
            </div>

            <div v-if="canCancelBeforePayment(booking)" class="payment-actions mt-4">
              <v-btn color="error" outlined :loading="cancelingId === booking.id" @click="cancelBeforePayment(booking)">
                <v-icon left>mdi-close-circle-outline</v-icon>Batalkan Pesanan
              </v-btn>
            </div>

            <div v-if="canViewInvoice(booking)" class="payment-actions mt-4">
              <v-btn color="primary" outlined :loading="openingInvoiceId === booking.id" @click="viewInvoice(booking)">
                <v-icon left>mdi-file-document-check-outline</v-icon>Lihat Invoice
              </v-btn>
            </div>

            <v-alert v-else-if="booking.quoted_price && !booking.payment_bank_name && booking.payment_status !== 'paid'" type="info" text dense class="mt-4 mb-0">
              Menunggu Admin melengkapi rekening pembayaran.
            </v-alert>
          </div>
        </div>
      </v-card>
    </div>

    <v-card v-else flat class="empty pa-10 text-center">
      <div class="empty-icon mx-auto mb-5"><v-icon size="58" color="primary">mdi-clipboard-text-outline</v-icon></div>
      <h2 class="text-h6 font-weight-bold">Belum ada pemesanan</h2>
      <p class="grey--text">Mulai rencanakan perjalanan pertama Anda bersama EZBus.</p>
      <v-btn color="primary" to="/customer/pesan">Pesan Bus Sekarang</v-btn>
    </v-card>

    <v-dialog v-model="paymentDialog" max-width="650" persistent>
      <v-card v-if="paymentBooking">
        <v-card-title class="pa-5">
          <div>
            <div class="text-h6 font-weight-bold">Pembayaran {{ paymentBooking.reference_code }}</div>
            <div class="caption grey--text">Transfer sesuai nominal penawaran</div>
          </div>
          <v-spacer />
          <v-btn icon @click="paymentDialog = false"><v-icon>mdi-close</v-icon></v-btn>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
          <div class="payment-amount pa-4 text-center mb-5">
            <div class="caption">TOTAL PEMBAYARAN</div>
            <div class="text-h4 font-weight-bold">{{ currency(paymentBooking.quoted_price) }}</div>
          </div>
          <v-card outlined class="pa-4 mb-5">
            <div class="label">Transfer ke rekening</div>
            <div class="text-h6 font-weight-bold">{{ paymentBooking.payment_bank_name }}</div>
            <div class="text-h5 primary--text font-weight-bold my-1">{{ paymentBooking.payment_account_number }}</div>
            <div>a.n. {{ paymentBooking.payment_account_holder }}</div>
          </v-card>
          <v-alert type="warning" text dense>Pastikan nominal dan rekening sudah benar. Status lunas diberikan setelah bukti diverifikasi Admin.</v-alert>
          <v-form ref="paymentForm" v-model="paymentValid">
            <v-select v-model="paymentForm.method" :items="paymentMethods" outlined label="Metode transfer" :rules="required" />
            <v-text-field v-model.trim="paymentForm.reference" outlined label="Nomor referensi transfer" hint="Nomor transaksi dari bank atau ATM" persistent-hint :rules="required" />
            <v-file-input v-model="paymentForm.proof" outlined accept="image/jpeg,image/png,image/webp,application/pdf" label="Bukti pembayaran" prepend-icon="mdi-paperclip" hint="JPG, PNG, WEBP, atau PDF, maksimal 5 MB" persistent-hint :rules="proofRules" class="mt-3" />
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-5 pt-0">
          <v-spacer />
          <v-btn text @click="paymentDialog = false">Batal</v-btn>
          <v-btn color="success" :loading="paying" @click="submitPayment">
            <v-icon left>mdi-send-check-outline</v-icon>Kirim Bukti
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script>
export default {
  data: () => ({
    bookings: [],
    loading: false,
    error: null,
    paymentDialog: false,
    paymentBooking: null,
    paymentValid: true,
    paying: false,
    cancelingId: null,
    openingInvoiceId: null,
    paymentForm: { method: null, reference: '', proof: null },
    paymentMethods: [
      { text: 'Mobile Banking', value: 'mobile_banking' },
      { text: 'Transfer ATM', value: 'atm' },
      { text: 'Transfer Bank', value: 'bank_transfer' },
    ],
    required: [v => !!v || 'Wajib diisi'],
    proofRules: [
      v => !!v || 'Bukti pembayaran wajib diunggah',
      v => !v || v.size <= 5 * 1024 * 1024 || 'Ukuran file maksimal 5 MB',
    ],
  }),
  async created() {
    await this.migrateLegacyBookings();
    await this.loadBookings();
  },
  methods: {
    async migrateLegacyBookings() {
      if (localStorage.getItem('customerBookingsMigrated') === '1') return;
      let legacy = [];
      try {
        legacy = JSON.parse(localStorage.getItem('customerBookings') || '[]');
      } catch (_) {
        legacy = [];
      }
      if (!legacy.length) {
        localStorage.setItem('customerBookingsMigrated', '1');
        return;
      }
      let migrated = true;
      for (const item of legacy) {
        try {
          await axios.post('/charter-bookings', {
            origin: item.origin,
            destination: item.destination,
            departure_date: item.departureDate,
            passenger_count: item.passengers,
            bus_type: item.busType,
            notes: item.notes || null,
          });
        } catch (_) {
          migrated = false;
        }
      }
      if (migrated) {
        localStorage.setItem('customerBookingsMigrated', '1');
        localStorage.removeItem('customerBookings');
      }
    },
    async loadBookings() {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get('/charter-bookings/mine');
        this.bookings = response.data.bookings || [];
      } catch (error) {
        this.error = (error.response && error.response.data && error.response.data.message) || 'Pemesanan tidak dapat dimuat.';
      } finally {
        this.loading = false;
      }
    },
    showAssignment(item) {
      return item.status === 'approved' && item.payment_status === 'paid' && ((item.assignments && item.assignments.length) || (item.bus && item.driver));
    },
    canPay(item) {
      return Boolean(item.quoted_price && item.payment_bank_name && ['quote_sent', 'approved'].includes(item.status) && ['unpaid', 'rejected'].includes(item.payment_status));
    },
    canViewInvoice(item) {
      return item.payment_status === 'paid';
    },
    canCancelRejectedPayment(item) {
      return item.payment_status === 'rejected' && !['cancelled', 'completed'].includes(item.status);
    },
    canCancelBeforePayment(item) {
      return ['waiting_quote', 'quote_sent'].includes(item.status) && item.payment_status !== 'paid';
    },
    openPayment(item) {
      this.paymentBooking = item;
      this.paymentForm = { method: null, reference: '', proof: null };
      this.paymentDialog = true;
      this.$nextTick(() => this.$refs.paymentForm && this.$refs.paymentForm.resetValidation());
    },
    async submitPayment() {
      if (!this.$refs.paymentForm.validate()) return;
      this.paying = true;
      const data = new FormData();
      data.append('payment_method', this.paymentForm.method);
      data.append('payment_reference', this.paymentForm.reference);
      data.append('payment_proof', this.paymentForm.proof);
      try {
        const response = await axios.post(`/charter-bookings/${this.paymentBooking.id}/payment`, data, { headers: { 'Content-Type': 'multipart/form-data' } });
        const index = this.bookings.findIndex(item => item.id === this.paymentBooking.id);
        if (index !== -1) this.$set(this.bookings, index, response.data.booking);
        this.paymentDialog = false;
        this.$notify({ type: 'success', title: 'Pembayaran terkirim', text: response.data.message });
      } catch (error) {
        const dataError = error.response && error.response.data;
        const errors = dataError && dataError.errors;
        this.$notify({
          type: 'error',
          title: 'Gagal',
          text: errors ? Object.values(errors).reduce((a, m) => a.concat(m), []).join(' ') : (dataError && dataError.message) || 'Bukti pembayaran gagal dikirim.',
        });
      } finally {
        this.paying = false;
      }
    },
    async viewInvoice(item) {
      this.openingInvoiceId = item.id;
      try {
        const response = await axios.get(`/charter-bookings/${item.id}/invoice`, { responseType: 'blob' });
        const url = URL.createObjectURL(new Blob([response.data], { type: 'text/html' }));
        window.open(url, '_blank', 'noopener');
        setTimeout(() => URL.revokeObjectURL(url), 60000);
      } catch (error) {
        const dataError = error.response && error.response.data;
        this.$notify({ type: 'error', title: 'Gagal', text: (dataError && dataError.message) || 'Invoice tidak dapat dibuka.' });
      } finally {
        this.openingInvoiceId = null;
      }
    },
    async cancelRejectedPayment(item) {
      const result = await this.$swal.fire({
        title: 'Batalkan pesanan?',
        text: `Pesanan ${item.reference_code} akan dibatalkan dan tidak diproses lebih lanjut.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, batalkan',
        cancelButtonText: 'Kembali',
      });
      if (!result.isConfirmed) return;
      this.cancelingId = item.id;
      try {
        const response = await axios.post(`/charter-bookings/${item.id}/cancel-rejected-payment`);
        const index = this.bookings.findIndex(booking => booking.id === item.id);
        if (index !== -1) this.$set(this.bookings, index, response.data.booking);
        this.$notify({ type: 'success', title: 'Pesanan dibatalkan', text: response.data.message });
      } catch (error) {
        const dataError = error.response && error.response.data;
        this.$notify({ type: 'error', title: 'Gagal', text: (dataError && dataError.message) || 'Pesanan gagal dibatalkan.' });
      } finally {
        this.cancelingId = null;
      }
    },
    async cancelBeforePayment(item) {
      const result = await this.$swal.fire({
        title: 'Batalkan pesanan?',
        text: `Pesanan ${item.reference_code} akan dibatalkan. Anda bisa membuat booking baru kapan saja.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, batalkan',
        cancelButtonText: 'Kembali',
      });
      if (!result.isConfirmed) return;
      this.cancelingId = item.id;
      try {
        const response = await axios.post(`/charter-bookings/${item.id}/cancel`);
        const index = this.bookings.findIndex(booking => booking.id === item.id);
        if (index !== -1) this.$set(this.bookings, index, response.data.booking);
        this.$notify({ type: 'success', title: 'Pesanan dibatalkan', text: response.data.message });
      } catch (error) {
        const dataError = error.response && error.response.data;
        this.$notify({ type: 'error', title: 'Gagal', text: (dataError && dataError.message) || 'Pesanan gagal dibatalkan.' });
      } finally {
        this.cancelingId = null;
      }
    },
    bookingSteps(booking) {
      const paid = booking.payment_status === 'paid';
      return [
        { key: 'request', title: 'Permintaan diterima', text: 'Rencana perjalanan sudah masuk.', icon: 'mdi-check', state: 'done' },
        {
          key: 'quote',
          title: 'Estimasi harga',
          text: booking.quoted_price ? this.currency(booking.quoted_price) : 'Belum tersedia.',
          icon: booking.quoted_price ? 'mdi-check' : 'mdi-dots-horizontal',
          state: booking.quoted_price ? 'done' : 'active',
        },
        {
          key: 'payment',
          title: 'Pembayaran',
          text: paid ? 'Lunas dan terverifikasi.' : 'Menunggu pembayaran.',
          icon: paid ? 'mdi-check' : 'mdi-credit-card-outline',
          state: paid ? 'done' : booking.quoted_price ? 'active' : 'todo',
        },
        {
          key: 'assignment',
          title: 'Armada & driver',
          text: this.showAssignment(booking) ? 'Sudah ditetapkan.' : 'Dibuka setelah lunas.',
          icon: this.showAssignment(booking) ? 'mdi-check' : 'mdi-bus-clock',
          state: this.showAssignment(booking) ? 'done' : paid ? 'active' : 'todo',
        },
      ];
    },
    formatDate(date) {
      return new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${date}T00:00:00`));
    },
    formatDateTime(date) {
      return date ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date)) : '-';
    },
    isPaymentOverdue(booking) {
      if (!booking || !booking.payment_deadline) return false;
      return new Date(booking.payment_deadline) < new Date();
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
    currency(value) {
      return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
    },
    statusInfo(status) {
      return ({
        waiting_quote: { label: 'Menunggu penawaran', color: 'orange' },
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
        pending_verification: { label: 'Menunggu verifikasi', color: 'orange' },
        paid: { label: 'Lunas', color: 'success' },
        rejected: { label: 'Pembayaran ditolak', color: 'error' },
      })[status] || { label: status, color: 'grey' };
    },
  },
};
</script>

<style scoped>
.booking-grid {
  display: grid;
  gap: 18px;
}

.booking-card,
.empty {
  border-radius: 18px !important;
  border: 1px solid #ececf3;
  background: #fff;
}

.booking-icon {
  width: 58px;
  height: 58px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f3edff;
  flex: 0 0 auto;
}

.booking-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  color: #746f82;
  font-size: .92rem;
}

.progress-track {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.progress-step {
  min-height: 76px;
  border-radius: 12px;
  padding: 10px;
  display: flex;
  gap: 9px;
  background: #f7f7fa;
  color: #8a8494;
}

.progress-step.done {
  background: #edf8f0;
  color: #276b3f;
}

.progress-step.active {
  background: #f2eaff;
  color: #6f36d8;
}

.step-dot {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: currentColor;
  color: #fff;
  flex: 0 0 auto;
}

.step-title {
  font-size: .82rem;
  font-weight: 700;
  line-height: 1.2;
}

.progress-step small {
  display: block;
  margin-top: 3px;
  line-height: 1.25;
}

.waiting-alert {
  border-radius: 12px;
}

.empty-icon {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  background: #f3edff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.quote {
  display: flex;
  flex-direction: column;
  max-width: 360px;
  border-radius: 12px;
  background: #f4efff;
  color: #6f36d8;
}

.quote strong {
  font-size: 1.15rem;
}

.quote small {
  color: #716b7c;
  margin-top: 4px;
}

.payment-deadline {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: .85rem;
  color: #e65100;
  font-weight: 500;
}

.overdue-text {
  color: #d32f2f;
  font-weight: 700;
}

.assignment {
  border-radius: 12px;
}

.payment-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.payment-amount {
  border-radius: 14px;
  background: #f2eaff;
  color: #6f36d8;
}

.label {
  font-size: .75rem;
  color: #8a8494;
  text-transform: uppercase;
  letter-spacing: .04em;
}

@media(max-width: 760px) {
  .progress-track {
    grid-template-columns: 1fr;
  }
}
</style>
