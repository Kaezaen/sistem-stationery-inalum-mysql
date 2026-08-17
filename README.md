# Sistem Stationery — PT Indonesia Asahan Aluminium (Inalum)

Aplikasi internal untuk **standarisasi pengajuan dan verifikasi pembelian Alat Tulis
Kantor (ATK)**. Inti alurnya: *request → approval 3 level → serah terima → stok berkurang*,
dilengkapi modul pembelian, 8 laporan + dashboard, notifikasi in-app/email, dan audit trail.

Prinsip yang dijaga sistem:

- **Ledger adalah sumber kebenaran stok.** `inventory_transactions` bersifat *append-only*;
  `items.stock_quantity` hanyalah cache yang selalu bisa direkonstruksi dari ledger. Hanya
  satu kelas (`StockService`) yang boleh menulis stok.
- **Stok hanya bergerak di dua titik:** MASUK saat pembelian **diverifikasi**, KELUAR saat
  barang **diserahkan** — bukan saat diinput atau disetujui.
- **Otorisasi berlapis dua:** *Permission* (boleh akses fitur?) + *Policy* (boleh atas
  dokumen INI, dalam status INI?).
- **Semua nilai uang** `DECIMAL(18,2)`, tidak pernah `float`. Data historis dipertahankan penuh.

---

## Daftar Isi

