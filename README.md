# Sistem Stationery — PT Indonesia Asahan Aluminium

Sistem pengajuan dan verifikasi pembelian Alat Tulis Kantor (ATK), dibangun dari
`Blueprint Pengembangan Sistem-Stationery_REV1.0` (disetujui 23 Juni 2025).

**Status:** Fase 0–8 ✅ — alur bisnis inti blueprint **lengkap end-to-end**: request → 3 level approval → serah terima → stok berkurang, ditambah **8 laporan + dashboard + export Excel/PDF**, dan **notifikasi (N1–N12) in-app + email, inbox, serta audit trail**. 224 test hijau terhadap PostgreSQL. Fase 9 (UAT & Go-Live) berikutnya.

---

## Tech Stack

| Lapis | Teknologi |
|---|---|
| Backend | Laravel 12 · PHP 8.4 |
| Frontend | React 19 · TypeScript · InertiaJS 2 |
| UI | TailwindCSS 4 · shadcn/ui · Poppins · motion (animasi) |
| Database | PostgreSQL 16+ |
| Auth | Laravel Built-in Auth + RBAC (`spatie/laravel-permission`) |
| Arsitektur | Monolith Modular · Service Layer Pattern |
| Deployment | Nginx + PHP-FPM + Supervisor + Cron (**tanpa Docker**) |

---

## Dokumentasi

> **Melanjutkan pekerjaan ini di sesi baru? Baca [docs/HANDOVER.md](docs/HANDOVER.md) lebih
> dulu.** Dokumen itu memuat temuan analisis blueprint, keputusan yang sudah dikunci, dan
> daftar jebakan yang sudah pernah ditabrak — hal-hal yang tidak dapat disimpulkan hanya
> dengan membaca kode.

| Dokumen | Isi |
|---|---|
| **[HANDOVER.md](docs/HANDOVER.md)** | **Serah terima konteks — baca pertama** |
| [Arsitektur](docs/architecture/README.md) | Indeks seluruh dokumen arsitektur |
| [Analisis Requirement](docs/architecture/01-requirement-analysis.md) | Proses bisnis, aktor, modul, workflow, keputusan D1–D8 |
| [Architecture Blueprint](docs/architecture/02-architecture-blueprint.md) | Diagram, 12 ADR, folder structure |
| [Database Schema](docs/architecture/03-database-schema.md) | DDL 17 tabel, constraint, uji integritas |
| [Roadmap](docs/architecture/04-roadmap.md) | 15 sprint, deployment, risiko |
| [Setup Development](docs/development-setup.md) | Prasyarat mesin developer |

**Baca [Setup Development](docs/development-setup.md) lebih dulu** — ada ekstensi PHP
yang harus diaktifkan dan Laravel wajib dipin ke versi 12.

---

## Menjalankan Secara Lokal

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Sesuaikan kredensial PostgreSQL di `.env`, lalu:

```bash
php artisan migrate --seed
```

Seeder membuat role, permission, departemen awal, **katalog 236 item Inalum** (dari
`database/data/stationery-items.csv`), dan — di luar produksi — satu akun demo per aktor
dengan kata sandi `password`:

| Username | Peran | Atasan |
|---|---|---|
| `admin` | Administrator | — |
| `vp.sga` | Pimpinan SGA (approval L3) | — |
| `ms.sit` | Pimpinan User (approval L1) | — |
| `pic.stationery` | PIC Stationery (approval L2) | vp.sga |
| `pic.gudang` | PIC Gudang (pembelian & serah terima) | vp.sga |
| `mawan` | Requester | ms.sit |

Lalu jalankan aplikasinya:

```bash
composer dev
```

`composer dev` menjalankan server, queue worker, log viewer, dan Vite sekaligus.

### Menjalankan di Windows (bila `composer dev` gagal)

`composer dev` memakai `concurrently` yang kadang bermasalah di Windows. Alternatif:
jalankan tiap proses di **terminal terpisah**.

Paling sederhana — pakai aset hasil build (tanpa Vite):

```bash
npm run build
php artisan serve
php artisan queue:work
```

