<template>
  <div class="landing-page">
    <!-- NAVBAR -->
    <nav :class="['navbar', { scrolled: scrolled }]">
      <div class="nav-container">
        <div class="nav-brand">
          <v-icon :color="scrolled ? 'primary' : 'white'" size="28">mdi-bus</v-icon>
          <span class="brand-text">EZBus</span>
        </div>
        <div class="nav-links">
          <a href="#beranda" @click.prevent="scrollTo('beranda')">Beranda</a>
          <a href="#armada" @click.prevent="scrollTo('armada')">Armada</a>
          <a href="#kontak" @click.prevent="scrollTo('kontak')">Hubungi Kami</a>
        </div>
        <div class="nav-actions">
          <v-btn color="primary" depressed to="/customer/pesan">Pesan Bus</v-btn>
          <v-btn text :color="scrolled ? 'primary' : 'white'" to="/login">Masuk</v-btn>
          <v-btn color="primary" depressed to="/customer/register" class="d-none d-sm-flex">Daftar</v-btn>
        </div>
        <v-btn icon class="d-flex d-sm-none" @click="mobileMenu = !mobileMenu">
          <v-icon>{{ mobileMenu ? 'mdi-close' : 'mdi-menu' }}</v-icon>
        </v-btn>
      </div>
      <v-expand-transition>
        <div v-if="mobileMenu" class="mobile-menu d-flex d-sm-none">
          <a href="#beranda" @click.prevent="scrollTo('beranda'); mobileMenu = false">Beranda</a>
          <a href="#armada" @click.prevent="scrollTo('armada'); mobileMenu = false">Armada</a>
          <a href="#kontak" @click.prevent="scrollTo('kontak'); mobileMenu = false">Hubungi Kami</a>
          <v-btn text color="primary" to="/login" block>Masuk</v-btn>
          <v-btn color="primary" depressed to="/customer/register" block>Daftar</v-btn>
        </div>
      </v-expand-transition>
    </nav>

    <!-- HERO -->
    <section id="beranda" class="hero">
      <div class="hero-content">
        <div class="hero-text">
          <v-chip color="rgba(255,255,255,0.2)" text-color="white" small class="mb-4">
            <v-icon left small>mdi-shield-check</v-icon>Terpercaya & Aman
          </v-chip>
          <h1>Perjalanan Rombongan<br>Jadi <span class="highlight">Lebih Mudah</span></h1>
          <p class="hero-subtitle">Sewa bus pariwisata terbaik untuk rombongan Anda. Armada lengkap, harga transparan, dan live tracking real-time.</p>
          <div class="hero-actions">
            <v-btn outlined large dark color="white" href="#armada" @click.prevent="scrollTo('armada')">
              Lihat Armada
            </v-btn>
          </div>
          <div class="hero-stats">
            <div class="stat-item">
              <strong>50+</strong>
              <span>Armada Bus</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
              <strong>1000+</strong>
              <span>Perjalanan</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
              <strong>24/7</strong>
              <span>Live Tracking</span>
            </div>
          </div>
        </div>
      </div>
      <div class="hero-wave">
        <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M0 120L60 105C120 90 240 60 360 45C480 30 600 30 720 37.5C840 45 960 60 1080 67.5C1200 75 1320 75 1380 75L1440 75V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" fill="white"/>
        </svg>
      </div>
    </section>

    <!-- ARMADA -->
    <section id="armada" class="armada-section">
      <div class="container">
        <div class="section-header">
          <v-chip color="primary lighten-5" text-color="primary" small class="mb-3">Armada Kami</v-chip>
          <h2>Pilih Armada Terbaik</h2>
          <p>Tersedia berbagai pilihan bus sesuai kebutuhan perjalanan Anda</p>
        </div>

        <v-row v-if="loading" class="justify-center">
          <v-col v-for="n in 3" :key="n" cols="12" md="4">
            <v-skeleton-loader type="card" class="mx-auto" max-width="380" />
          </v-col>
        </v-row>

        <v-row v-else class="justify-center">
          <v-col v-for="bus in busTypes" :key="bus.id" cols="12" md="4">
            <v-card class="bus-card" flat>
              <div class="bus-icon-wrapper">
                <v-icon size="48" :color="bus.color">{{ bus.icon }}</v-icon>
              </div>
              <v-card-title class="bus-title">{{ bus.name }}</v-card-title>
              <v-card-subtitle class="bus-subtitle">{{ bus.capacity }} Kursi</v-card-subtitle>
              <v-card-text>
                <div class="bus-price">
                  <span>Mulai dari</span>
                  <strong>{{ currency(bus.base_price) }}</strong>
                </div>
                <div class="bus-price-info">
                  <span>{{ currency(bus.price_per_km) }} / km</span>
                </div>
                <v-divider class="my-3" />
                <div class="bus-features">
                  <div v-for="(feature, i) in bus.features" :key="i" class="feature-item">
                    <v-icon small color="success">mdi-check-circle</v-icon>
                    <span>{{ feature }}</span>
                  </div>
                </div>
              </v-card-text>

            </v-card>
          </v-col>
        </v-row>

        <div v-if="!loading && busTypes.length === 0" class="text-center py-8">
          <v-icon size="64" color="grey lighten-1">mdi-bus-alert</v-icon>
          <p class="grey--text mt-3">Belum ada armada tersedia</p>
        </div>
      </div>
    </section>

    <!-- FOOTER -->
    <footer id="kontak" class="footer">
      <div class="container">
        <div class="footer-content">
          <div class="footer-brand">
            <div class="d-flex align-center mb-3">
              <v-icon color="white" size="24" class="mr-2">mdi-bus</v-icon>
              <span class="text-h6 font-weight-bold white--text">EZBus</span>
            </div>
            <p class="grey--text text--lighten-1 body-2">Sistem informasi pemesanan bus pariwisata terpercaya dengan live tracking GPS.</p>
          </div>
          <div class="footer-links">
            <h4>Menu</h4>
            <a href="#beranda" @click.prevent="scrollTo('beranda')">Beranda</a>
            <a href="#armada" @click.prevent="scrollTo('armada')">Armada</a>
            <a href="/login">Masuk</a>
            <a href="/customer/register">Daftar</a>
          </div>
          <div class="footer-links">
            <h4>Legal</h4>
            <a href="/privacy">Kebijakan Privasi</a>
            <a href="/terms">Syarat & Ketentuan</a>
          </div>
        </div>
        <v-divider class="footer-divider" />
        <div class="footer-bottom">
          <span>&copy; {{ currentYear }} EZBus Pariwisata. Semua hak dilindungi.</span>
        </div>
      </div>
    </footer>
  </div>
