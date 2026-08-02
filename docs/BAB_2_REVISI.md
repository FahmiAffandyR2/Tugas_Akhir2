# BAB 2
# TINJAUAN PUSTAKA

Bab ini membahas teori dan konsep yang mendukung penelitian mengenai sistem booking bus pariwisata berbasis website. Pembahasan meliputi sistem informasi, sistem informasi manajemen, booking bus pariwisata, website, GPS dan live tracking, Geolocation API, database, MySQL, Laravel, Vue.js, Vuetify, Axios, Leaflet, metode Prototype, UML, ERD, dan Black Box Testing.

## 2.1 Sistem Informasi

Sistem informasi merupakan kumpulan komponen yang saling berhubungan untuk mengumpulkan, memproses, menyimpan, dan menyajikan informasi. Sistem informasi digunakan untuk membantu kegiatan operasional, pengelolaan data, serta pengambilan keputusan dalam suatu organisasi. Pada sistem berbasis website, penggunaan sistem informasi dapat meningkatkan distribusi sumber daya dan mendukung keberhasilan organisasi dalam mengelola proses bisnis secara lebih terstruktur (Nusa & Faisal, 2020).

Dalam penelitian ini, sistem informasi digunakan sebagai dasar pengembangan aplikasi booking bus pariwisata. Sistem membantu proses pengelolaan data customer, driver, bus, rute, jadwal perjalanan, pemesanan, serta pelacakan perjalanan agar informasi dapat tersimpan secara terpusat dan mudah diakses oleh pengguna sesuai hak aksesnya. Penelitian terkait sistem informasi penyewaan bus berbasis website juga menunjukkan bahwa sistem berbasis web dapat membantu proses pendataan, pencarian, dan transaksi agar pelayanan menjadi lebih cepat dan akurat (Savitri & Supriyono, 2021).

## 2.2 Sistem Informasi Manajemen

Sistem informasi manajemen adalah sistem yang digunakan untuk membantu pihak manajemen dalam mengelola data dan memperoleh informasi yang dibutuhkan untuk pengambilan keputusan. Sistem ini biasanya menyajikan data dalam bentuk laporan, tabel, dashboard, atau ringkasan informasi yang mudah dipahami oleh pengguna.

Pada sistem booking bus pariwisata, sistem informasi manajemen diterapkan pada sisi admin. Admin dapat melihat ringkasan data melalui dashboard, mengelola data customer dan driver, mengatur bus, depo armada, rute, titik berhenti, jadwal perjalanan, serta memantau booking bus dan complaints. Dengan adanya sistem ini, proses administrasi dan operasional dapat berjalan lebih terstruktur. Hal ini sejalan dengan penelitian Pangestu yang menunjukkan bahwa sistem sewa bus pariwisata berbasis online dapat mempermudah transaksi, pengolahan data jenis bus, data bus, data pengguna, dan pengelolaan transaksi sewa bus pariwisata (Pangestu, 2023).

## 2.3 Booking Bus Pariwisata

Booking bus pariwisata merupakan proses pemesanan bus yang dilakukan oleh customer untuk kebutuhan perjalanan rombongan, seperti wisata, study tour, acara keluarga, kegiatan kantor, atau perjalanan khusus lainnya. Proses booking umumnya meliputi pengisian data perjalanan, pemilihan jenis bus, konfirmasi permintaan, penawaran harga, serta penetapan armada dan driver. Pada penelitian sistem penyewaan bus berbasis website, proses penyewaan secara manual dinilai kurang efektif karena customer harus datang langsung untuk mengetahui jenis dan ketersediaan bus, sehingga sistem website dibutuhkan untuk menghemat waktu dan biaya (Savitri & Supriyono, 2021).

Dalam sistem yang dikembangkan, customer dapat melakukan pemesanan bus melalui halaman pesan bus. Customer mengisi rute perjalanan, tanggal, jumlah peserta, catatan tambahan, lalu memilih jenis bus yang tersedia. Setelah permintaan dikirim, admin dapat memeriksa data pemesanan, memberikan penawaran harga, dan menetapkan armada serta driver yang akan bertugas. Dengan alur tersebut, sistem diharapkan dapat memberikan kemudahan bagi customer dan admin dalam mengelola permintaan penyewaan bus, sebagaimana penelitian terdahulu yang menyatakan bahwa sistem penyewaan bus berbasis web mempermudah transaksi dan pendataan (Pangestu, 2023; Savitri & Supriyono, 2021).

