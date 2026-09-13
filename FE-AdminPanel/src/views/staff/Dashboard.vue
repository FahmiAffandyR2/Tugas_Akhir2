<template>
  <div>
    <div class="d-flex align-center mb-4">
      <div><h1 class="text-h5">Dashboard Depot</h1><p class="mb-0">Ringkasan operasional dan pemesanan depot Anda.</p></div>
      <v-spacer />
      <v-btn outlined :loading="loading" @click="load">Muat ulang</v-btn>
    </div>
    <v-alert v-if="error" type="error">{{ error }}</v-alert>
    <v-row>
      <v-col v-for="card in cards" :key="card.key" cols="12" sm="6" md="4">
        <v-card outlined><v-card-text>{{ card.title }}<div class="text-h4 mt-2">{{ loading ? '…' : (dashboard[card.key] || 0) }}</div></v-card-text></v-card>
      </v-col>
    </v-row>
    <v-card outlined class="mt-6">
      <v-card-title>Booking Bus</v-card-title>
      <v-data-table :headers="headers" :items="bookings" :loading="loading" no-data-text="Belum ada booking untuk depot Anda.">
        <template #item.payment_status="{ item }">{{ item.payment_status === 'paid' ? 'Lunas' : 'Belum lunas' }}</template>
        <template #item.invoice="{ item }">
          <v-btn small text color="primary" :disabled="item.payment_status !== 'paid'" :loading="invoiceId === item.id" @click="downloadInvoice(item)">Unduh invoice</v-btn>
        </template>
      </v-data-table>
    </v-card>
  </div>
</template>

<script>
import axios from 'axios'
import AuthService from '@/services/AuthService'

export default {
  data: () => ({
    loading: false,
    error: '',
    dashboard: {},
    bookings: [],
    invoiceId: null,
    cards: [
      { key: 'total_bookings', title: 'Total booking' },
      { key: 'pending_bookings', title: 'Menunggu penawaran' },
      { key: 'active_bookings', title: 'Booking disetujui' },
      { key: 'total_buses', title: 'Bus aktif' },
      { key: 'available_buses', title: 'Bus tersedia' },
      { key: 'total_drivers', title: 'Pengemudi aktif' },
    ],
    headers: [
      { text: 'Referensi', value: 'reference_code' },
      { text: 'Pelanggan', value: 'customer.name' },
      { text: 'Status', value: 'status' },
      { text: 'Pembayaran', value: 'payment_status' },
      { text: 'Invoice', value: 'invoice', sortable: false },
    ],
  }),
  created() { this.load() },
  methods: {
    async load() {
      this.loading = true
      this.error = ''
      this.dashboard = {}
      this.bookings = []
      try {
        const [summary, bookings] = await Promise.all([axios.get('/staff/dashboard'), axios.get('/staff/bookings')])
        this.dashboard = summary.data.dashboard || {}
        this.bookings = bookings.data.bookings || []
      } catch (error) {
        this.error = 'Data depot belum bisa dimuat. Silakan coba lagi.'
        AuthService.checkError(error, this.$router, this.$swal)
      } finally {
        this.loading = false
      }
    },
    async downloadInvoice(booking) {
      this.invoiceId = booking.id
      this.error = ''
      try {
        const response = await axios.get(`/staff/bookings/${booking.id}/invoice`, { responseType: 'blob' })
        const url = URL.createObjectURL(response.data)
        const link = document.createElement('a')
        link.href = url
        link.download = `invoice-${booking.reference_code}.html`
        document.body.appendChild(link)
        link.click()
        link.remove()
        setTimeout(() => URL.revokeObjectURL(url), 1000)
      } catch (error) {
        this.error = 'Invoice belum bisa diunduh. Pastikan booking sudah lunas.'
        AuthService.checkError(error, this.$router, this.$swal)
      } finally {
        this.invoiceId = null
      }
    },
  },
}
</script>
