# ERD EZBus Admin Panel

Dokumen ini dibuat dari migration database proyek pada 29 Juli 2026. Diagram menggunakan [Mermaid ER Diagram](https://mermaid.js.org/syntax/entityRelationshipDiagram.html), sehingga dapat dirender di GitHub, GitLab, atau Mermaid Live Editor.

## 1. Inti charter, armada, pendapatan, dan tracking

```mermaid
erDiagram
    USERS {
        int id PK
        string name
        string email
        int role "0 admin | 1 customer | 2 driver"
        int status_id FK
    }

    FLEET_DEPOTS {
        int id PK
        string name
        string city
        decimal latitude
        decimal longitude
        boolean is_active
    }

    BUSES {
        int id PK
        string license
        int capacity
        int driver_id FK "driver utama, opsional"
        int depot_id FK
    }

    CHARTER_BOOKINGS {
        bigint id PK
        string reference_code UK
        int customer_id FK
        string origin
        string destination
        date departure_date
        time departure_time
        date return_date
        time return_time
        int passenger_count
        string bus_type
        decimal quoted_price
        string status
        string payment_status
        int bus_id FK
        int driver_id FK
        int depot_id FK
        int operational_planned_trip_id FK
    }

    CHARTER_REVENUE_TRANSACTIONS {
        bigint id PK
        bigint charter_booking_id FK "unik, satu transaksi per booking"
        decimal amount
        string status
        int verified_by FK
        datetime paid_at
    }

    ROUTES {
        int id PK
        string name
    }

    TRIPS {
        int id PK
        string channel
        int route_id FK
        int driver_id FK
        date effective_date
        int repetition_period
        int status_id FK
    }

    PLANNED_TRIPS {
        int id PK
        string channel
        int trip_id FK
        int route_id FK
        date planned_date
        datetime started_at
        datetime ended_at
        double last_position_lat
        double last_position_lng
        int driver_id FK
        int bus_id FK
    }

    USERS ||--o{ CHARTER_BOOKINGS : "membuat sebagai customer"
    USERS ||--o{ CHARTER_BOOKINGS : "menjalankan sebagai driver"
    FLEET_DEPOTS ||--o{ BUSES : "menjadi lokasi asal"
    FLEET_DEPOTS ||--o{ CHARTER_BOOKINGS : "asal armada"
    BUSES ||--o{ CHARTER_BOOKINGS : "ditugaskan ke"
    USERS ||--o{ BUSES : "driver utama opsional"
    CHARTER_BOOKINGS ||--o| CHARTER_REVENUE_TRANSACTIONS : "dibayar"
    USERS ||--o{ CHARTER_REVENUE_TRANSACTIONS : "memverifikasi"
    CHARTER_BOOKINGS o|--o| PLANNED_TRIPS : "membuat perjalanan operasional"
    ROUTES ||--o{ TRIPS : "memiliki"
    USERS ||--o{ TRIPS : "driver"
    TRIPS ||--o{ PLANNED_TRIPS : "dijadwalkan"
    ROUTES ||--o{ PLANNED_TRIPS : "digunakan"
    BUSES ||--o{ PLANNED_TRIPS : "menjalankan"
    USERS ||--o{ PLANNED_TRIPS : "mengemudikan"
```

Catatan penting: lokasi GPS terakhir tersimpan di `planned_trips.last_position_lat` dan `planned_trips.last_position_lng`. Nilai pendapatan dashboard charter dibaca dari `charter_revenue_transactions` dengan `status = paid`, bukan dari booking yang hanya berstatus disetujui.

## 2. Rute reguler dan reservasi penumpang

```mermaid
erDiagram
    USERS {
        int id PK
        string name
        int role
    }
    ROUTES {
        int id PK
        string name
    }
    STOPS {
        int id PK
        string name
        string place_id
        string address
        string lat
        string lng
    }
    ROUTE_STOPS {
        int id PK
        int route_id FK
        int stop_id FK
        int order
    }
    ROUTE_STOP_DIRECTIONS {
        int id PK
        int route_stop_id FK
        string summary
        int index
        text overview_path
        boolean current
    }
    TRIPS {
        int id PK
        int route_id FK
        int driver_id FK
    }
    TRIP_DETAILS {
        int id PK
        int trip_id FK
        int stop_id FK
        time planned_timestamp
        time actual_timestamp
    }
    PLANNED_TRIPS {
        int id PK
        int trip_id FK
        int route_id FK
    }
    PLANNED_TRIP_DETAILS {
        int id PK
        int planned_trip_id FK
        int stop_id FK
        time planned_timestamp
        time actual_timestamp
    }
    CUSTOMER_RESERVED_TRIPS {
        int id PK
        int user_id FK
        int planned_trip_id FK
        int start_stop_id FK
        int end_stop_id FK
        decimal paid_price
        int ride_status
    }

    ROUTES ||--o{ ROUTE_STOPS : "terdiri dari"
    STOPS ||--o{ ROUTE_STOPS : "dipakai pada"
    ROUTE_STOPS ||--o{ ROUTE_STOP_DIRECTIONS : "memiliki segmen arah"
    ROUTES ||--o{ TRIPS : "template perjalanan"
    TRIPS ||--o{ TRIP_DETAILS : "jadwal halte"
    STOPS ||--o{ TRIP_DETAILS : "halte"
    TRIPS ||--o{ PLANNED_TRIPS : "instans jadwal"
    PLANNED_TRIPS ||--o{ PLANNED_TRIP_DETAILS : "detail aktual"
    STOPS ||--o{ PLANNED_TRIP_DETAILS : "halte"
    USERS ||--o{ CUSTOMER_RESERVED_TRIPS : "memesan"
    PLANNED_TRIPS ||--o{ CUSTOMER_RESERVED_TRIPS : "dipesan pada"
    STOPS ||--o{ CUSTOMER_RESERVED_TRIPS : "titik naik/turun"
```

## 3. Akun, notifikasi, dan keuangan reguler

```mermaid
erDiagram
    STATUSES {
        int id PK
        string name
    }
    USERS {
        int id PK
        int status_id FK
    }
    USER_STATUSES {
        int id PK
        int user_id FK
        int status_id FK
    }
    NOTIFICATIONS {
        int id PK
        int user_id FK
        string message
        boolean seen
    }
    PLACES {
        int id PK
        int user_id FK
        string name
        decimal latitude
        decimal longitude
    }
    USER_PAYMENTS {
        int id PK
        int user_id FK
        int reservation_id FK
        decimal amount
    }
    USER_REFUNDS {
        int id PK
        int user_id FK
        int reservation_id FK
        decimal amount
    }
    USER_CHARGES {
        int id PK
        int user_id FK
        decimal amount
    }
    REDEMPTIONS {
        int id PK
        int user_id FK
        int redemption_type_id FK
    }
    CUSTOMER_RESERVED_TRIPS {
        int id PK
        int user_id FK
        int planned_trip_id FK
    }

    STATUSES ||--o{ USERS : "status akun"
    USERS ||--o{ USER_STATUSES : "riwayat status"
    STATUSES ||--o{ USER_STATUSES : "status"
    USERS ||--o{ NOTIFICATIONS : "menerima"
    USERS ||--o{ PLACES : "menyimpan lokasi"
    USERS ||--o{ USER_PAYMENTS : "menerima pembayaran"
    CUSTOMER_RESERVED_TRIPS ||--o{ USER_PAYMENTS : "dasar pembayaran"
    USERS ||--o{ USER_REFUNDS : "menerima refund"
    CUSTOMER_RESERVED_TRIPS ||--o{ USER_REFUNDS : "dasar refund"
    USERS ||--o{ USER_CHARGES : "mengisi saldo"
    USERS ||--o{ REDEMPTIONS : "mencairkan dana"
```

## Ringkasan alur charter

```text
Customer (users)
  -> charter_bookings
  -> pembayaran diverifikasi
  -> charter_revenue_transactions
  -> admin menetapkan bus + driver + depo
  -> planned_trips
  -> GPS driver tersimpan dan dikirim ke Live Tracking
```