## 2.4 Website

Website adalah kumpulan halaman yang dapat diakses melalui browser dan digunakan untuk menyajikan informasi maupun menjalankan layanan berbasis internet. Website dapat digunakan oleh berbagai perangkat, seperti komputer, laptop, tablet, maupun smartphone, selama perangkat tersebut memiliki browser dan koneksi internet. Sistem informasi berbasis website banyak digunakan karena mampu mendukung proses penyajian informasi, pengolahan data, dan interaksi pengguna secara lebih fleksibel (Nusa & Faisal, 2020).

Pada penelitian ini, website digunakan sebagai media utama untuk mengakses sistem. Admin menggunakan website untuk mengelola data dan memantau operasional, driver menggunakan website untuk melihat jadwal serta menjalankan perjalanan, sedangkan customer menggunakan website untuk melakukan registrasi, login, pemesanan bus, dan melihat status pemesanan. Penggunaan website pada layanan penyewaan bus dinilai relevan karena dapat memudahkan customer memperoleh informasi bus tanpa harus datang langsung ke perusahaan (Savitri & Supriyono, 2021).

## 2.5 GPS dan Live Tracking

Global Positioning System atau GPS merupakan teknologi yang digunakan untuk mengetahui posisi suatu perangkat berdasarkan koordinat latitude dan longitude. Dalam aplikasi transportasi, GPS banyak digunakan untuk memantau posisi kendaraan, mengetahui pergerakan armada, serta membantu pengawasan perjalanan secara lebih akurat. Walaupun demikian, pemanfaatan data lokasi perlu memperhatikan kemungkinan perbedaan akurasi akibat kondisi perangkat, jaringan, dan lingkungan sekitar, sehingga informasi lokasi perlu ditampilkan sebagai data pemantauan operasional, bukan sebagai posisi absolut yang selalu sempurna (Ranacher et al., 2015; Wen et al., 2018; Lee et al., 2022).

Live tracking pada sistem ini digunakan untuk memantau posisi driver atau bus ketika perjalanan sedang aktif. Driver dapat memulai perjalanan melalui halaman perjalanan, kemudian sistem akan mengirimkan lokasi perangkat driver ke server. Lokasi tersebut dapat dipantau oleh admin melalui halaman live tracking sehingga admin dapat mengetahui posisi perjalanan yang sedang berlangsung. Dalam konteks sistem booking bus pariwisata, fitur ini mendukung pengawasan perjalanan setelah pemesanan disetujui dan armada ditetapkan.

## 2.6 Geolocation API

Geolocation API adalah fitur pada browser yang memungkinkan aplikasi website memperoleh informasi lokasi perangkat pengguna. Data lokasi yang diperoleh dapat berupa latitude, longitude, akurasi lokasi, dan informasi pendukung lainnya. Penggunaan Geolocation API biasanya membutuhkan izin dari pengguna agar browser dapat mengakses lokasi perangkat. Pada aplikasi berbasis web, pemanfaatan fitur lokasi perlu disertai kontrol akses dan persetujuan pengguna karena data lokasi termasuk informasi yang sensitif (Ranacher et al., 2015; Lee et al., 2022).

Pada sistem ini, Geolocation API digunakan pada sisi driver. Saat driver memulai perjalanan, sistem meminta akses lokasi perangkat. Jika izin lokasi diberikan, sistem dapat mengambil koordinat driver dan mengirimkannya ke server sebagai data live tracking. Fitur ini mendukung proses pemantauan perjalanan oleh admin. Dengan demikian, pengiriman lokasi driver menjadi bagian dari proses operasional perjalanan setelah jadwal aktif.

## 2.7 Database

Database atau basis data adalah tempat penyimpanan data yang digunakan oleh sistem agar data dapat dikelola, dicari, diperbarui, dan digunakan kembali. Database sangat penting dalam aplikasi berbasis website karena hampir seluruh aktivitas pengguna menghasilkan data yang perlu disimpan secara terstruktur. Dalam penelitian pengembangan sistem informasi, database menjadi salah satu komponen utama karena digunakan untuk mendukung penyimpanan dan pengelolaan data aplikasi (Wulandari et al., 2021).

Pada sistem booking bus pariwisata, database digunakan untuk menyimpan data user, customer, driver, bus, depo armada, rute, titik berhenti, jadwal perjalanan, booking bus, complaints, dan data lokasi perjalanan. Dengan penggunaan database, data sistem dapat dikelola secara konsisten dan terpusat.

