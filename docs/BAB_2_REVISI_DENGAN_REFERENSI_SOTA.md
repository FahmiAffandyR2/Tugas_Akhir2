# BAB 2
# TINJAUAN PUSTAKA

Bab ini membahas teori dan konsep yang mendukung penelitian mengenai sistem booking dan monitoring bus pariwisata berbasis website. Pembahasan pada bab ini disesuaikan dengan penelitian terdahulu yang telah dijelaskan pada bagian State of the Art, yaitu mengenai fleet management system, GPS tracking, sistem tracking kendaraan berbasis web, Progressive Web App, serta pengelolaan data operasional armada.

## 2.1 Sistem Informasi

Sistem informasi merupakan kumpulan komponen yang saling berhubungan untuk mengumpulkan, memproses, menyimpan, dan menyajikan informasi. Sistem informasi digunakan untuk membantu kegiatan operasional, pengelolaan data, serta pengambilan keputusan dalam suatu organisasi. Dalam konteks transportasi, sistem informasi berperan penting untuk mengelola data kendaraan, data pengguna, jadwal perjalanan, serta informasi operasional secara terpusat.

Pada penelitian ini, sistem informasi diterapkan dalam bentuk website yang digunakan oleh admin, driver, dan customer. Admin dapat mengelola data operasional, driver dapat melihat jadwal serta memperbarui status perjalanan, sedangkan customer dapat melakukan pemesanan bus dan melihat status pemesanan. Penggunaan sistem informasi pada bidang transportasi sejalan dengan penelitian Farahpoor et al. yang menjelaskan bahwa sistem fleet management berbasis IoT dapat mengintegrasikan data kendaraan secara real-time untuk mendukung pengambilan keputusan dalam pengelolaan armada (Farahpoor et al., 2024).

## 2.2 Sistem Informasi Manajemen

Sistem informasi manajemen adalah sistem yang digunakan untuk membantu proses pengelolaan data dan penyajian informasi bagi pihak manajemen. Sistem ini dapat menghasilkan informasi yang dibutuhkan untuk mengawasi kegiatan operasional, melakukan evaluasi, serta mengambil keputusan berdasarkan data yang tersedia.

Pada sistem booking bus pariwisata, sistem informasi manajemen diterapkan pada sisi admin. Admin dapat mengelola data customer, driver, bus, depo armada, rute, titik berhenti, jadwal perjalanan, booking bus, complaints, dan driver conflicts. Selain itu, admin juga dapat memantau perjalanan aktif melalui fitur live tracking. Penelitian Uday dan Prasad menunjukkan bahwa fleet management system dengan live GPS tracking dapat membantu pengguna dalam memantau lokasi kendaraan, mengelola data kendaraan, dan melihat laporan operasional melalui aplikasi berbasis web (Uday & Prasad, 2022).

## 2.3 Booking Bus Pariwisata

Booking bus pariwisata merupakan proses pemesanan bus yang dilakukan oleh customer untuk kebutuhan perjalanan rombongan. Proses ini dapat mencakup pengisian data perjalanan, pemilihan jenis bus, pengiriman permintaan, pemeriksaan oleh admin, pemberian penawaran harga, serta penetapan armada dan driver.

Dalam sistem yang dikembangkan, customer dapat melakukan pemesanan bus melalui halaman pesan bus. Customer mengisi informasi perjalanan seperti rute, tanggal keberangkatan, jumlah peserta, catatan perjalanan, dan jenis bus yang diinginkan. Setelah permintaan dikirim, admin memeriksa data tersebut dan memberikan penawaran harga. Dengan adanya fitur booking, proses pemesanan menjadi lebih terstruktur dan dapat tercatat dalam sistem.

## 2.4 Fleet Management System

Fleet Management System adalah sistem yang digunakan untuk mengelola armada kendaraan agar operasional dapat berjalan lebih efektif. Sistem ini dapat mencakup pengelolaan data kendaraan, pengemudi, jadwal perjalanan, status kendaraan, lokasi kendaraan, serta laporan operasional.

Pada penelitian ini, konsep fleet management diterapkan melalui fitur admin yang mengelola data bus, driver, rute, titik berhenti, depo armada, jadwal perjalanan, serta live tracking. Farahpoor et al. menjelaskan bahwa fleet management system yang terintegrasi dapat mengumpulkan, menyimpan, dan menganalisis data kendaraan untuk mendukung efisiensi operasional dan pengambilan keputusan (Farahpoor et al., 2024). Oleh karena itu, sistem yang dikembangkan tidak hanya berfungsi sebagai aplikasi booking, tetapi juga sebagai sistem pendukung operasional armada.

