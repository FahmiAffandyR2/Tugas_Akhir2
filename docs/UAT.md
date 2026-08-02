# User Acceptance Testing (UAT)

User Acceptance Testing (UAT) dilakukan untuk memastikan sistem yang dikembangkan telah sesuai dengan kebutuhan pengguna akhir, khususnya Admin PO dan Driver. Pengujian ini berfokus pada kesesuaian fungsi sistem terhadap proses operasional monitoring bus pariwisata, mulai dari proses login, pengelolaan hak akses, pemantauan lokasi bus, pengiriman data GPS, hingga tampilan informasi pada dashboard.

## Tabel Skenario UAT

| No | Aktor | Fitur yang Diuji | Skenario Pengujian | Langkah Pengujian | Hasil yang Diharapkan | Hasil Aktual | Status |
|---|---|---|---|---|---|---|---|
| 1 | Admin PO | Login Admin | Admin melakukan login menggunakan akun yang valid | 1. Buka halaman login 2. Masukkan email dan password admin 3. Klik tombol login | Sistem berhasil memvalidasi akun dan menampilkan dashboard admin | Sesuai harapan | Diterima |
| 2 | Admin PO | Login Admin | Admin melakukan login dengan email atau password yang salah | 1. Buka halaman login 2. Masukkan email atau password yang tidak sesuai 3. Klik tombol login | Sistem menolak login dan menampilkan pesan kesalahan | Sesuai harapan | Diterima |
| 3 | Admin PO | Hak Akses Admin | Admin mengakses halaman dashboard setelah login | 1. Login sebagai admin 2. Buka halaman dashboard | Sistem menampilkan menu dan fitur yang sesuai dengan hak akses admin | Sesuai harapan | Diterima |
| 4 | Customer / Driver | Hak Akses Role | User non-admin mencoba mengakses fitur admin | 1. Login sebagai customer atau driver 2. Akses halaman khusus admin | Sistem menolak akses karena user tidak memiliki role admin | Sesuai harapan | Diterima |
| 5 | Admin PO | Dashboard Live Tracking | Admin membuka menu Live Tracking | 1. Login sebagai admin 2. Pilih menu Live Tracking | Sistem menampilkan halaman peta dan daftar perjalanan bus yang sedang aktif | Sesuai harapan | Diterima |
| 6 | Admin PO | Menampilkan Data Trip Aktif | Admin melihat daftar bus yang sedang berjalan | 1. Buka menu Live Tracking 2. Perhatikan daftar trip aktif | Sistem menampilkan data perjalanan aktif seperti driver, rute, waktu mulai, dan status GPS | Sesuai harapan | Diterima |
| 7 | Driver | Mulai Perjalanan | Driver memulai perjalanan yang telah ditugaskan | 1. Login sebagai driver 2. Pilih perjalanan 3. Klik mulai perjalanan | Sistem mengubah status perjalanan menjadi aktif dan menyimpan waktu mulai perjalanan | Sesuai harapan | Diterima |
| 8 | Driver | Pengiriman GPS | Driver mengirimkan posisi GPS selama perjalanan aktif | 1. Perjalanan berada dalam status aktif 2. Sistem driver mengirim latitude, longitude, dan speed | Sistem menyimpan posisi terakhir bus dan mengirim data lokasi ke dashboard admin | Sesuai harapan | Diterima |
| 9 | Admin PO | Realtime Tracking | Admin memantau pergerakan bus pada peta | 1. Admin membuka Live Tracking 2. Driver mengirim posisi GPS terbaru | Marker bus pada peta berpindah mengikuti lokasi terbaru yang dikirim driver | Sesuai harapan | Diterima |
| 10 | Admin PO | Polling Posisi GPS | Sistem memperbarui posisi bus secara berkala | 1. Buka Live Tracking 2. Tunggu beberapa detik saat data GPS berubah | Sistem melakukan pembaruan posisi secara berkala sebagai cadangan realtime tracking | Sesuai harapan | Diterima |
| 11 | Driver | Validasi Trip Aktif | Driver mengirim GPS sebelum perjalanan dimulai atau setelah selesai | 1. Pilih perjalanan yang belum aktif atau sudah selesai 2. Kirim lokasi GPS | Sistem menolak pengiriman lokasi dan menampilkan pesan bahwa lokasi hanya dapat dikirim saat trip aktif | Sesuai harapan | Diterima |
| 12 | Driver | Selesai Perjalanan | Driver mengakhiri perjalanan | 1. Login sebagai driver 2. Pilih perjalanan aktif 3. Klik selesai perjalanan | Sistem menyimpan waktu selesai perjalanan dan mengubah status perjalanan menjadi selesai | Sesuai harapan | Diterima |
| 13 | Admin PO | Tampilan Setelah Trip Selesai | Admin melihat dashboard setelah perjalanan selesai | 1. Driver menyelesaikan perjalanan 2. Admin membuka ulang Live Tracking | Bus yang sudah selesai tidak lagi ditampilkan pada daftar perjalanan aktif | Sesuai harapan | Diterima |
| 14 | Admin PO | Informasi GPS Tidak Tersedia | Admin membuka Live Tracking saat bus belum mengirim lokasi | 1. Buka menu Live Tracking 2. Pilih trip yang belum memiliki koordinat GPS | Sistem menampilkan status menunggu lokasi GPS driver | Sesuai harapan | Diterima |
| 15 | Admin PO | Visualisasi Peta | Admin melihat lokasi bus pada peta | 1. Buka menu Live Tracking 2. Pastikan bus memiliki data koordinat | Sistem menampilkan marker bus pada peta Leaflet/OpenStreetMap sesuai koordinat terakhir | Sesuai harapan | Diterima |

## Kesimpulan UAT

Berdasarkan hasil User Acceptance Testing, seluruh skenario pengujian yang dilakukan telah memperoleh status diterima. Sistem dinilai telah sesuai dengan kebutuhan pengguna, terutama dalam proses login, pembatasan hak akses, pemantauan perjalanan aktif, pengiriman data GPS, dan visualisasi posisi bus pada dashboard live tracking. Dengan demikian, sistem layak untuk digunakan pada tahap implementasi dan pengujian lanjutan.