## 2.8 MySQL

MySQL adalah salah satu sistem manajemen basis data relasional yang menggunakan bahasa SQL untuk mengelola data. MySQL banyak digunakan dalam pengembangan aplikasi web karena mendukung penyimpanan data dalam bentuk tabel yang saling berelasi.

Dalam penelitian ini, MySQL digunakan sebagai database untuk menyimpan data yang dibutuhkan oleh sistem. Relasi antar tabel digunakan untuk menghubungkan data, misalnya data user dengan role, data driver dengan jadwal perjalanan, data bus dengan depo armada, serta data booking dengan customer. Penggunaan MySQL juga banyak ditemukan pada penelitian sistem penyewaan bus berbasis website karena sesuai untuk menyimpan data operasional seperti data bus, pengguna, dan transaksi (Pangestu, 2023; Savitri & Supriyono, 2021).

## 2.9 Laravel

Laravel adalah framework PHP yang digunakan untuk membangun aplikasi web. Laravel menyediakan struktur pengembangan yang rapi melalui konsep routing, controller, model, middleware, validasi, autentikasi, dan pengelolaan database. Laravel juga mendukung pola Model-View-Controller atau MVC sehingga pengembangan sistem menjadi lebih terorganisasi.

Pada sistem ini, Laravel digunakan pada sisi backend untuk mengelola proses bisnis dan komunikasi dengan database. Backend menangani proses login, registrasi, pengelolaan data master, pemesanan bus, penjadwalan perjalanan, pengelolaan status booking, serta penyimpanan lokasi driver saat perjalanan aktif. Framework Laravel banyak digunakan dalam pengembangan sistem informasi berbasis web karena menyediakan struktur pengembangan yang rapi dan mendukung proses pembuatan fitur CRUD secara lebih terorganisasi (Alfarisi et al., 2023; Bagwan & Ghule, 2019).

## 2.10 Vue.js

Vue.js adalah framework JavaScript yang digunakan untuk membangun antarmuka pengguna berbasis komponen. Vue.js memungkinkan pengembang membuat halaman yang dinamis, interaktif, dan mudah dikelola karena setiap bagian tampilan dapat dipisahkan menjadi komponen-komponen tertentu.

Dalam sistem booking bus pariwisata, Vue.js digunakan pada sisi frontend. Halaman seperti login, registrasi, dashboard admin, halaman driver, halaman customer, pemesanan bus, dan live tracking dibangun menggunakan Vue.js agar tampilan sistem lebih interaktif dan responsif terhadap aksi pengguna.

## 2.11 Vuetify

Vuetify adalah framework komponen antarmuka pengguna untuk Vue.js yang menyediakan berbagai komponen siap pakai, seperti button, form, card, dialog, table, navigation drawer, dan layout. Vuetify membantu pengembang membuat tampilan aplikasi yang konsisten dan rapi.

Pada sistem ini, Vuetify digunakan untuk membangun tampilan halaman website. Komponen Vuetify digunakan pada form login, form registrasi, dashboard, tabel data admin, halaman pemesanan customer, serta halaman driver. Dengan Vuetify, tampilan sistem menjadi lebih terstruktur dan mudah digunakan.

## 2.12 Axios

Axios adalah library JavaScript yang digunakan untuk melakukan komunikasi HTTP antara frontend dan backend. Axios dapat digunakan untuk mengambil data, mengirim data, memperbarui data, maupun menghapus data melalui API.

Dalam sistem ini, Axios digunakan oleh frontend untuk berkomunikasi dengan backend Laravel. Contohnya adalah saat user melakukan login, customer mengirim pemesanan bus, admin mengambil daftar data, driver mengambil jadwal perjalanan, dan driver mengirim data lokasi GPS ke server.

## 2.13 Leaflet

Leaflet adalah library JavaScript yang digunakan untuk menampilkan peta interaktif pada website. Leaflet dapat menampilkan marker, polyline, area peta, serta informasi lokasi yang dibutuhkan dalam aplikasi berbasis peta.

Pada sistem booking bus pariwisata, Leaflet digunakan untuk mendukung fitur live tracking. Sistem dapat menampilkan posisi driver atau bus pada peta sehingga admin dapat memantau perjalanan yang sedang berlangsung. Selain itu, peta juga dapat membantu menampilkan rute dan titik pemberhentian perjalanan.