## 2.5 GPS Tracking

GPS Tracking merupakan teknologi yang digunakan untuk mengetahui posisi kendaraan atau perangkat berdasarkan koordinat lokasi. Dalam bidang transportasi, GPS tracking digunakan untuk memantau posisi kendaraan, mengetahui pergerakan armada, dan mendukung pengawasan perjalanan.

Pada sistem ini, GPS tracking digunakan saat driver menjalankan perjalanan aktif. Ketika perjalanan dimulai, sistem mengambil lokasi perangkat driver dan mengirimkan koordinat tersebut ke server. Data lokasi kemudian dapat dipantau oleh admin melalui halaman live tracking. Moumen et al. menjelaskan bahwa sistem GPS tracking real-time dapat mengintegrasikan data lokasi kendaraan dengan server, database, dan web interface sehingga posisi kendaraan dapat ditampilkan secara langsung kepada pengguna (Moumen et al., 2023).

## 2.6 Live Tracking

Live tracking adalah fitur pemantauan posisi kendaraan secara langsung atau berkala. Fitur ini membantu pihak pengelola mengetahui posisi kendaraan ketika perjalanan sedang berlangsung. Dalam sistem transportasi, live tracking dapat membantu pengawasan perjalanan, koordinasi operasional, dan peningkatan transparansi informasi.

Dalam sistem booking bus pariwisata, live tracking digunakan oleh admin untuk memantau posisi driver atau bus saat perjalanan aktif. Driver mengirimkan lokasi melalui perangkat yang digunakan, kemudian sistem menampilkan posisi tersebut pada peta. Penelitian Uday dan Prasad menekankan bahwa live GPS tracking pada fleet management system memungkinkan pengguna memantau lokasi kendaraan dan mengelola laporan operasional melalui sistem berbasis web (Uday & Prasad, 2022). Dengan demikian, fitur live tracking pada sistem ini mendukung pengawasan armada setelah jadwal perjalanan dijalankan.

## 2.7 Progressive Web App

Progressive Web App atau PWA adalah teknologi web yang memungkinkan website memiliki pengalaman penggunaan seperti aplikasi mobile. PWA dapat mendukung tampilan responsif, akses melalui browser, pemasangan pada perangkat, serta performa yang lebih baik melalui mekanisme caching dan service worker.

Pada sistem ini, konsep PWA relevan karena sistem dapat diakses oleh driver dan customer melalui browser tanpa harus menginstal aplikasi native. Hal ini mempermudah penggunaan, terutama bagi customer yang mungkin hanya menggunakan layanan untuk kebutuhan perjalanan tertentu. Alim et al. menjelaskan bahwa penerapan PWA dapat meningkatkan performa website, mendukung akses mobile, serta memberikan pengalaman pengguna yang menyerupai aplikasi native (Alim et al., 2024).

## 2.8 Antarmuka Website

Antarmuka website merupakan bagian yang berhubungan langsung dengan pengguna. Antarmuka yang baik perlu memperhatikan kemudahan penggunaan, kejelasan informasi, konsistensi tampilan, dan responsivitas pada berbagai perangkat.

Pada sistem booking bus pariwisata, antarmuka dibuat untuk tiga jenis pengguna, yaitu admin, driver, dan customer. Customer menggunakan antarmuka untuk registrasi, login, pesan bus, melihat pemesanan, dan mengelola profil. Driver menggunakan antarmuka untuk melihat jadwal, memulai perjalanan, mengirim lokasi, menyelesaikan perjalanan, dan melihat riwayat. Admin menggunakan antarmuka untuk mengelola data master, booking, live tracking, dan complaints. Penelitian Amalia menunjukkan bahwa aplikasi tracking kendaraan berbasis web membutuhkan dashboard interaktif agar pengguna dapat memantau posisi kendaraan dengan lebih mudah (Amalia, 2025).

## 2.9 Database

Database adalah tempat penyimpanan data yang digunakan agar data dapat dikelola, dicari, diperbarui, dan digunakan kembali. Database sangat penting dalam aplikasi berbasis website karena hampir seluruh proses sistem membutuhkan penyimpanan data yang terstruktur.