Buka `http://localhost:8000`. `queue:work` **wajib** berjalan agar notifikasi in-app
muncul. Untuk pengembangan frontend dengan hot-reload, ganti `npm run build` dengan
`npm run dev` di terminal tersendiri.

---

## Perintah Kualitas Kode

```bash
composer check
```

Menjalankan Pint (format), PHPStan level 6 (analisis statis), dan seluruh test.

| Perintah | Fungsi |
|---|---|
| `composer lint` / `composer lint:fix` | Format PHP (Pint) |
| `composer analyse` | Analisis statis (PHPStan level 6) |
| `composer test:arch` | Uji batas modul saja |
| `npm run types` | Cek tipe TypeScript |
| `npm run lint` / `npm run format` | ESLint / Prettier |

---

## Struktur Modul

Batas modul ada di `app/Modules/`. Setiap modul memuat route dan Policy-nya sendiri
lewat `ServiceProvider` masing-masing; menambah modul cukup menambah satu baris di
`bootstrap/providers.php`.

```
app/Modules/
├── Identity/       user, role, permission, struktur organisasi
├── Catalog/        item, kategori, UoM
├── Requisition/    request + mesin status
├── Approval/       engine approval generik (Request & Purchase)
├── Fulfillment/    serah terima barang
├── Purchasing/     dokumen pembelian + verifikasi
├── Inventory/      ledger stok, saldo, reservasi
├── Notification/   notifikasi in-app & email
├── Reporting/      8 laporan + dashboard
└── Audit/          jejak audit teknis
```

**Aturan yang ditegakkan otomatis** oleh `tests/Architecture/ModuleBoundaryTest.php`:

1. `app/Shared` tidak boleh bergantung pada modul bisnis mana pun
2. `Approval` tidak boleh mengenal `Requisition` maupun `Purchasing` secara konkret
3. Seluruh berkas wajib `declare(strict_types=1)`
4. Tidak boleh ada sisa `dd`/`dump`/`var_dump`
5. Konvensi penamaan Controller & Service

Pelanggaran menggagalkan CI, bukan menunggu ketahuan saat review.

> **Aturan terpenting** — hanya modul `Inventory` yang boleh menulis stok. Sejak Fase 3
> aturan ini ditegakkan otomatis: ledger tidak boleh dipakai di luar modulnya, dan
> penulisan `stock_quantity`/`reserved_quantity` dari modul lain menggagalkan CI.

---

## Perintah Operasional Stok

```bash
php artisan stock:reconcile
```

Membandingkan `items.stock_quantity` terhadap ledger. Selisih berarti ada mutasi yang
tidak melewati `StockService` — telusuri penyebabnya sebelum menjalankan `--fix`.

```bash
php artisan stock:adjust 1709000002 50 --reason="Saldo Awal Migrasi"   # satu item
php artisan stock:seed-initial --reason="Saldo Awal Migrasi"           # SEMUA item aktif → max_stock
```

Menyesuaikan stok ke nilai tertentu. `stock:adjust` untuk satu item (stock opname/koreksi);
`stock:seed-initial` mengisi **seluruh item aktif ke `max_stock`** sekaligus (pengisian saldo
awal go-live), idempoten. **Saldo awal wajib masuk lewat perintah ini**, bukan `UPDATE`
langsung — bila dilanggar, ledger tidak akan pernah rekonsiliasi dengan saldo. Jalankan
`stock:reconcile` lalu `stock:snapshot --current` setelahnya.

```bash
php artisan stock:snapshot --backfill      # isi riwayat snapshot dari ledger (saat deploy)
php artisan stock:snapshot --current       # segarkan snapshot bulan berjalan
php artisan stock:snapshot --period=2026-07 # periode tertentu
```

Membangun snapshot saldo bulanan yang menjadi sumber laporan **Stock by Month/Year**
(R1/R2). Dijadwalkan otomatis: bulanan (tanggal 1, untuk bulan yang baru selesai) plus
refresh harian untuk bulan berjalan. Idempoten — aman dijalankan ulang.

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

