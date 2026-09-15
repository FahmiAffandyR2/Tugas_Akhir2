<template>
  <v-card id="booking-start" class="booking-start pa-5" light>
    <h2 class="text-h6 font-weight-bold mb-3">Pesan Bus</h2>
    <v-alert v-if="error" dense text type="error">{{ error }}</v-alert>
    <v-form ref="form" @submit.prevent="continueBooking">
      <booking-journey-fields v-model="form" :items="items" :loading="loading" />
      <v-btn type="submit" block color="primary" :disabled="loading || !items.some(item => !item.disabled)">Lanjut ke Detail Perjalanan<v-icon right>mdi-arrow-right</v-icon></v-btn>
    </v-form>
  </v-card>
</template>
<script>
import axios from 'axios'
import BookingJourneyFields from '@/components/BookingJourneyFields.vue'
import AuthService from '@/services/AuthService'
import { busOptions, draftKey, effectiveReturnDate, validateJourney } from '@/utils/bookingFlow'
export default {
  components: { BookingJourneyFields },
  data: () => ({ form: { tripStyle: 'day_trip', rentalDays: null, busTypeId: null, departureDate: '', departureTime: '', origin: '', destination: '', returnDate: '', returnTime: '' }, items: [], loading: false, error: '', requestId: 0 }),
  computed: { dates() { return `${this.form.departureDate}/${effectiveReturnDate(this.form)}` } },
  watch: { dates() { this.load() } },
  created() { this.load() },
  methods: {
    async load() {
      const id = ++this.requestId
      this.loading = true
      this.error = ''
      try {
        const response = await axios.get('/booking-options', { params: { departure_date: this.form.departureDate || undefined, return_date: effectiveReturnDate(this.form) || undefined, passenger_count: 1 } })
        if (id !== this.requestId) return
        this.items = busOptions(response.data.bus_types || [])
        if (this.form.busTypeId && !this.items.some(item => item.id === this.form.busTypeId && !item.disabled)) this.form.busTypeId = null
      } catch (_) { if (id === this.requestId) { this.items = []; this.error = 'Ketersediaan bus belum dapat dimuat. Silakan muat ulang halaman.' } }
      finally { if (id === this.requestId) this.loading = false }
    },
    continueBooking() {
      if (this.loading || !this.items.some(item => item.id === this.form.busTypeId && !item.disabled)) return
      if (!this.$refs.form.validate()) return
      this.error = validateJourney(this.form)
      if (this.error) return
      sessionStorage.setItem(draftKey, JSON.stringify(this.form))
      this.$router.push(AuthService.isUserLoggedIn('customer') ? '/customer/pesan' : '/login?to=/customer/pesan')
    },
  },
}
</script>
<style scoped>
.booking-start { width:100%; max-width:460px; margin-left:auto; border-radius:18px; text-align:left; }
</style>