Dalam sistem ini, database digunakan untuk menyimpan data user, customer, driver, bus, depo armada, rute, titik berhenti, jadwal perjalanan, booking bus, complaints, dan lokasi perjalanan. Data tersebut saling berhubungan sehingga sistem dapat menampilkan informasi sesuai kebutuhan masing-masing aktor. Pada penelitian Moumen et al., data lokasi kendaraan juga diintegrasikan dengan database berbasis cloud agar dapat ditampilkan pada web interface secara real-time (Moumen et al., 2023).

## 2.10 Laravel

Laravel adalah framework PHP yang digunakan untuk membangun aplikasi web. Laravel mendukung pengembangan backend melalui routing, controller, model, middleware, autentikasi, validasi, dan pengelolaan database. Struktur Laravel membantu pengembangan sistem menjadi lebih rapi dan mudah dipelihara.

Pada penelitian ini, Laravel digunakan pada sisi backend untuk mengelola proses bisnis sistem. Backend menangani proses registrasi, login, pengelolaan data master, pemesanan bus, penjadwalan perjalanan, pengelolaan status booking, serta penyimpanan lokasi driver saat perjalanan aktif.

## 2.11 Vue.js dan Vuetify

Vue.js adalah framework JavaScript yang digunakan untuk membangun antarmuka pengguna berbasis komponen. Vuetify adalah framework komponen UI untuk Vue.js yang menyediakan berbagai komponen siap pakai seperti form, table, card, dialog, navigation drawer, dan button.

Pada sistem ini, Vue.js dan Vuetify digunakan untuk membangun tampilan frontend. Halaman login, registrasi, dashboard admin, halaman driver, halaman customer, pemesanan bus, dan live tracking dibuat agar pengguna dapat berinteraksi dengan sistem secara lebih mudah. Penggunaan antarmuka berbasis web yang interaktif mendukung kebutuhan sistem monitoring sebagaimana dijelaskan oleh Amalia bahwa dashboard interaktif dapat memudahkan pengguna dalam memantau kendaraan (Amalia, 2025).

## 2.12 Axios

Axios adalah library JavaScript yang digunakan untuk melakukan komunikasi HTTP antara frontend dan backend. Axios dapat digunakan untuk mengambil data, mengirim data, memperbarui data, dan menghapus data melalui API.

Dalam sistem ini, Axios digunakan oleh frontend untuk berkomunikasi dengan backend Laravel. Contohnya adalah ketika user melakukan login, customer mengirim data booking, admin mengambil daftar data, driver mengambil jadwal perjalanan, dan driver mengirim data lokasi ke server.

## 2.13 Leaflet

Leaflet adalah library JavaScript yang digunakan untuk menampilkan peta interaktif pada website. Leaflet dapat menampilkan marker, polyline, dan informasi lokasi pada peta.

Pada sistem booking bus pariwisata, Leaflet digunakan untuk mendukung fitur live tracking. Posisi driver atau bus dapat ditampilkan pada peta sehingga admin dapat memantau perjalanan yang sedang berlangsung. Penggunaan peta pada sistem tracking kendaraan sejalan dengan penelitian Moumen et al. yang menggunakan web interface untuk menampilkan data lokasi kendaraan secara langsung (Moumen et al., 2023).

## 2.14 Metode Prototype Pressman

Metode Prototype menurut Pressman merupakan salah satu model pengembangan perangkat lunak yang digunakan ketika kebutuhan pengguna belum sepenuhnya jelas pada tahap awal. Model ini memungkinkan pengembang membuat gambaran awal sistem dalam bentuk prototype, kemudian prototype tersebut dievaluasi oleh pengguna untuk memperoleh masukan. Hasil evaluasi digunakan sebagai dasar perbaikan hingga sistem yang dikembangkan semakin sesuai dengan kebutuhan pengguna (Pressman, 2019).

Pressman menjelaskan bahwa tahapan dalam model Prototype meliputi communication, quick plan, modeling quick design, construction of prototype, dan deployment delivery and feedback. Tahap communication dilakukan untuk mengumpulkan kebutuhan pengguna. Tahap quick plan digunakan untuk menyusun rencana awal pengembangan. Tahap modeling quick design menghasilkan rancangan awal antarmuka dan alur sistem. Tahap construction of prototype merupakan proses pembuatan prototype, sedangkan tahap deployment delivery and feedback dilakukan dengan menyerahkan prototype kepada pengguna untuk dievaluasi dan diberi masukan (Pressman, 2019).