</template>

<script>
import axios from 'axios'
export default {
  data: () => ({
    scrolled: false,
    mobileMenu: false,
    loading: false,
    busTypes: [],
    busIcons: {
      0: { icon: 'mdi-bus', color: 'primary' },
      1: { icon: 'mdi-bus-side', color: 'teal' },
      2: { icon: 'mdi-bus-articulated-front', color: 'deep-purple' },
    },
    busFeatures: {
      0: ['AC Dingin', 'Audio System', 'Kursi Reclining'],
      1: ['AC Dingin', 'TV LCD', 'Bagasi Luas', 'Audio System'],
      2: ['AC Dingin', 'Leg Rest', 'USB Charger', 'Kursi Premium', 'Snack Box'],
    },
  }),
  computed: {
    currentYear() {
      return new Date().getFullYear();
    },
  },
  mounted() {
    window.addEventListener('scroll', this.handleScroll);
    this.fetchBusTypes();
  },
  beforeDestroy() {
    window.removeEventListener('scroll', this.handleScroll);
  },
  methods: {
    handleScroll() {
      this.scrolled = window.scrollY > 50;
    },
    scrollTo(id) {
      const el = document.getElementById(id);
      if (el) el.scrollIntoView({ behavior: 'smooth' });
    },
    async fetchBusTypes() {
      this.loading = true;
      try {
        const response = await axios.get('/booking-options');
        const types = response.data.bus_types || [];
        this.busTypes = types
          .filter(bt => bt.is_available !== false)
          .map((bt, index) => ({
            ...bt,
            icon: this.busIcons[index]?.icon || 'mdi-bus',
            color: this.busIcons[index]?.color || 'primary',
            features: this.busFeatures[index] || ['AC Dingin', 'Audio System'],
          }));
      } catch (error) {
        console.error('Gagal memuat armada:', error);
      } finally {
        this.loading = false;
      }
    },
    currency(value) {
      return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
    },
  },
};
</script>

<style scoped>
.landing-page {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* NAVBAR */
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
  padding: 16px 0;
  transition: all 0.3s ease;
}

.navbar.scrolled {
  background: white;
  box-shadow: 0 2px 20px rgba(0,0,0,0.1);
  padding: 10px 0;
}

.nav-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.nav-brand {
  display: flex;
  align-items: center;
  gap: 8px;
}

.brand-text {
  font-size: 1.4rem;
  font-weight: 800;
  color: #6f36d8;
}

.navbar.scrolled .brand-text {
  color: #6f36d8;
}
.navbar:not(.scrolled) .brand-text { color: white; }

.nav-links {
  display: flex;
  gap: 32px;
}

.nav-links a {
  text-decoration: none;
  color: white;
  font-weight: 500;
  font-size: 0.95rem;
  transition: color 0.2s;
}

.navbar.scrolled .nav-links a {
  color: #4a4458;
}

.nav-links a:hover {
  color: #6f36d8;
}

.nav-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.mobile-menu {
  flex-direction: column;
  gap: 12px;
  padding: 16px 24px;
  background: white;
  border-top: 1px solid #eee;
}

.mobile-menu a {
  text-decoration: none;
  color: #4a4458;
  font-weight: 500;
  padding: 8px 0;
}

