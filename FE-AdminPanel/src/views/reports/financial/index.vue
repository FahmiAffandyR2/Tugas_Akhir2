<template>
  <div>
    <v-card class="rounded-xl mb-4">
      <v-card-title>
        <v-icon color="primary" class="mr-2">mdi-cash-multiple</v-icon>
        Laporan Keuangan
        <v-spacer />
        <v-btn icon :loading="loading" @click="loadData"><v-icon>mdi-refresh</v-icon></v-btn>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="3">
            <v-text-field v-model="filters.from_date" outlined dense hide-details type="date" label="Dari" />
          </v-col>
          <v-col cols="12" sm="3">
            <v-text-field v-model="filters.to_date" outlined dense hide-details type="date" label="Sampai" />
          </v-col>
          <v-col cols="12" sm="2">
            <v-select v-model="filters.period" :items="periodOptions" outlined dense hide-details label="Periode" />
          </v-col>
          <v-col cols="12" sm="2">
            <v-btn color="primary" block @click="loadData">Tampilkan</v-btn>
          </v-col>
          <v-col cols="12" sm="2">
            <v-btn outlined block @click="exportCSV">Export CSV</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-row class="mb-4">
      <v-col cols="12" sm="4">
        <v-card class="rounded-xl">
          <v-card-text class="text-center">
            <v-icon size="48" color="success">mdi-cash-check</v-icon>
            <div class="text-h4 font-weight-bold mt-2">Rp {{ formatNumber(summary.total_revenue) }}</div>
            <div class="subtitle-1 grey--text">Total Pendapatan</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4">
        <v-card class="rounded-xl">
          <v-card-text class="text-center">
            <v-icon size="48" color="info">mdi-receipt</v-icon>
            <div class="text-h4 font-weight-bold mt-2">{{ summary.total_bookings }}</div>
            <div class="subtitle-1 grey--text">Total Booking</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="4">
        <v-card class="rounded-xl">
          <v-card-text class="text-center">
            <v-icon size="48" color="warning">mdi-cash-refund</v-icon>
            <div class="text-h4 font-weight-bold mt-2">{{ summary.total_transactions }}</div>
            <div class="subtitle-1 grey--text">Total Transaksi</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row class="mb-4">
      <v-col cols="12" sm="3">
        <v-card class="rounded-xl">
          <v-card-text>
            <div class="d-flex align-center">
              <v-icon color="success" class="mr-2">mdi-check-circle</v-icon>
              <div>
                <div class="font-weight-bold">Lunas</div>
                <div class="caption grey--text">{{ summary.paid_count }} transaksi</div>
              </div>
            </div>
            <div class="text-h6 font-weight-bold mt-1 success--text">Rp {{ formatNumber(summary.paid_amount) }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="3">
        <v-card class="rounded-xl">
          <v-card-text>
            <div class="d-flex align-center">
              <v-icon color="error" class="mr-2">mdi-close-circle</v-icon>
              <div>
                <div class="font-weight-bold">Belum Lunas</div>
                <div class="caption grey--text">{{ summary.unpaid_count }} transaksi</div>
              </div>
            </div>
            <div class="text-h6 font-weight-bold mt-1 error--text">Rp {{ formatNumber(summary.unpaid_amount) }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="3">
        <v-card class="rounded-xl">
          <v-card-text>
            <div class="d-flex align-center">
              <v-icon color="info" class="mr-2">mdi-check-decagram</v-icon>
              <div>
                <div class="font-weight-bold">Selesai</div>
                <div class="caption grey--text">{{ summary.completed_bookings }} booking</div>
              </div>
            </div>
            <div class="text-h6 font-weight-bold mt-1 info--text">Rp {{ formatNumber(summary.total_quoted) }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="3">
        <v-card class="rounded-xl">
          <v-card-text>
            <div class="d-flex align-center">
              <v-icon color="warning" class="mr-2">mdi-cancel</v-icon>
              <div>
                <div class="font-weight-bold">Dibatalkan</div>
                <div class="caption grey--text">{{ summary.cancelled_bookings }} booking</div>
              </div>
            </div>
            <div class="text-h6 font-weight-bold mt-1 warning--text">Rata-rata Rp {{ formatNumber(summary.avg_transaction) }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12" sm="6">
        <v-card class="rounded-xl">
          <v-card-title>Pendapatan per Tipe Bus</v-card-title>
          <v-card-text>
            <v-simple-table>
              <thead><tr><th>Tipe Bus</th><th class="text-right">Jumlah</th><th class="text-right">Total</th></tr></thead>
              <tbody>
                <tr v-for="item in byBusType" :key="item.bus_type">
                  <td>{{ item.bus_type || '-' }}</td>
                  <td class="text-right">{{ item.count }}</td>
                  <td class="text-right font-weight-bold">Rp {{ formatNumber(item.total) }}</td>
                </tr>
                <tr v-if="byBusType.length === 0"><td colspan="3" class="text-center grey--text">Tidak ada data</td></tr>
              </tbody>
            </v-simple-table>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6">
        <v-card class="rounded-xl">
          <v-card-title>Tren Pendapatan</v-card-title>
          <v-card-text>
            <v-simple-table>
              <thead><tr><th>Periode</th><th class="text-right">Jumlah</th><th class="text-right">Total</th></tr></thead>
              <tbody>
                <tr v-for="item in trend" :key="item.period">
                  <td>{{ item.period }}</td>
                  <td class="text-right">{{ item.count }}</td>
                  <td class="text-right font-weight-bold">Rp {{ formatNumber(item.total) }}</td>
                </tr>
                <tr v-if="trend.length === 0"><td colspan="3" class="text-center grey--text">Tidak ada data</td></tr>
              </tbody>
            </v-simple-table>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading: false,
      filters: {
        from_date: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
        to_date: new Date().toISOString().slice(0, 10),
        period: 'daily',
      },
      periodOptions: [
        { text: 'Harian', value: 'daily' },
        { text: 'Mingguan', value: 'weekly' },
        { text: 'Bulanan', value: 'monthly' },
      ],
      summary: {
        total_revenue: 0, total_bookings: 0, total_transactions: 0,
        paid_count: 0, paid_amount: 0, unpaid_count: 0, unpaid_amount: 0,
        completed_bookings: 0, cancelled_bookings: 0, confirmed_bookings: 0,
        total_quoted: 0, avg_transaction: 0,
      },
      byBusType: [],
      trend: [],
    }
  },
  created() { this.loadData() },
  methods: {
    async loadData() {
      this.loading = true
      try {
        const r = await axios.get('/reports/financial', { params: this.filters })
        this.summary = r.data.summary || this.summary
        this.byBusType = r.data.by_bus_type || []
        this.trend = r.data.trend || []
      } catch (e) {
        this.$notify({ type: 'error', title: 'Gagal', text: 'Laporan keuangan tidak dapat dimuat.' })
      } finally { this.loading = false }
    },
    formatNumber(n) {
      return Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
    },
    async exportCSV() {
      try {
        const r = await axios.get('/reports/financial/bookings', { params: { ...this.filters, per_page: 1000 } })
        const rows = r.data.data || []
        if (!rows.length) return this.$notify({ type: 'warning', title: 'Kosong', text: 'Tidak ada data untuk di-export.' })
        const headers = ['Kode','Customer','Origin','Destination','Bus Type','Passengers','Price','Status','Payment','Paid At']
        const csvRows = [headers.join(',')]
        rows.forEach(b => {
          csvRows.push([
            b.reference_code, b.customer_name, b.origin, b.destination,
            b.bus_type, b.passenger_count, b.quoted_price, b.status,
            b.payment_status, b.paid_at || ''
          ].map(v => `"${(v||'').toString().replace(/"/g,'""')}"`).join(','))
        })
        const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a'); a.href = url; a.download = `laporan-keuangan-${this.filters.from_date}-${this.filters.to_date}.csv`
        a.click(); URL.revokeObjectURL(url)
      } catch (e) { this.$notify({ type: 'error', title: 'Gagal', text: 'Export CSV gagal.' }) }
    },
  },
}
</script>
