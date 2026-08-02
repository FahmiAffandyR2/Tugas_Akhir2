# Tahapan Penelitian

## Judul sementara

**Rancang Bangun Sistem Informasi Pemesanan Bus Pariwisata Berbasis Web dengan Manajemen Armada, Pembayaran, dan Live Tracking GPS Driver.**

## 1. Identifikasi masalah

Tahap awal dilakukan untuk menemukan masalah pada proses pemesanan dan operasional bus pariwisata yang masih dilakukan secara manual atau belum terintegrasi.

Masalah utama yang menjadi fokus:

- Customer sulit memantau status pesanan dan pembayaran.
- Admin sulit mengelola penawaran, pembayaran, bus, driver, dan jadwal dalam satu sistem.
- Risiko bus atau driver mendapat jadwal yang bentrok.
- Customer belum dapat mengetahui status dan lokasi bus secara realtime.
- Pendapatan berisiko dihitung sebelum pembayaran benar-benar diterima.

**Output:** daftar masalah, tujuan sistem, batasan sistem, dan kebutuhan awal pengguna.

## 2. Studi literatur

Mengumpulkan teori, jurnal, dokumentasi, dan penelitian terdahulu yang relevan.

Topik yang dikaji:

- Sistem informasi pemesanan atau rental kendaraan.
- Manajemen armada dan penjadwalan driver.
- Sistem pembayaran dan verifikasi bukti transfer.
- Global Positioning System (GPS) dan live tracking.
- Progressive Web App atau aplikasi web responsif.
- OpenStreetMap, Leaflet, dan OSRM untuk peta serta routing.
- Laravel sebagai backend API dan Vue.js sebagai frontend.

**Output:** landasan teori dan perbandingan penelitian terdahulu.

## 3. Pengumpulan data

Data dikumpulkan dari calon pengguna sistem, yaitu admin operasional, driver, dan customer.

Metode yang dapat digunakan:

| Metode | Tujuan | Contoh data yang dikumpulkan |
|---|---|---|
| Observasi | Memahami proses operasional berjalan | Cara admin menerima pesanan dan menentukan bus |
| Wawancara | Mendapatkan kebutuhan pengguna | Kriteria pemilihan bus, kebutuhan driver, kendala pembayaran |
| Dokumentasi | Mengumpulkan data pendukung | Data armada, depo, driver, tarif, dan contoh jadwal |
| Kuesioner | Mengukur penerimaan pengguna | Kemudahan pemesanan, tampilan peta, dan kejelasan status |

**Output:** data primer dan sekunder untuk analisis kebutuhan.

## 4. Analisis kebutuhan sistem

Kebutuhan dibagi menjadi fungsional dan nonfungsional.

### Kebutuhan fungsional

| Aktor | Kebutuhan utama |
|---|---|
| Customer | Registrasi, login, pesan bus, melihat penawaran, membayar, melihat status dan armada |
| Admin | Mengelola user, depo, bus, driver, booking, penawaran, pembayaran, dan live tracking |
| Driver | Login, melihat jadwal, memulai perjalanan, mengirim GPS, dan menyelesaikan perjalanan |

### Kebutuhan nonfungsional

- Sistem dapat dibuka melalui desktop dan perangkat mobile.
- Data pembayaran hanya dapat diverifikasi admin.
- Lokasi GPS hanya dapat diakses pengguna yang berhak.
- Sistem mencegah konflik jadwal bus dan driver.
- Akses GPS pada perangkat produksi menggunakan HTTPS.

**Output:** dokumen Software Requirement Specification (SRS) atau kebutuhan sistem.

## 5. Perancangan sistem

Perancangan dilakukan sebelum implementasi.

Artefak yang digunakan:

- Use Case Diagram untuk customer, admin, dan driver.
- Activity Diagram untuk alur pemesanan hingga perjalanan selesai.
- ERD untuk hubungan tabel database.
- Sequence Diagram untuk proses pembayaran dan pengiriman GPS.
- Wireframe atau mockup antarmuka.

Alur utama sistem:

```text
Customer membuat booking
  -> Admin mengirim penawaran
  -> Customer melakukan pembayaran
  -> Admin memverifikasi pembayaran
  -> Pendapatan tercatat
  -> Admin menetapkan bus dan driver
  -> Jadwal operasional dibuat
  -> Driver memulai perjalanan dan GPS dikirim
  -> Admin/customer memantau status sesuai hak akses
  -> Driver menyelesaikan perjalanan
```

**Output:** rancangan proses, rancangan database, dan rancangan antarmuka.

## 6. Implementasi sistem

Implementasi dibagi menjadi beberapa modul agar mudah diuji.

| Tahap | Modul |
|---|---|
| 1 | Autentikasi dan pemisahan portal admin, driver, serta customer |
| 2 | Manajemen depo, bus, dan driver |
| 3 | Pemesanan bus pariwisata dan penawaran admin |
| 4 | Pembayaran transfer, unggah bukti, dan verifikasi pembayaran |
| 5 | Transaksi pendapatan dashboard |
| 6 | Penugasan bus dan driver serta pemeriksaan konflik jadwal |
| 7 | Jadwal perjalanan driver dan live tracking GPS admin |
| 8 | Tracking customer serta rute charter berbasis koordinat |

Teknologi yang digunakan dalam sistem saat ini:

- Frontend: Vue.js 2 dan Vuetify.
- Backend: Laravel API.
- Database: MySQL.
- Peta: Leaflet dan OpenStreetMap.
- Routing: OSRM atau penyedia routing lain.
- Tracking: Geolocation API pada perangkat driver.

**Output:** aplikasi yang dapat dijalankan dan diuji.

## 7. Pengujian sistem

Pengujian dilakukan menggunakan black-box testing dan, bila diperlukan, User Acceptance Test (UAT).

Contoh skenario black-box:

| No. | Skenario | Hasil yang diharapkan |
|---|---|---|
| 1 | Customer membuat booking | Booking tersimpan dengan status menunggu penawaran |
| 2 | Admin mengirim penawaran | Customer melihat harga dan rekening pembayaran |
| 3 | Customer mengunggah bukti pembayaran | Status menjadi menunggu verifikasi |
| 4 | Admin memverifikasi pembayaran | Status lunas dan pendapatan dashboard bertambah |
| 5 | Admin menetapkan bus/driver yang bentrok | Sistem menolak penugasan |
| 6 | Admin menetapkan bus/driver valid | Jadwal muncul pada akun driver |
| 7 | Driver memulai perjalanan | GPS aktif dan tampil pada live tracking admin |
| 8 | Driver menyelesaikan perjalanan | GPS berhenti dan status perjalanan selesai |

**Output:** tabel hasil pengujian dan daftar perbaikan.

## 8. Evaluasi pengguna

Setelah pengujian teknis, mintalah pengguna mencoba sistem lalu mengisi kuesioner, misalnya menggunakan skala Likert 1–5.

Aspek evaluasi:

- Kemudahan customer membuat pesanan.
- Kejelasan status pembayaran.
- Kemudahan admin menetapkan armada dan driver.
- Keakuratan informasi jadwal.
- Kemudahan driver menjalankan tracking.
- Manfaat live tracking bagi admin dan customer.

Rumus persentase kelayakan:

```text
Persentase = (total skor diperoleh / total skor maksimum) x 100%
```

**Output:** nilai kelayakan dan kesimpulan penerimaan sistem.

## 9. Kesimpulan dan saran

Kesimpulan menjawab tujuan penelitian, misalnya apakah sistem berhasil mengintegrasikan pemesanan, pembayaran, armada, driver, pendapatan, dan tracking.

Saran pengembangan berikutnya:

- Integrasi payment gateway otomatis.
- Titik penjemputan dan tujuan berbasis koordinat untuk routing charter.
- Peta realtime khusus customer.
- Riwayat detail perjalanan GPS.
- Notifikasi realtime melalui Firebase atau WebSocket.
- Laporan pendapatan per periode dan per depo.