## 2.14 Metode Prototype

Metode Prototype adalah metode pengembangan perangkat lunak yang dilakukan dengan membuat rancangan awal sistem terlebih dahulu, kemudian dievaluasi dan diperbaiki berdasarkan masukan pengguna. Metode ini cocok digunakan ketika kebutuhan pengguna perlu divisualisasikan terlebih dahulu agar pengembang dan pengguna memiliki pemahaman yang sama terhadap sistem yang akan dibuat. Model prototyping memungkinkan pengguna melihat gambaran sistem lebih awal, memberikan umpan balik, dan membantu pengembang menyesuaikan sistem dengan kebutuhan pengguna (Wulandari et al., 2021).

Tahapan metode Prototype meliputi pengumpulan kebutuhan, pembuatan prototype, evaluasi prototype, perbaikan prototype, implementasi sistem, dan pengujian sistem. Dalam penelitian ini, metode Prototype digunakan karena sistem memiliki beberapa aktor dan fitur yang saling berhubungan, yaitu admin, driver, dan customer. Melalui prototype, rancangan halaman dan alur kerja sistem dapat dievaluasi sebelum sistem dikembangkan secara lebih lengkap. Pendekatan ini sesuai untuk sistem yang membutuhkan interaksi langsung antara pengembang dan pengguna agar fungsi sistem yang dibangun sesuai kebutuhan operasional (Wulandari et al., 2021).

## 2.15 Unified Modeling Language

Unified Modeling Language atau UML adalah bahasa pemodelan yang digunakan untuk menggambarkan rancangan sistem secara visual. UML membantu menjelaskan aktor, proses, interaksi, dan struktur sistem sebelum masuk ke tahap implementasi. Dalam pengembangan sistem informasi berbasis web, UML dapat digunakan untuk memodelkan kebutuhan pengguna dan alur sistem agar rancangan lebih mudah dipahami sebelum proses implementasi dilakukan (Prihandoyo, 2018).

Dalam penelitian ini, UML digunakan untuk menggambarkan kebutuhan dan rancangan sistem booking bus pariwisata. Diagram yang dapat digunakan meliputi Use Case Diagram untuk menggambarkan hubungan aktor dengan fitur sistem, Activity Diagram untuk menjelaskan alur aktivitas, Sequence Diagram untuk menjelaskan interaksi antar komponen, dan Class Diagram untuk menggambarkan struktur objek pada sistem.

## 2.16 Data Flow Diagram

Data Flow Diagram atau DFD adalah diagram yang digunakan untuk menggambarkan aliran data dalam sistem. DFD menunjukkan proses yang terjadi, data yang masuk, data yang keluar, penyimpanan data, serta pihak luar yang berinteraksi dengan sistem.

Pada sistem booking bus pariwisata, DFD digunakan untuk menjelaskan alur data antara customer, driver, admin, dan sistem. Customer mengirim data registrasi dan booking, driver mengirim data perjalanan dan lokasi, sedangkan admin mengelola data master serta memantau informasi booking dan live tracking.

## 2.17 Entity Relationship Diagram

Entity Relationship Diagram atau ERD adalah diagram yang digunakan untuk menggambarkan struktur basis data. ERD menunjukkan entitas, atribut, primary key, foreign key, serta relasi antar data. Dalam pengembangan sistem informasi, rancangan database dapat dibuat menggunakan ERD agar struktur data dan hubungan antar entitas lebih jelas sebelum diterapkan ke database (Wulandari et al., 2021).

Dalam penelitian ini, ERD digunakan untuk merancang struktur database sistem. Entitas yang digunakan dapat meliputi users, buses, fleet_depots, routes, stops, trips, planned_trips, charter_bookings, complaints, dan data lain yang mendukung proses booking serta operasional perjalanan.

## 2.18 Black Box Testing

Black Box Testing adalah metode pengujian perangkat lunak yang berfokus pada fungsi sistem tanpa melihat struktur kode program. Pengujian dilakukan dengan memberikan input tertentu dan memeriksa apakah output yang dihasilkan sudah sesuai dengan kebutuhan. Black Box Testing berfokus pada validasi fungsionalitas sistem, sehingga dapat digunakan untuk menemukan kesalahan pada fitur tanpa harus memeriksa kode program secara langsung (Santi et al., 2022).

