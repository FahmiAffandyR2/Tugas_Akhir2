# Uji Unit Testing

Unit testing dilakukan untuk memastikan setiap komponen utama pada sistem dapat berjalan sesuai fungsi yang diharapkan secara terpisah. Pengujian ini difokuskan pada komponen backend yang mendukung fitur autentikasi admin dan live tracking GPS, yaitu model user, model log GPS, serta event realtime `TripPositionUpdated`.

Pengujian dilakukan menggunakan PHPUnit pada Laravel. Setiap test case dibuat untuk memeriksa logika kecil yang tidak membutuhkan koneksi database, sehingga pengujian dapat berjalan lebih cepat dan aman tanpa mengubah data aplikasi.

| No | Nama Pengujian | Komponen yang Diuji | Skenario Pengujian | Hasil yang Diharapkan |
|---|---|---|---|---|
| 1 | Validasi role admin | `User::isAdmin()` | User memiliki role `0` | Sistem mengenali user sebagai admin |
| 2 | Validasi role customer | `User::isAdmin()` | User memiliki role `1` | Sistem tidak mengenali user sebagai admin |
| 3 | Validasi role driver | `User::isAdmin()` | User memiliki role `2` | Sistem tidak mengenali user sebagai admin |
| 4 | Validasi channel realtime GPS | `TripPositionUpdated::broadcastOn()` | Event dibuat dengan nama channel perjalanan | Event dikirim ke channel yang sesuai |
| 5 | Validasi payload realtime GPS | `TripPositionUpdated::broadcastWith()` | Event membawa data latitude, longitude, dan speed | Payload event berisi data GPS yang benar |
| 6 | Validasi field GPS tracking | `GpsTrackingLog` | Model dibuat dan daftar fillable diperiksa | Field GPS seperti driver, bus, trip, latitude, longitude, speed, dan recorded_at dapat diisi |
| 7 | Validasi casting GPS tracking | `GpsTrackingLog` | Tipe data koordinat dan waktu diperiksa | Latitude, longitude, speed, heading, dan accuracy bertipe double, sedangkan recorded_at bertipe datetime |

Perintah untuk menjalankan unit testing:

```bash
php artisan test --testsuite=Unit
```

File unit test yang dibuat:

- `tests/Unit/UserRoleTest.php`
- `tests/Unit/TripPositionUpdatedTest.php`
- `tests/Unit/GpsTrackingLogTest.php`

Dengan adanya unit testing ini, sistem dapat memastikan bahwa logika dasar hak akses admin, struktur data GPS, dan mekanisme pengiriman event realtime berjalan sesuai kebutuhan sebelum dilakukan pengujian pada level integrasi atau sistem.
