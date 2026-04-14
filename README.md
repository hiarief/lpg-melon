# 🔥 Aplikasi Rekap Gas LPG 3KG — Laravel 11

## Fitur Utama
- **Manajemen Periode** — Generate periode bulanan, cutoff, carry-over otomatis
- **DO Agen** — Input DO per pangkalan per tanggal, grid rekap bulanan, status lunas/belum
- **Distribusi Harian** — Input bulk sehari banyak customer, harga per baris, status bayar (lunas/tunda/sebagian)
- **Cashflow Harian** — Grid pengeluaran per kategori per hari (bensin, rokok, sopir, dll)
- **Transfer & Setoran** — Setoran kurir → penampung, transfer penampung → rek utama, alokasi ke DO, riwayat mutasi
- **Ringkasan** — Dashboard lengkap: stok, penjualan, margin, cashflow, piutang DO, gaji kurir, kontrak pangkalan
- **Piutang External** — Catat pinjaman modal masuk/keluar
- **Master Data** — CRUD pangkalan, customer, kurir
- **Kontrak Khusus** — Sukmedi (1000×DO/bulan), Angga (flat 600rb/bulan), harga distribusi 18rb, setoran bisa ditunda

---

## Persyaratan
- PHP >= 8.2
- Composer
- MySQL 8.0 / MariaDB 10.6+
- Node.js (opsional, hanya jika ingin build assets lokal)

---

## Langkah Instalasi

### 1. Buat project Laravel baru
```bash
composer create-project laravel/laravel lpg-app
cd lpg-app
```

### 2. Salin semua file dari paket ini
Salin semua file ke direktori project Laravel sesuai path masing-masing:
```
app/Models/           → semua model
app/Http/Controllers/ → semua controller
database/migrations/  → semua migrasi
database/seeders/     → seeder
resources/views/      → semua blade views
routes/web.php        → route
```

### 3. Setup environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lpg_rekap
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 4. Buat database
```sql
CREATE DATABASE lpg_rekap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Jalankan migrasi & seeder
```bash
php artisan migrate
php artisan db:seed
```

Seeder akan membuat data awal:
- 4 Pangkalan: Angga (flat 600rb), Arief Hidayat, Fahrul Rozhi, Sukmedi (per DO ×1000)
- 16 Customer (Angga & Sukmedi sebagai kontrak, sisanya regular)
- 1 Kurir: Epul (500/tabung)

### 6. Jalankan server
```bash
php artisan serve
```

Buka browser: http://localhost:8000

---

## Cara Pakai (Alur Kerja Bulanan)

### A. Awal Bulan — Buat Periode
1. Klik **📅 Periode** → **Buat Periode Baru**
2. Isi bulan & tahun
3. Isi saldo awal (dari penutupan bulan lalu):
   - Stok tabung sisa
   - Saldo kas fisik
   - Saldo rekening penampung
   - Piutang external (jika ada)
   - Tabung DO bulan lalu yang belum lunas

### B. Harian — Input DO
1. Klik **📦 DO Agen** → **+ Input DO**
2. Pilih pangkalan, isi tanggal + qty + harga (default 16000)
3. DO otomatis masuk rekap grid

### C. Harian — Input Distribusi
1. Klik **🚚 Distribusi** → **+ Input Harian Bulk**
2. Pilih tanggal, kurir (Epul)
3. Tambah baris per customer:
   - Qty, harga/tabung (18000-20000)
   - Status bayar: Lunas / Tunda / Sebagian
4. Untuk Angga & Sukmedi (★): pilih status **Tunda** jika belum bayar
5. Klik **Simpan Semua**

### D. Harian — Catat Setoran Kurir
1. Klik **🏦 Transfer** → Tab **Setoran Kurir**
2. Input nominal setoran kurir ke rekening penampung (+ biaya admin jika ada)

### E. Harian/Berkala — Input Pengeluaran
1. Klik **💸 Cashflow**
2. Input pengeluaran: bensin, rokok, sopir, oli, dll per tanggal

### F. Berkala — Transfer Penampung ke Rek Utama
1. Klik **🏦 Transfer** → Tab **Transfer ke Rek Utama**
2. Input nominal transfer
3. Alokasikan ke DO mana yang dilunasi (klik **+ Tambah DO**)
4. Klik **Simpan Transfer & Update Status DO**
5. Status DO otomatis terupdate (lunas/sebagian)

### G. Akhir Bulan — Bayar Kontrak & Gaji
1. Klik **📊 Ringkasan** → Bagian **Piutang & Kewajiban**
2. Klik **↻ Recalculate Kontrak** untuk update nilai Sukmedi (1000×total DO)
3. Tandai kontrak Angga & Sukmedi sebagai **Lunas**
4. Gaji Epul otomatis terhitung di Ringkasan

### H. Tutup Periode
1. Klik **📅 Periode** → klik **Tutup** pada periode berjalan
2. Saldo akhir akan dicatat untuk dijadikan saldo awal bulan berikutnya

---

## Catatan Penting

### Kontrak Khusus
| Pihak   | Tipe        | Tarif          | Harga Distribusi | Setoran        |
|---------|-------------|----------------|-----------------|----------------|
| Sukmedi | Per DO      | 1000×total DO  | 18.000/tabung   | Bisa ditunda   |
| Angga   | Flat Bulanan| 600.000/bulan  | 18.000/tabung   | Bisa ditunda   |

### Alur Pembayaran DO
```
Penjualan Gas → Kurir (Epul) → Rekening Penampung → Rekening Utama → Lunasi DO Agen
```

### Piutang DO Carry-Over
- DO yang belum lunas di bulan sebelumnya tetap muncul di halaman Transfer
- Bisa dialokasikan di bulan berikutnya

### Catatan `net_amount` di CourierDeposit
Jika MySQL Anda tidak support `storedAs` (generated column), ganti baris tersebut di migration:
```php
// Ganti ini:
$table->bigInteger('net_amount')->storedAs('amount - admin_fee');
// Dengan ini:
$table->bigInteger('net_amount')->default(0);
```
Dan update di controller:
```php
CourierDeposit::create([...] + ['net_amount' => $request->amount - ($request->admin_fee ?? 0)]);
```

---

## Struktur Database

```
periods               → periode bulanan
outlets               → 4 pangkalan (Angga, Arief, Fahrul, Sukmedi)
customers             → customer distribusi harian
couriers              → kurir (Epul)
delivery_orders       → DO dari agen per pangkalan
distributions         → distribusi harian kurir ke customer
daily_expenses        → pengeluaran operasional harian
courier_deposits      → setoran kurir ke rekening penampung
account_transfers     → transfer penampung ke rekening utama
account_transfer_do   → pivot: transfer ↔ DO (alokasi pembayaran)
external_debts        → piutang/modal pihak luar
outlet_contract_payments → pembayaran kontrak pangkalan per periode
```