Pada sistem ini, Black Box Testing digunakan untuk menguji fitur utama seperti registrasi, login, pengelolaan data admin, pemesanan bus customer, penjadwalan perjalanan, perjalanan aktif driver, live tracking, complaints, dan pengelolaan profil. Pengujian ini bertujuan untuk memastikan bahwa setiap fitur berjalan sesuai dengan hasil yang diharapkan. Penelitian terkait pengujian black box menunjukkan bahwa metode ini dapat membantu mengevaluasi dan memperbaiki kualitas perangkat lunak berdasarkan hasil skenario pengujian fungsional (Santi et al., 2022; Raihan & Voutama, 2023).

## Referensi Jurnal yang Disarankan untuk Daftar Pustaka

Alfarisi, I. A., Priandika, A. T., & Puspaningrum, A. S. (2023). Penerapan Framework Laravel pada Sistem Pelayanan Kesehatan (Studi Kasus: Klinik Berkah Medical Center). *Jurnal Ilmiah Computer Science, 2*(1), 1-9. https://doi.org/10.58602/jics.v2i1.11

Bagwan, M. I. K., & Ghule, P. D. S. (2019). A modern review on Laravel-PHP framework.

Lee, K., Sener, I. N., & Mullins, J. A. (2022). An evaluation of emerging data collection technologies for travel demand modeling: From research to practice. *Transportation Research Record*.

Nusa, I. B. S., & Faisal, F. M. (2020). Web-Based Information Systems: Developing a Design Theory. *IOP Conference Series: Materials Science and Engineering, 879*, 012015. https://doi.org/10.1088/1757-899X/879/1/012015

Pangestu, I. (2023). Perancangan Sistem Informasi Manajemen Penyewaan Bus Pariwisata Berbasis Website. *Prosiding Seminar Nasional Teknologi Informasi dan Komunikasi (SENATIK), 6*(1).

Prihandoyo, M. T. (2018). Unified Modeling Language (UML) Model untuk Pengembangan Sistem Informasi Akademik Berbasis Web. *Jurnal Informatika: Jurnal Pengembangan IT, 3*(1), 126-129.

Raihan, H., & Voutama, A. (2023). Pengujian Black Box pada Aplikasi Database Perguruan Tinggi dengan Teknik Equivalence Partition. *Antivirus: Jurnal Ilmiah Teknik Informatika, 17*(1), 1-18. https://doi.org/10.35457/antivirus.v17i1.2501

Ranacher, P., Brunauer, R., van der Spek, S., & Reich, S. (2015). Why GPS makes distances bigger than they are. *International Journal of Geographical Information Science, 30*(2), 316-333.

Santi, P. A. D. A., Afwani, R., Albar, M. A., Anjarwani, S. E., & Mardiansyah, A. Z. (2022). Black Box Testing with Equivalence Partitioning and Boundary Value Analysis Methods (Study Case: Academic Information System of Mataram University). *Advances in Computer Science Research, 102*, 207-219. https://doi.org/10.2991/978-94-6463-084-8_19

Savitri, R. A., & Supriyono, H. (2021). Perancangan Sistem Informasi Penyewaan Bus Pariwisata Berbasis Website pada PT. Hadi Mulyo Raya Sragen. Universitas Muhammadiyah Surakarta.

Wen, H., Li, X., Zhang, L., & Zeng, Y. (2018). Data quality assessment and uncertainty analysis of GPS trajectory data. *ISPRS International Journal of Geo-Information*.

Wulandari, D. A. N., Bahar, A. A. H., Arfananda, M. G., & Apriyani, H. (2021). Prototyping Model in Information System Development of Al-Ruhamaa' Bogor Yatim Center Foundation. *Jurnal Pilar Nusa Mandiri*.

## Catatan Penggantian

Bagian lama yang sebaiknya dihapus atau tidak digunakan:

1. Simulasi GPS Tracking, karena program menggunakan lokasi perangkat driver, bukan simulasi GPS utama.
2. WebSocket, karena program mengirim lokasi menggunakan request API, bukan komunikasi WebSocket.
3. Haversine Formula, jika sistem tidak menampilkan perhitungan jarak khusus sebagai fitur utama.
4. Geofencing, jika sistem tidak memiliki fitur batas area atau notifikasi masuk/keluar wilayah.
5. Tailwind CSS, karena tampilan program lebih dominan menggunakan Vue.js dan Vuetify.
6. Lighthouse, karena pengujian Lighthouse tidak digunakan.
7. Midtrans atau Payment Gateway, jika sistem belum menerapkan pembayaran online.