1. [Tech Stack](#tech-stack)
2. [Prasyarat](#prasyarat)
3. [Instalasi](#instalasi)
4. [Menjalankan Aplikasi](#menjalankan-aplikasi)
5. [Alur Request & Peran](#alur-request--peran)
6. [Akun Demo](#akun-demo)
7. [Struktur Modul](#struktur-modul)
8. [Perintah Operasional Stok](#perintah-operasional-stok)
9. [Notifikasi & Queue](#notifikasi--queue)
10. [Laporan & Dashboard](#laporan--dashboard)
11. [Pengujian & Kualitas Kode](#pengujian--kualitas-kode)
12. [Troubleshooting](#troubleshooting)

---

## Tech Stack

| Lapis | Teknologi |
|---|---|
| Backend | Laravel 12 · PHP 8.4 |
| Frontend | React 19 · TypeScript · InertiaJS 2 |
| UI | TailwindCSS 4 · shadcn/ui · Poppins · motion (animasi) |
| Database | **MySQL 8.0.16+** (InnoDB, `utf8mb4`) |
| Auth | Laravel Auth + RBAC (`spatie/laravel-permission`) |
| Arsitektur | Monolith Modular · Service Layer |
| Deployment | Nginx + PHP-FPM + Supervisor + Cron (tanpa Docker) |

---

## Prasyarat

- **PHP 8.4** dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `intl`, `bcmath`, `fileinfo`, `zip`.
- **MySQL 8.0.16 atau lebih baru** — **wajib**. Seluruh "jaring pengaman database" bergantung
  pada `CHECK constraint` yang **ditegakkan**. MySQL < 8.0.16 dan sebagian MariaDB
  *mem-parse tapi mengabaikan* `CHECK`, sehingga data rusak dapat tersimpan diam-diam.
  Gunakan engine **InnoDB** (default) dan charset **`utf8mb4`**.
- **Node.js 20+** dan **npm**.
- **Composer 2**.

Verifikasi versi server sebelum lanjut:

```bash
mysql -u root -p -e "SELECT VERSION();"   # harus >= 8.0.16
```

---

## Instalasi

```bash
# 1. Dependensi
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
```

Buat dua database (aplikasi + pengujian), lalu isi kredensialnya di `.env`:

```bash
mysql -u root -p -e "CREATE DATABASE taajri_stationery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE DATABASE taajri_stationery_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

`.env` (bagian database):

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taajri_stationery
DB_USERNAME=root
DB_PASSWORD=
```

Jalankan migrasi + seeder, lalu isi saldo awal stok:

```bash
php artisan migrate --seed
php artisan stock:seed-initial --reason="Saldo awal"
php artisan stock:snapshot --current
```

`migrate --seed` membuat role & permission, departemen dasar, **katalog 236 item Inalum**
(dari `database/data/stationery-items.csv`), dan — di luar produksi — **organisasi karyawan**
(`EmployeeSeeder`): seluruh karyawan sebagai requester + 5 akun approver. Kata sandi: `password`.

> **Data karyawan** dibaca dari `database/data/employees.csv` (namecode, nama, seksi). Berkas itu
> memuat PII sehingga **tidak di-commit** (gitignored); seeder melewatinya dengan aman bila tak
> ada, dan tetap membuat akun approver.

> **Catatan zona waktu.** Aplikasi berjalan pada UTC dan koneksi MySQL dipaksa ke `+00:00`
> (`config/database.php`). Timestamp disimpan UTC; konversi tampilan ke Asia/Jakarta
> dilakukan di aplikasi.

---

## Menjalankan Aplikasi

```bash
composer dev
```

Menjalankan server, **queue worker**, log viewer, dan Vite sekaligus. Buka
`http://localhost:8000`.

### Windows (bila `composer dev` gagal)

`composer dev` memakai `concurrently` yang kadang bermasalah di Windows. Jalankan tiap
proses di **terminal terpisah** — paling sederhana pakai aset hasil build:

```bash
npm run build
php artisan serve
php artisan queue:work
```

**`queue:work` wajib berjalan** agar notifikasi in-app muncul. Untuk pengembangan frontend
dengan hot-reload, ganti `npm run build` dengan `npm run dev`.

---

## Alur Request & Peran

**Alur terpadu** — satu alur untuk seluruh seksi; ketiga approver berbasis **role global**:

```
Requester  →  Pimpinan SIT (L1)  →  PIC Stationery (L2)  →  Pimpinan SGA (L3)  →  PIC Gudang
 buat &         setujui / tolak       tetapkan QTY ACTUAL      setujui / tolak      serah terima
 ajukan        (role global)         + remark (kuantitatif)   (final)              → stok berkurang
```

| # | Tahap | Pelaku | Aksi | Status setelahnya |
|---|---|---|---|---|
| 1 | Buat & ajukan | Requester (karyawan mana pun) | Pilih item + qty, submit | `Pending Approval Pimpinan` |
| 2 | Approval L1 | **Pimpinan SIT** (role global, seluruh seksi) | Setujui / Tolak + alasan | `Pending Approval PIC Stationery` / `Ditolak Pimpinan` |
| 3 | Approval L2 | **PIC Stationery** | **Tetapkan qty per baris** (≤ diminta) + remark, atau tolak seluruhnya | `Pending Approval Pimpinan SGA` / `Ditolak PIC Stationery` |
| 4 | Approval L3 | **Pimpinan SGA** (berbasis role, global) | Setujui / Tolak (qty read-only) | `Pengambilan Item` / `Ditolak Pimpinan SGA` |
| 5 | Serah terima | **PIC Gudang** | Serahkan (boleh sebagian bila stok kurang) | `Selesai` (stok berkurang) |

**Detail penting:**

- **Approval L1 berbasis role global** (**Pimpinan SIT**), berlaku untuk seluruh seksi —
  bukan atasan langsung. Karena itu **karyawan tidak perlu punya atasan** (`manager_id`) untuk
  mengajukan. L2 (PIC Stationery) dan L3 (Pimpinan SGA) juga berbasis role. Akun `admin`
  mengelola sistem, bukan aktor pengajuan request.
- **Approval L2 bersifat kuantitatif** — PIC Stationery mengubah `quantity` per baris (hanya
  boleh mengurangi), bukan sekadar memindahkan status.
- **Reservasi (ADR-07).** Saat L2 menyetujui, stok **direservasi** (`reserved_quantity`
  bertambah; `stock_quantity` tidak berubah) hingga barang diserahkan atau request
  dibatalkan/ditolak SGA. Stok tersedia = `stock_quantity − reserved_quantity`.
- **Tiga titik penolakan → tiga tujuan revisi:** ditolak Pimpinan SIT → **requester**
  revisi; ditolak PIC Stationery → **final** (buat request baru); ditolak Pimpinan SGA →
  **PIC Stationery** revisi.

**Enam peran (RBAC):**

| Peran | Kapabilitas inti |
|---|---|
| `requester` | Role dasar semua pegawai — buat/ajukan/revisi/batalkan request miliknya |
| `supervisor` (Pimpinan SIT) | Approval **L1** untuk seluruh seksi (role global) |
| `pic_stationery` (PIC Stationery) | Approval **L2** kuantitatif, verifikasi pembelian, kelola master item |
| `sga_manager` (Pimpinan SGA) | Approval **L3** final, lingkup global |
| `warehouse_officer` (PIC Gudang) | Input pembelian, serah terima barang |
| `administrator` | Kelola user/master, lihat audit; **bukan** aktor approval |

**Alur pembelian:** PIC Gudang input pembelian → PIC Stationery verifikasi → stok
**bertambah** (via ledger `IN`). Pembuat dokumen tidak boleh memverifikasi miliknya sendiri.

---

## Akun Demo

`EmployeeSeeder` (**hanya non-produksi**) menyeed **seluruh karyawan** sebagai requester +
5 akun approver tetap. Karena alurnya terpadu, karyawan mana pun bisa mengajukan dan L1 selalu
jatuh ke Pimpinan SIT. Kata sandi seragam: `password`.

**Akun approver & fungsi (tetap):**

| Username | Peran |
|---|---|
| `pimpinan.sit` | Approval **L1** — Pimpinan SIT (seluruh seksi) |
| `pic.stationery` | Approval **L2** kuantitatif + verifikasi pembelian |
| `vp.sga` | Approval **L3** final (Pimpinan SGA) |
| `pic.gudang` | Serah terima barang |
| `admin` | Administrator (bukan aktor request) |

**Karyawan (requester):** username = `namecode` karyawan (mis. `nik_37298009`), diseed dari
`database/data/employees.csv`. Bila berkas itu tidak ada, hanya akun approver yang dibuat.

**Contoh uji alur penuh:** login karyawan mana pun → `pimpinan.sit` (L1) → `pic.stationery`
(L2) → `vp.sga` (L3) → `pic.gudang` (serah terima). Ketiga approver sama untuk semua seksi.

---

## Struktur Modul

Batas modul ada di `app/Modules/`. Setiap modul memuat route dan Policy-nya sendiri lewat
`ServiceProvider` masing-masing; menambah modul cukup menambah satu baris di
`bootstrap/providers.php`.

```
app/Modules/
├── Identity/       user, departemen, RBAC, auth
├── Catalog/        item, kategori, UoM, import CSV
├── Inventory/      ledger stok, StockService, reservasi, snapshot  (satu-satunya penulis stok)
├── Approval/       engine approval generik (Request & Purchase)
├── Requisition/    request + workflow 10 status + approval 3 level
├── Purchasing/     dokumen pembelian + verifikasi (4 status)
├── Fulfillment/    serah terima barang + bukti cetak
├── Reporting/      8 laporan + dashboard + export .xlsx/PDF  (hanya membaca)
├── Notification/   notifikasi N1–N12 in-app & email, inbox
└── Audit/          jejak audit teknis, halaman admin
```

**Aturan yang ditegakkan otomatis** oleh `tests/Architecture/ModuleBoundaryTest.php`:

1. `app/Shared` tidak boleh bergantung pada modul bisnis mana pun.
2. `Approval` tidak boleh mengenal `Requisition` maupun `Purchasing` secara konkret.
3. `stock_quantity`/`reserved_quantity` **hanya** boleh ditulis dari modul `Inventory`.
4. Seluruh berkas wajib `declare(strict_types=1)`; tanpa sisa `dd`/`dump`/`var_dump`.
5. Controller berakhiran `Controller`, Service berakhiran `Service`.

Pelanggaran menggagalkan CI, bukan menunggu ketahuan saat review.

---

## Perintah Operasional Stok

```bash
php artisan stock:reconcile
```

Membandingkan `items.stock_quantity` terhadap SUM ledger. Selisih berarti ada mutasi yang
tidak melewati `StockService` — telusuri penyebabnya sebelum menjalankan `--fix`.

```bash
php artisan stock:adjust 1709000002 50 --reason="Stock opname"   # satu item
php artisan stock:seed-initial --reason="Saldo awal"             # SEMUA item aktif → max_stock
```

Saldo awal **wajib** masuk lewat perintah ini (transaksi `ADJUSTMENT`), bukan `UPDATE`
langsung — bila dilanggar, ledger tidak akan pernah rekonsiliasi.

```bash
php artisan stock:snapshot --backfill        # isi riwayat snapshot dari ledger (saat deploy)
php artisan stock:snapshot --current         # segarkan snapshot bulan berjalan
php artisan stock:snapshot --period=2026-07  # periode tertentu
```

Snapshot saldo bulanan menjadi sumber laporan **Stock by Month/Year** (R1/R2). Idempoten.

---

## Notifikasi & Queue

Notifikasi (N1–N12) bersifat **asinkron** (`ShouldQueue`, driver `database`) dan dikirim
**setelah** transaksi commit. Agar notifikasi in-app muncul, **queue worker wajib berjalan**:

```bash
php artisan queue:work        # sudah dijalankan otomatis oleh `composer dev`
```

Pengingat approval tertunda (N12) dijalankan scheduler; untuk mengirim manual:

```bash
php artisan approvals:remind --days=2
```

Di dev, `MAIL_MAILER=log` — email ditulis ke `storage/logs`, bukan dikirim. Isi kredensial
SMTP di `.env` untuk pengiriman sungguhan.

---

## Laporan & Dashboard

Modul `Reporting` **hanya membaca** (tidak pernah menulis ledger). Tersedia 8 laporan:

- **Stock by Month / Year** (R1–R2) — kartu/mutasi stok per periode (dari snapshot).
- **Purchasing** (R3) — rekap pembelian per periode.
- **Need to Buy** — item dengan `stock_quantity < min_stock`.
- **Request by Month / Year / Account / Item** (R4–R7) — rekap request; "Account" = Departemen/Seksi.

Setiap laporan dapat **diekspor ke .xlsx** dan dicetak PDF. Dashboard menampilkan statistik
ringkas sesuai kewenangan: requester hanya ringkasan miliknya, approver lingkup perusahaan.

---

## Pengujian & Kualitas Kode

Test memakai **MySQL** (database `taajri_stationery_test`), bukan SQLite — SQLite tidak
menegakkan `CHECK`, tidak punya `SELECT ... FOR UPDATE` nyata, sehingga uji integritas
berisiko lulus palsu. Suite lengkap melampaui `memory_limit` 128M default:

```bash
php -d memory_limit=512M vendor/pestphp/pest/bin/pest
```

Definition of Done sebelum menyatakan selesai:

```bash
vendor/bin/pint --test                       # format PHP
vendor/bin/phpstan analyse --memory-limit=1G # analisis statis level 6
php -d memory_limit=512M vendor/pestphp/pest/bin/pest
npm run types && npm run lint && npm run format:check && npm run build
php artisan stock:reconcile                  # SUM(ledger) == stock_quantity
```

Pintasan Composer:

| Perintah | Fungsi |
|---|---|
| `composer check` | Pint + PHPStan + seluruh test |
| `composer lint` / `composer lint:fix` | Format PHP (Pint) |
| `composer analyse` | Analisis statis (PHPStan level 6) |
| `composer test:arch` | Uji batas modul saja |

---

## Troubleshooting

**Tombol Setujui/Tolak tidak muncul di layar Verify.** Tombol digerbangi
`Gate::can('approveL{1,2,3}', $request)`. Periksa berurutan: (1) level & akun sudah tepat
(L1 = `pimpinan.sit`, L2 = `pic.stationery`, L3 = `vp.sga`); (2) reset cache permission
`php artisan permission:cache-reset && php artisan optimize:clear`; (3) aset frontend basi —
`npm run build` lalu hard refresh (Ctrl+Shift+R).

**MySQL tidak menegakkan CHECK.** Bila data yang seharusnya ditolak justru tersimpan
(mis. penolakan tanpa alasan, `quantity_approved > quantity_requested`), server Anda
< 8.0.16 atau MariaDB — perbaiki server dulu, jangan lanjut.

**Windows.** `composer dev` sering gagal — jalankan `npm run build`, `php artisan serve`,
dan `php artisan queue:work` di terminal terpisah.