Di dev, `MAIL_MAILER=log` — email notifikasi ditulis ke `storage/logs`, bukan dikirim.
Isi kredensial SMTP di `.env` untuk pengiriman sungguhan.

---

## Catatan Menjalankan Test

Suite lengkap (229 test) melampaui `memory_limit` default 128M. Jalankan dengan limit
lebih besar:

```bash
php -d memory_limit=512M vendor/pestphp/pest/bin/pest
```

---

## Pengembangan Selanjutnya & Troubleshooting

Sistem sudah fungsional dan teruji (**229 test hijau**), tetapi UAT dapat memunculkan
kendala **lingkungan** (bukan logika). Catat setiap temuan di bagian ini agar sesi
pengembangan berikutnya — termasuk di window/mesin lain — bisa cepat menindaklanjuti.

### ⚠️ Tombol Approve/Reject tidak muncul di PIC Stationery (L2)

**Gejala:** request yang sudah disetujui approver **L1** berpindah ke PIC Stationery
(status `PENDING_STATIONERY`), tetapi layar _Verify_ tampil **tanpa** tombol
`Submit`/`Ditolak Seluruhnya` dan tanpa input _Quantity Actual_.

**Hasil investigasi (kode sudah benar):**

- Tombol digerbangi prop `canDecide` dari
  [`RequestApprovalController::canDecide()`](app/Modules/Requisition/Http/Controllers/RequestApprovalController.php)
  = `Gate::can('approveL2', $request)`.
- [`RequestPolicy::approveL2`](app/Modules/Requisition/Policies/RequestPolicy.php)
  = punya permission `request.approve.l2` **DAN** status `PENDING_STATIONERY`. **Benar.**
- Frontend [`Verify/Show.tsx`](resources/js/pages/Requests/Verify/Show.tsx) menampilkan
  tombol pada `{canDecide && …}` dan stepper kuantitas pada `mode === 'l2' && canDecide`. **Benar.**
- Policy _terdaftar_ dan berfungsi (tombol L1 muncul & approve L1 berhasil).
- Akun `pic.stationery` di DB dev **punya** `request.approve.l2` (tinker: `YES`).
- 229 test hijau, termasuk otorisasi approval L1/L2/L3.

**Kesimpulan:** ini **bukan bug logika**. Jika tombol hilang, `canDecide` bernilai
`false` karena pemeriksaan permission gagal **di lingkungan yang menjalankan aplikasi**.
Penyebab paling mungkin, urut dari yang tersering — jalankan perbaikannya di lingkungan Anda:

1. **Cache permission spatie basi** (paling sering setelah seed/ubah role):
   ```bash
   php artisan permission:cache-reset
   php artisan optimize:clear
   ```
2. **Aset frontend basi** — karena `composer dev`/Vite tidak berjalan di Windows, browser
   memuat build lama. Bangun ulang lalu _hard refresh_ (Ctrl+Shift+R):
   ```bash
   npm run build
   ```
3. **Akun PIC belum ter-seed permission-nya.** Verifikasi cepat:
   ```bash
   php artisan tinker --execute="echo App\Modules\Identity\Models\User::where('username','pic.stationery')->first()?->can('request.approve.l2') ? 'YES' : 'NO';"
   ```
   Bila `NO`, seed ulang role/permission:
   ```bash
   php artisan db:seed --class="Database\Seeders\RolePermissionSeeder"
   php artisan permission:cache-reset
   ```

**Cara reproduksi untuk verifikasi:** buat request → approve sebagai approver L1 →
login sebagai `pic.stationery` → buka request berstatus `PENDING_STATIONERY`. Tombol
`Submit` + `Ditolak Seluruhnya` dan input `Quantity Actual` harus muncul.

> Catatan: "salah satu contoh" — selama UAT, verifikasi juga alur L3 (PENDING_SGA),
> penerbitan PO, dan penerimaan barang dengan pola diagnosis yang sama
> (`canDecide` → permission → status → cache → build).

### Reminder dev di Windows

`composer dev` (via `concurrently`) sering gagal di Windows. Jalankan tiap proses di
terminal terpisah:

```bash
npm run build
php artisan serve
php artisan queue:work
```
