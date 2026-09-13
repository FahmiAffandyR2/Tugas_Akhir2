<template>
  <div>
    <section class="customer-hero pa-6 pa-md-9 mb-7">
      <div class="d-flex justify-end mb-4">
        <v-btn color="white" class="primary--text" to="/customer/pesan"><v-icon left>mdi-bus</v-icon>Pesan Bus</v-btn>
      </div>
      <div>
        <div class="hero-label mb-3"><v-icon small color="white" class="mr-1">mdi-star-four-points</v-icon> Perjalanan jadi lebih mudah</div>
        <h1 class="text-h4 text-md-h3 font-weight-bold mb-3">Mau pergi ke mana?</h1>
        <p class="hero-copy mb-6">Pesan satu bus penuh untuk study tour, wisata keluarga, gathering, atau perjalanan instansi.</p>
      </div>
    </section>

    <v-row class="mb-3">
      <v-col v-for="stat in stats" :key="stat.label" cols="12" sm="4"><v-card flat class="stat-card pa-5 d-flex align-center"><div :class="['stat-icon', stat.color]" class="mr-4"><v-icon color="white">{{ stat.icon }}</v-icon></div><div><div class="text-h5 font-weight-bold">{{ stat.value }}</div><div class="body-2 grey--text">{{ stat.label }}</div></div></v-card></v-col>
    </v-row>

    <div class="d-flex align-center justify-space-between mb-4"><div><h2 class="text-h5 font-weight-bold mb-1">Pilih bus sesuai kebutuhan</h2><p class="grey--text mb-0">Kapasitas dan kenyamanan untuk setiap jenis perjalanan.</p></div></div>
    <v-row class="mb-5"><v-col v-for="bus in buses" :key="bus.title" cols="12" md="4"><v-card flat class="bus-card pa-5"><div class="bus-visual mb-4"><v-img :src="bus.image" :alt="bus.title" height="150" class="bus-photo" gradient="to bottom, rgba(0,0,0,.05), rgba(0,0,0,.22)" /><span class="bus-badge">{{ bus.capacity }}</span></div><h3 class="text-h6 font-weight-bold">{{ bus.title }}</h3><p class="grey--text body-2">{{ bus.description }}</p><div class="d-flex flex-wrap"><v-chip v-for="feature in bus.features" :key="feature" small class="mr-2 mb-2">{{ feature }}</v-chip></div></v-card></v-col></v-row>

    <v-card flat class="steps-card pa-6 pa-md-7"><h2 class="text-h5 font-weight-bold mb-6">Pesan bus dalam 2 langkah</h2><v-row><v-col v-for="(step,index) in steps" :key="step.title" cols="12" md="6" class="d-flex"><div class="step-number mr-4">{{ index+1 }}</div><div><h3 class="font-weight-bold mb-1">{{ step.title }}</h3><p class="grey--text body-2 mb-0">{{ step.text }}</p></div></v-col></v-row></v-card>
  </div>
</template>
<script>
export default {
  data: () => ({
    bookingStats: { active: 0, waiting: 0, completed: 0 },
    buses:[{title:'Mini Bus',capacity:'25-35 kursi',color:'#7c3aed',image:'https://images.pexels.com/photos/19517915/pexels-photo-19517915.jpeg?auto=compress&cs=tinysrgb&w=900',description:'Lincah dan nyaman untuk rombongan sedang.',features:['AC','Reclining seat','Audio']},{title:'Large Bus',capacity:'45-59 kursi',color:'#2563eb',image:'https://images.pexels.com/photos/18029643/pexels-photo-18029643.jpeg?auto=compress&cs=tinysrgb&w=900',description:'Kapasitas besar untuk study tour dan gathering.',features:['AC','Bagasi luas','TV']},{title:'Luxury Bus',capacity:'18-32 kursi',color:'#d97706',image:'https://images.pexels.com/photos/29702987/pexels-photo-29702987.jpeg?auto=compress&cs=tinysrgb&w=900',description:'Pengalaman premium dengan fasilitas terbaik.',features:['Leg rest','USB charger','Kursi premium']}],
    steps:[{title:'Detail perjalanan',text:'Pilih bus, isi data pergi, lalu lengkapi data pulang.'},{title:'Konfirmasi',text:'Periksa rincian dan harga dasar bus, lalu kirim pemesanan.'}],
  }),
  computed: {
    stats() {
      return [
        { label: 'Pemesanan aktif', value: this.bookingStats.active, icon: 'mdi-calendar-check', color: 'purple' },
        { label: 'Menunggu penawaran', value: this.bookingStats.waiting, icon: 'mdi-file-clock-outline', color: 'orange' },
        { label: 'Perjalanan selesai', value: this.bookingStats.completed, icon: 'mdi-check-decagram-outline', color: 'green' },
      ]
    },
  },
  created() {
    this.loadBookingStats()
  },
  methods: {
    async loadBookingStats() {
      try {
        const response = await axios.get('/charter-bookings/mine')
        const bookings = response.data.bookings || []
        const isCompleted = item => item.status === 'completed' || Boolean(item.operational_trip && item.operational_trip.ended_at)
        const isWaiting = item => item.status === 'waiting_quote'
        const isActive = item => !isWaiting(item) && !isCompleted(item) && !['rejected', 'cancelled'].includes(item.status)

        this.bookingStats = {
          active: bookings.filter(isActive).length,
          waiting: bookings.filter(isWaiting).length,
          completed: bookings.filter(isCompleted).length,
        }
      } catch (e) {
        this.bookingStats = { active: 0, waiting: 0, completed: 0 }
        console.warn('Gagal memuat statistik booking:', e.message)
      }
    },
  },
}
</script>
<style scoped>
.customer-hero{border-radius:24px;color:#fff;background:linear-gradient(120deg,#6325cf,#9658f5);box-shadow:0 16px 36px rgba(111,54,216,.22);overflow:hidden}.hero-label{display:inline-flex;align-items:center;padding:7px 12px;border-radius:30px;background:rgba(255,255,255,.14);font-size:.78rem}.hero-copy{color:rgba(255,255,255,.85);max-width:630px;font-size:1.08rem}.hero-bus{position:relative}.route-line{display:flex;align-items:center;justify-content:space-between;width:250px;border-top:2px dashed rgba(255,255,255,.6)}.route-line span{width:10px;height:10px;border-radius:50%;background:#fff;margin-top:-6px}.stat-card,.bus-card,.steps-card{border-radius:18px!important;border:1px solid #ececf3}.stat-icon{width:52px;height:52px;border-radius:15px;display:flex;align-items:center;justify-content:center}.purple{background:#7c3aed}.orange{background:#f59e0b}.green{background:#16a34a}.bus-card{height:100%;transition:.2s}.bus-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(55,48,70,.09)!important}.bus-visual{height:150px;border-radius:15px;background:#f7f5fb;overflow:hidden;position:relative}.bus-photo{height:100%;border-radius:15px}.bus-badge{position:absolute;right:12px;top:12px;background:#fff;padding:5px 10px;border-radius:20px;font-size:.75rem;box-shadow:0 6px 16px rgba(31,28,44,.12)}.step-number{flex:none;width:40px;height:40px;border-radius:12px;background:#efe7ff;color:#7c3aed;font-weight:bold;display:flex;align-items:center;justify-content:center}
</style>