/* HERO */
.hero {
  background: linear-gradient(135deg, #7c3aed 0%, #6f36d8 50%, #5b21b6 100%);
  min-height: 100vh;
  display: flex;
  align-items: flex-start;
  position: relative;
  overflow: hidden;
  padding-top: 80px;
}

.hero::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -20%;
  width: 600px;
  height: 600px;
  border-radius: 50%;
  background: rgba(255,255,255,0.05);
}

.hero::after {
  content: '';
  position: absolute;
  bottom: -30%;
  left: -10%;
  width: 400px;
  height: 400px;
  border-radius: 50%;
  background: rgba(255,255,255,0.03);
}

.hero-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 60px 24px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  justify-content: space-between;
  gap: 40px;
  width: 100%;
  position: relative;
  z-index: 1;
}

.hero-text {
  flex: 1;
  max-width: 600px;
}

.hero-text h1 {
  font-size: 3.2rem;
  font-weight: 800;
  color: white;
  line-height: 1.15;
  margin-bottom: 20px;
}

.highlight {
  background: linear-gradient(120deg, #fbbf24 0%, #f59e0b 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-subtitle {
  font-size: 1.15rem;
  color: rgba(255,255,255,0.85);
  line-height: 1.7;
  margin-bottom: 32px;
}

.hero-actions {
  display: flex;
  gap: 16px;
  margin-bottom: 48px;
  flex-wrap: wrap;
}

.cta-btn {
  border-radius: 12px !important;
  font-weight: 700;
  text-transform: none;
  letter-spacing: 0;
  padding: 0 28px !important;
}

.hero-stats {
  display: flex;
  gap: 32px;
  align-items: center;
}

.stat-item strong {
  display: block;
  font-size: 1.8rem;
  font-weight: 800;
  color: white;
}

.stat-item span {
  font-size: 0.85rem;
  color: rgba(255,255,255,0.7);
}

.stat-divider {
  width: 1px;
  height: 40px;
  background: rgba(255,255,255,0.2);
}

.hero-visual {
  flex: 0 0 auto;
}

.hero-bus-icon {
  width: 240px;
  height: 240px;
  border-radius: 50%;
  background: rgba(255,255,255,0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-15px); }
}

.hero-wave {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
}

.hero-wave svg {
  display: block;
  width: 100%;
  height: auto;
}

/* ARMADA */
.armada-section {
  padding: 100px 0;
  background: #f8f7fb;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

.section-header {
  text-align: center;
  margin-bottom: 60px;
}

.section-header h2 {
  font-size: 2.2rem;
  font-weight: 800;
  color: #2f2a3d;
  margin-bottom: 12px;
}

.section-header p {
  font-size: 1.05rem;
  color: #8a8494;
}

.bus-card {
  border-radius: 20px !important;
  border: 2px solid #ececf3;
  transition: all 0.3s ease;
  height: 100%;
}

.bus-card:hover {
  border-color: #7c3aed;
  transform: translateY(-5px);
  box-shadow: 0 20px 40px rgba(124, 58, 237, 0.12);
}

.bus-icon-wrapper {
  width: 80px;
  height: 80px;
  margin: 24px auto 0;
  border-radius: 20px;
  background: #f3edff;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bus-title {
  justify-content: center;
  font-size: 1.3rem !important;
  font-weight: 700;
  padding-top: 16px !important;
}

.bus-subtitle {
  text-align: center;
  color: #8a8494;
}

.bus-price {
  text-align: center;
  margin-top: 8px;
}

.bus-price span {
  font-size: 0.85rem;
  color: #8a8494;
  display: block;
}

.bus-price strong {
  font-size: 1.5rem;
  color: #6f36d8;
}

.bus-price-info {
  text-align: center;
  margin-top: 4px;
}

.bus-price-info span {
  font-size: 0.8rem;
  color: #b0adc0;
}

.bus-features {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: #4a4458;
}

/* FOOTER */
.footer {
  background: #2f2a3d;
  padding: 60px 0 30px;
}

.footer-content {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  gap: 40px;
  margin-bottom: 40px;
}

.footer-links h4 {
  color: white;
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: 16px;
}

.footer-links a {
  display: block;
  color: #b0adc0;
  text-decoration: none;
  font-size: 0.9rem;
  padding: 4px 0;
  transition: color 0.2s;
}

.footer-links a:hover {
  color: white;
}

.footer-divider {
  border-color: rgba(255,255,255,0.1) !important;
}

.footer-bottom {
  text-align: center;
  color: #8a8494;
  font-size: 0.85rem;
}

/* RESPONSIVE */
@media (max-width: 960px) {
  .hero-text h1 {
    font-size: 2.4rem;
  }

  .hero-content {
    flex-direction: column;
    text-align: left;
    padding: 40px 24px;
    align-items: stretch;
  }

  .hero-actions {
    justify-content: center;
  }

  .hero-stats {
    justify-content: center;
  }

  .footer-content {
    grid-template-columns: 1fr;
    text-align: center;
  }
}

@media (max-width: 600px) {
  .hero-text h1 {
    font-size: 1.8rem;
  }

  .nav-links {
    display: none;
  }

  .hero-stats {
    gap: 16px;
  }

  .stat-item strong {
    font-size: 1.3rem;
  }
}
</style>