Dalam penelitian ini, metode Prototype Pressman digunakan karena sistem memiliki beberapa aktor dan fitur yang saling berhubungan, yaitu admin, driver, dan customer. Melalui prototype, rancangan halaman dan alur kerja sistem dapat dievaluasi sebelum sistem dikembangkan secara lebih lengkap. Metode ini sesuai digunakan pada pengembangan sistem booking dan monitoring bus pariwisata karena pengguna dapat melihat bentuk awal sistem, memberikan masukan terhadap alur pemesanan, dashboard, jadwal driver, dan live tracking, kemudian sistem diperbaiki berdasarkan hasil evaluasi tersebut.

## 2.15 Unified Modeling Language

Unified Modeling Language atau UML adalah bahasa pemodelan yang digunakan untuk menggambarkan rancangan sistem secara visual. UML membantu menjelaskan aktor, proses, interaksi, dan struktur sistem sebelum masuk ke tahap implementasi.

Dalam penelitian ini, UML digunakan untuk menggambarkan kebutuhan dan rancangan sistem booking bus pariwisata. Diagram yang digunakan dapat meliputi Use Case Diagram, Activity Diagram, Sequence Diagram, dan Class Diagram.

## 2.16 Data Flow Diagram

Data Flow Diagram atau DFD adalah diagram yang digunakan untuk menggambarkan aliran data dalam sistem. DFD menunjukkan proses yang terjadi, data yang masuk, data yang keluar, penyimpanan data, serta pihak luar yang berinteraksi dengan sistem.

Pada sistem booking bus pariwisata, DFD digunakan untuk menjelaskan alur data antara customer, driver, admin, dan sistem. Customer mengirim data registrasi dan booking, driver mengirim data perjalanan dan lokasi, sedangkan admin mengelola data master serta memantau booking dan live tracking.

## 2.17 Entity Relationship Diagram

Entity Relationship Diagram atau ERD adalah diagram yang digunakan untuk menggambarkan struktur basis data. ERD menunjukkan entitas, atribut, primary key, foreign key, serta relasi antar data.

Dalam penelitian ini, ERD digunakan untuk merancang struktur database sistem. Entitas yang digunakan meliputi users, buses, fleet_depots, routes, stops, trips, planned_trips, charter_bookings, complaints, dan entitas lain yang mendukung proses booking serta operasional perjalanan.

## 2.18 Black Box Testing

Black Box Testing adalah metode pengujian perangkat lunak yang berfokus pada fungsi sistem tanpa melihat struktur kode program. Pengujian dilakukan dengan memberikan input tertentu dan memeriksa apakah output yang dihasilkan sudah sesuai dengan kebutuhan.

Pada sistem ini, Black Box Testing digunakan untuk menguji fitur utama seperti registrasi, login, pengelolaan data admin, pemesanan bus customer, penjadwalan perjalanan, perjalanan aktif driver, live tracking, complaints, dan pengelolaan profil. Pengujian ini bertujuan untuk memastikan setiap fitur berjalan sesuai dengan hasil yang diharapkan.

## Referensi State of the Art yang Digunakan

Alim, A. R., et al. (2024). The Implementation of PWA (Progressive Web App) Technology in Enhancing Website Performance & Mobile Accessibility. *Prosiding Penelitian Teknologi Informasi*.

Amalia, E. (2025). Designing a Web-Based Vehicle Tracking Application. *Indonesian Journal of Computer Science*.

Farahpoor, M., et al. (2024). Comprehensive IoT-Driven Fleet Management System for Industrial Vehicles. *IEEE Access*.

Moumen, I., Rafalia, N., Abouchabaka, J., & Aoufi, M. (2023). Real-time GPS Tracking System for IoT-Enabled Connected Vehicles. *E3S Web of Conferences*.

Pressman, R. S. (2019). *Software Engineering: A Practitioner's Approach*. McGraw-Hill.

Uday, D., & Prasad, P. K. (2022). Fleet Management System with Live GPS Tracking. *International Journal of Engineering Technology and Management Sciences*.

## Catatan

Karena State of the Art pada BAB 1 masih banyak membahas PWA, GPS tracking, WebSocket, dan fleet management, sedangkan program terbaru sudah memiliki fitur booking customer, maka BAB 1 sebaiknya juga ikut disesuaikan. Jika BAB 1 tetap memakai State of the Art lama, BAB 2 dapat menggunakan referensi di atas, tetapi bagian booking bus masih membutuhkan tambahan jurnal khusus tentang sistem penyewaan atau booking bus berbasis website.
