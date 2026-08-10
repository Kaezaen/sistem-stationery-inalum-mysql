# Serah Terima Konteks — Sistem Stationery Inalum

> **Dokumen ini ditujukan untuk sesi kerja berikutnya (manusia maupun AI).**
> Bacalah SEBELUM menulis kode apa pun. Isinya adalah hal-hal yang **tidak dapat
> disimpulkan hanya dengan membaca kode** — temuan analisis blueprint, keputusan
> yang sudah dikunci, dan jebakan yang sudah pernah ditabrak.
>
> Terakhir diperbarui: **8 Agustus 2026**, setelah Fase 8 selesai.

---

## 1. Mulai dalam 5 Menit

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed        # termasuk katalog 236 item Inalum
composer dev
```

> **Windows:** bila `composer dev` gagal (`concurrently`), jalankan di terminal terpisah:
> `npm run build`, `php artisan serve`, `php artisan queue:work`. `queue:work` wajib
> hidup agar notifikasi in-app muncul.

Akun demo (kata sandi seragam `password`):

| Username | Peran | Atasan |
|---|---|---|
| `admin` | Administrator | — |
| `vp.sga` | Pimpinan SGA — approval L3 | — |
| `ms.sit` | Pimpinan User — approval L1 | — |
| `pic.stationery` | PIC Stationery — approval L2, verifikasi pembelian | vp.sga |
| `pic.gudang` | PIC Gudang — input pembelian, serah terima | vp.sga |
| `mawan` | Requester | **ms.sit** |

Verifikasi bahwa semuanya sehat:

```bash
composer check && npm run types && npm run lint && npm run build
```

Harapan: **226 test lulus**, PHPStan level 6 tanpa error, seluruh cek frontend bersih.

> **Catatan test:** suite lengkap melampaui `memory_limit` 128M default. Jalankan
> `php -d memory_limit=512M vendor/pestphp/pest/bin/pest` (lihat jebakan §6.12).

---

## 2. Posisi Saat Ini

| Fase | Cakupan | Status |
|---|---|---|
| 0 | Fondasi (Laravel 12, Inertia, React 19, CI, uji arsitektur) | ✅ |
| 1 | Identity & Access (auth, user, departemen, RBAC) | ✅ |
| 2 | Catalog (item, kategori, UoM, import CSV) | ✅ |
| 3 | Inventory Core (ledger, StockService, reservasi) | ✅ |
| 4 | Purchasing (+ modul Approval generik) | ✅ |
| 5 | Requisition & Approval tiga level | ✅ |
| 6 | Fulfillment — serah terima barang | ✅ |
| 7 | Reporting (8 laporan + dashboard + export) | ✅ |
| 8 | Notifikasi (N1–N12) & Audit | ✅ |
| **9** | **UAT, Hardening & Go-Live** | **⬅ BERIKUTNYA** |

**Angka saat ini:** 21 migration · 10 modul · 29 berkas test · 226 test · 960 assertion. Katalog **236 item Inalum** ter-seed (`database/data/stationery-items.csv`).

**Seluruh cakupan fungsional blueprint (Bab 1 fitur 1–7) sudah lengkap.** Yang tersisa Fase 9 adalah UAT, hardening keamanan, migrasi data, dan go-live — bukan fitur baru.

**Alur bisnis inti blueprint sudah lengkap end-to-end:** request → approval L1 → L2 kuantitatif → L3 → serah terima → stok berkurang, dengan ledger yang selalu rekonsiliasi.

---

## 3. Lima Temuan Blueprint yang Menentukan Desain

Ini hasil analisis dokumen blueprint (termasuk membaca **29 gambar tertanam** di PDF —
swimlane, 11 wireframe, dan ERD). Beberapa temuan **hanya ada di gambar**, tidak di teks.
Jangan mendesain ulang tanpa memahami ini.

### 3.1 Approval PIC Stationery bersifat KUANTITATIF, bukan biner

Wireframe 3.3.2 punya kolom `QUANTITY` (diminta) vs `QUANTITY ACTUAL` (stepper ±) plus
`REMARK` per baris. Diagram menyebut *"Permintaan disetujui / disetujui sebagian?"*.

Level 2 **mengubah data**, bukan sekadar memindahkan status. Karena itu ia punya method
tersendiri (`approveByStationery($request, $approver, $decisions)`), bukan `approve()`
yang seragam seperti L1 dan L3.

### 3.2 Penolakan Pimpinan SGA kembali ke PIC Stationery — BUKAN ke requester

Tiga titik penolakan punya tiga tujuan berbeda:

| Ditolak oleh | Yang merevisi | Referensi |
|---|---|---|
| Pimpinan User (L1) | **Requester** | Bab 3.6 |
| PIC Stationery (L2) | Requester diberi tahu — status **final** (D1) | tombol "Ditolak Seluruhnya" |
| Pimpinan SGA (L3) | **PIC Stationery** | Bab 3.7 + Gambar 2.1 |

Ini bagian yang paling mudah tertukar. Aturannya dikumpulkan di satu `match` pada
`RequestPolicy::revise()` supaya terbaca berdampingan.

### 3.3 Stok hanya bergerak di DUA titik

- **Masuk:** saat pembelian **diverifikasi** PIC Stationery — bukan saat diinput.
- **Keluar:** saat barang **diserahkan** PIC Gudang — bukan saat disetujui.

Menaikkan stok saat input akan memaksa koreksi negatif bila kemudian ditolak, dan
merusak integritas ledger.

### 3.4 Aturan status stok (di-reverse-engineer dari angka contoh wireframe 3.11.2)

```
stock > max_stock  → OVER STOCK
stock < min_stock  → UNDER STOCK
selain itu         → STOCK ON HAND
```

Tidak tertulis di teks blueprint mana pun. Dikunci di `Catalog\Enums\StockStatus` dan
diuji di `tests/Unit/Catalog/StockStatusTest.php` dengan angka contoh aslinya.

### 3.5 Celah alokasi ganda stok → mekanisme reservasi (ADR-07)

Antara PIC Stationery menetapkan kuantitas dan PIC Gudang menyerahkan barang ada jeda
(melewati approval SGA). Tanpa reservasi, dua request dapat sama-sama disetujui atas stok
fisik yang sama, lalu salah satunya gagal di gudang **setelah** melewati seluruh approval.

Reservasi **tidak** mengurangi `stock_quantity` — yang bertambah adalah `reserved_quantity`.
Stok tersedia = `stock_quantity - reserved_quantity`.

### Inkonsistensi dokumen yang ditemukan

Bab 1 blueprint menyebut *"permintaan dari PIC Gudang"*, sedangkan seluruh diagram Bab 2–3
menunjukkan permintaan berasal dari **User/Requester**. **Diagram diperlakukan sebagai
sumber kebenaran.** Belum dikonfirmasi ke pemilik proses bisnis.

---

## 4. Keputusan Terkunci — Jangan Dilitigasi Ulang

### D1–D8 (disetujui pengguna, 6 Agustus 2026)

| # | Keputusan |
|---|---|
| D1 | Request yang ditolak PIC Stationery bersifat **final** — requester membuat request baru |
| D2 | Pimpinan SGA **berbasis role**, satu jabatan untuk seluruh perusahaan |
| D3 | "Account" pada laporan *Request by Account* = **Departemen/Seksi** |
| D4 | `unit_price` pembelian **nullable**, disembunyikan di UI Fase 1 |
| D5 | Serah terima **sebagian diperbolehkan** bila stok fisik kurang |
| D6 | **Tanpa delegasi approval** di Fase 1 |
| D7 | Request **lintas kategori** dalam satu dokumen diperbolehkan |
| D8 | **Tanpa purge** — data historis dipertahankan penuh |

Dasar dan dampak perubahan tiap keputusan ada di
[Analisis §11](architecture/01-requirement-analysis.md).

### ADR yang paling sering disalahpahami

| ADR | Inti | Jangan |
|---|---|---|
| **ADR-02** | Modul = folder ber-namespace di `app/Modules/`, tanpa paket pihak ketiga | Jangan pasang `nwidart/laravel-modules` |
| **ADR-04** | Repository **hanya** untuk query laporan & ledger. CRUD pakai Eloquent langsung | Jangan bungkus setiap model dengan repository |
| **ADR-05** | Mesin status = tabel transisi deklaratif buatan sendiri | Jangan pasang paket state machine |
| **ADR-07** | Reservasi stok saat approval L2 | Jangan kurangi `stock_quantity` saat approval |
| **ADR-08** | Ledger = sumber kebenaran; `items.stock_quantity` = cache | Jangan tulis kolom stok di luar `StockService` |
| **ADR-09** | Status = `varchar` + `CHECK`, bukan tipe ENUM PostgreSQL | Jangan pakai `CREATE TYPE ... AS ENUM` |
| **ADR-11** | Queue driver `database` | Jangan tambahkan Redis |

---

## 5. Konvensi WAJIB

### 5.1 Aturan yang ditegakkan otomatis

`tests/Architecture/ModuleBoundaryTest.php` menggagalkan CI bila dilanggar:

1. `app/Shared` **tidak boleh** bergantung pada modul bisnis mana pun.
2. Modul `Approval` **tidak boleh** mengenal `Requisition` maupun `Purchasing`.
3. `InventoryTransaction` hanya boleh dipakai di dalam modul `Inventory`.
4. Kolom `stock_quantity` / `reserved_quantity` **tidak boleh ditulis** di luar modul Inventory.
5. Semua berkas `declare(strict_types=1)`.
6. Tidak ada `dd` / `dump` / `var_dump` / `print_r`.
7. Controller berakhiran `Controller`, Service berakhiran `Service`.
8. Setiap model ber-`HasFactory` **wajib** punya `newFactory()` (lihat jebakan §6.2).
9. Setiap modul punya ServiceProvider yang terdaftar di `bootstrap/providers.php`.

### 5.2 Pola yang harus diikuti

**Controller** hanya: `authorize()` → validasi → panggil Service → kembalikan Inertia.
Tidak ada logika bisnis. Setiap action yang mengubah state **wajib** memanggil `authorize()`.

**Otorisasi berlapis dua.** Permission menjawab *"boleh akses fitur ini?"*; Policy menjawab
*"boleh atas dokumen INI, dalam status INI?"*. Permission saja tidak cukup — lihat §6.5.

**Transisi status** selalu: kunci baris (`lockForUpdate`) → baca ulang status → jalankan
efek → simpan status baru, seluruhnya dalam satu `DB::transaction`. Lihat
`RequestService::transition()` dan `PurchaseService::transition()`.

**Semua yang menyentuh stok** wajib lewat `StockService`, dalam transaksi, dengan
`lockForUpdate()`, dan menghasilkan tepat satu baris ledger.

**Menambah modul baru:** buat folder di `app/Modules/`, `<Nama>ServiceProvider` yang
extend `ModuleServiceProvider`, `routes/modules/<nama>.php`, lalu daftarkan providernya di
`bootstrap/providers.php`. Tidak ada berkas terpusat lain yang perlu disentuh.

**Bahasa.** Komentar, pesan error, dan label UI dalam Bahasa Indonesia. Nama kelas,
method, dan kolom dalam Bahasa Inggris. Label status mengikuti teks wireframe blueprint
persis — jangan diterjemahkan bebas.

---

## 6. Jebakan yang SUDAH Ditabrak — Jangan Ulangi

### 6.1 `composer create-project laravel/laravel` menarik Laravel 13

Ketentuan proyek mewajibkan **Laravel 12**. Selalu pin:

```bash
composer create-project "laravel/laravel:^12.0" nama-folder
```

### 6.2 Factory tidak ditemukan untuk model di dalam modul

Resolusi otomatis Laravel mengasumsikan model ada di `App\Models`, sehingga ia mencari
`Database\Factories\Modules\<Modul>\Models\<X>Factory` yang tidak pernah ada. **Setiap
model wajib menunjuk factory-nya manual:**

```php
protected static function newFactory(): ItemFactory
{
    return ItemFactory::new();
}
```

Sudah dijaga uji arsitektur, tetapi gejalanya membingungkan bila lupa (19 test gagal
sekaligus).

### 6.3 Union type pada FormRequest membuat approval level lain selalu 403

`approve(ApproveByStationeryRequest|HttpRequest $r)` membuat Laravel **selalu** me-resolve
ke FormRequest-nya, sehingga `authorize()` milik level 2 ikut berjalan saat yang menyetujui
adalah approver L1 atau L3. **Jangan pakai union type FormRequest.** Bila satu endpoint
melayani beberapa peran, pakai `HttpRequest` biasa dan validasi di dalam method.

### 6.4 `CHECK` constraint bernilai NULL dianggap LULUS oleh PostgreSQL

```sql
-- SALAH: lolos diam-diam saat quantity_approved masih NULL
CHECK (quantity_actual IS NULL OR quantity_actual <= quantity_approved)

-- BENAR
CHECK (quantity_actual IS NULL
       OR (quantity_approved IS NOT NULL AND quantity_actual <= quantity_approved))
```

Selalu sertakan `IS NOT NULL` eksplisit pada kolom nullable yang ikut dibandingkan.

### 6.5 Matriks permission tidak menangkap aturan kontekstual

Pimpinan seksi lain memegang permission `request.approve.l1` yang **identik**. Yang
mencegahnya menyetujui request bukan bawahannya hanyalah `RequestPolicy::approveL1()`.
Diuji di `RequestAuthorizationTest` dengan dua pimpinan dari seksi berbeda.

Pola yang sama berlaku untuk pemisahan tugas pembelian: pembuat dokumen tidak boleh
memverifikasi miliknya sendiri (`PurchasePolicy::verify()`).

### 6.6 `RefreshDatabase` membuat uji penguncian baris lulus palsu

`RefreshDatabase` membungkus tiap test dalam transaksi yang tidak pernah di-commit,
sehingga koneksi kedua **tidak akan pernah melihat** baris yang diuji — test hijau tanpa
membuktikan apa pun. `tests/Feature/Inventory/StockRowLockTest.php` memakai
`DatabaseTruncation`, plus **test kontrol** yang memastikan koneksi probe memang bisa
mengunci baris lain.

### 6.7 Nama constraint mengikuti Laravel, bukan DDL di dokumen

Dokumen `03-database-schema.md` adalah **spesifikasi bentuk**, bukan skrip yang dijalankan.
Migration menghasilkan `<tabel>_<kolom>_foreign`. Nama manual hanya berlaku untuk
constraint yang dibuat lewat `DB::statement()` (mis. `chk_users_not_own_manager`).

### 6.8 Heredoc bash merusak namespace PHP dan file TSX besar

`cat > file <<'EOF'` sering gagal untuk berkas TSX panjang, dan heredoc tanpa kutip
merusak `\M` pada `App\Modules\...`. **Gunakan tool Write untuk berkas PHP/TSX**, bukan
heredoc.

### 6.9 SQLite tidak memadai untuk proyek ini

Skema bergantung pada partial index, `jsonb`, `inet`, `pg_trgm`, dan `SELECT … FOR UPDATE`.
`phpunit.xml` sudah diarahkan ke `taajri_stationery_test` di PostgreSQL. Jangan
kembalikan ke SQLite "supaya test cepat" — uji integritas akan lulus palsu.

### 6.11 `DatabaseTruncation` membocorkan data ke berkas test lain

`DatabaseTruncation` membersihkan tabel **sebelum** tiap test, sehingga baris dari test
TERAKHIR sebuah berkas tetap ter-commit setelah berkasnya selesai. Baris sisa itu terlihat
oleh berkas test lain — dan bila nilai acak dari factory kebetulan bertabrakan, muncul
`UniqueConstraintViolationException` **secara acak**.

Terbukti menyisakan 8 baris pada `StockRowLockTest`. Kegagalan seperti ini jauh lebih sulit
ditelusuri daripada kegagalan konsisten, karena bergantung pada urutan test dan nilai acak.

**Setiap berkas yang memakai `DatabaseTruncation` wajib punya `afterEach` yang membersihkan
sendiri.** Setelah memperbaikinya, jalankan suite beberapa kali berturut-turut — sekali
hijau tidak membuktikan apa pun untuk kegagalan acak.

### 6.10 Nama kelas `Request` bentrok dengan `Illuminate\Http\Request`

Model domain sengaja bernama `Request` agar sesuai ERD. Di Controller, request HTTP
diimpor sebagai `use Illuminate\Http\Request as HttpRequest;`.

### 6.12 Zona sesi PostgreSQL adalah +07, sedangkan `app.timezone` UTC

Sesi PostgreSQL di lingkungan ini memakai **Asia/Bangkok (+07)**; aplikasi memakai
`app.timezone = UTC`. Dua akibat yang harus disadari saat menulis query berbasis tanggal:

1. **Jangan membandingkan instant Carbon hasil parse dari kolom timestamptz terhadap
   `now()`.** `min('transaction_date')` kembali dalam +07; `CarbonImmutable::parse(...)
   ->startOfMonth()` lalu dibandingkan `<` terhadap `now()->startOfMonth()` (UTC) menggeser
   batas bulan tujuh jam — sempat menarik bulan berjalan ke `stock:snapshot --backfill`.
   Solusi di `StockSnapshotService::backfill()`: normalkan ke zona app, lalu bandingkan
   sebagai **indeks bulan kalender** `year*12+month`, bukan instant.
2. **Batas bulan pada snapshot memakai interval setengah terbuka** `[awal, awal berikutnya)`
   agar transaksi di mikrodetik terakhir bulan tidak lolos. Karena Laravel mengikat Carbon
   sebagai string naif dan PostgreSQL menafsirkannya di zona sesi (+07), atribusi bulan
   snapshot secara efektif mengikuti kalender **Jakarta** — konsisten dengan tampilan
   Asia/Jakarta, dan sama untuk backfill maupun job bulanan.

Laporan yang berbasis kolom `date` murni (`requests.request_date`, `purchases.purchase_date`)
**tidak** terpengaruh — `date` tak menyimpan zona.

### 6.13 Suite lengkap melampaui `memory_limit` 128M

Menjalankan `vendor/bin/pest` polos gagal dengan *Allowed memory size exhausted* setelah
suite membesar (224 test, plus pembacaan .xlsx openspout). Jalankan dengan limit lebih besar
dan lewat binari Pest langsung (proxy `vendor/bin/pest` tidak bisa dijalankan via `php -d`):

```bash
php -d memory_limit=512M vendor/pestphp/pest/bin/pest
```

### 6.14 Jangan meredeklarasi `$afterCommit` — trait Queueable sudah punya

`class X extends Notification { public bool $afterCommit = true; }` **fatal**: trait
`Illuminate\Bus\Queueable` sudah mendeklarasi `$afterCommit` dengan tipe/default berbeda,
sehingga komposisinya bentrok. Untuk menunda pengiriman ke setelah commit (ADR-12), panggil
**method** dari trait di konstruktor: `$this->afterCommit();` — bukan meredeklarasi properti.

### 6.15 Uji arsitektur berbasis pemindaian teks — hati-hati false positive

Dua jebakan dari `ModuleBoundaryTest`:

1. **Berkas di `Services/` WAJIB berakhiran `Service`.** `AuditLogger` & `RecipientResolver`
   sempat menggagalkan CI karena namanya tidak berakhiran `Service`. Solusi: taruh kelas
   ber-peran (logger/resolver) di `Support/`, bukan `Services/` (pola `Reporting/Support`).
2. **Cek "HasFactory tanpa newFactory" memakai `str_contains($source, 'HasFactory')`.**
   Menyebut string `HasFactory` **di komentar** pun memicu offender palsu. Bila sebuah model
   sengaja tanpa factory, jangan tulis kata "HasFactory" di komentarnya.

### 6.16 Notifikasi queued tidak terkirim di dev tanpa worker (dan lolos senyap di test)

Notifikasi memakai `ShouldQueue` + driver `database`. Konsekuensi:

- **Dev:** notifikasi in-app baru muncul bila **queue worker berjalan** (`composer dev`
  menjalankannya; `php artisan serve` polos tidak). Untuk menyuntik data uji cepat tanpa
  worker, pakai `Notification::sendNow(...)` yang melewati antrean.
- **Test:** dengan `RefreshDatabase`, callback `afterCommit` **tidak** menyala (transaksi test
  tak pernah commit) — jadi uji wiring memakai `Notification::fake()` (memverifikasi penerima
  & kode), bukan menghitung baris `notifications`. Pengiriman nyata diverifikasi di browser.

### 6.17 Animasi entrance jangan menggerbangi VISIBILITAS pada opacity

UI memakai `motion` (framer-motion) untuk animasi (dashboard, toast flash, badge, transisi
halaman). Dua hal yang menggigit:

1. **Tab tersembunyi menjeda `requestAnimationFrame`.** Elemen dengan `initial={opacity:0}`
   yang dimuat saat tab tidak terlihat (mis. Browser pane otomasi yang tidak ditampilkan)
   **tetap** di opacity 0 sampai tab difokuskan — konten tampak hilang. Karena itu **pembungkus
   konten lebar-halaman di `AuthenticatedLayout` memakai transform-only** (`y:8→0`, opacity
   tetap 1): halaman berisi tabel tak pernah tak terlihat. Efek fade yang lebih kaya disimpan
   per-halaman (dashboard), yang selalu dimuat di foreground.
2. **Verifikasi di browser otomasi terbatas.** Pane sering tidak ter-composite (screenshot
   gagal, `document.hidden = true`). Verifikasi lewat `javascript_tool`: cek `visibilityState`,
   `getComputedStyle(...).opacity`, dan keberadaan elemen — bukan screenshot.

`prefers-reduced-motion` dihormati global lewat `<MotionConfig reducedMotion="user">` di
`app.tsx`.

---

## 7. Peta Modul

| Modul | Isi utama | Catatan |
|---|---|---|
| `Identity` | User, Department, RBAC, auth | `users.manager_id` menentukan approver L1 |
| `Catalog` | Item, Category, Uom, import CSV | `StockStatus` enum ada di sini |
| `Inventory` | Ledger, `StockService`, reservasi | **Satu-satunya penulis stok** |
| `Approval` | `Approvable`, `ApprovalService`, tabel polymorphic | Tidak mengenal Request/Purchase |
| `Purchasing` | Purchase, workflow 4 state | Stok naik saat `verify()` |
| `Requisition` | Request, workflow 10 state, approval 3 level | Modul paling kompleks |
| `Fulfillment` | `HandoverService`, serah terima + bukti cetak | Konsumsi reservasi + stok keluar |
| `Reporting` | 8 Query Object (R1–R8), `ReportService`, `ReportExportService`, `DashboardService` | **Hanya membaca** — snapshot & tabel peer, tidak pernah ledger |
| `Notification` | `RecipientResolver` (Support), subscriber Request/Purchase/Stock, notifikasi N1–N12, inbox | Subscriber baca event, `ShouldQueue`+`afterCommit` |
| `Audit` | `AuditLogger` (Support), observer Item/RequestItem, subscriber auth, halaman admin | Append-only; login gagal tanpa password |

**Event domain → subscriber notifikasi/audit (Fase 8).** `RequestSubmitted`,
`RequestApproved`, `RequestRejected`, `RequestCompleted`, `PurchaseSubmitted`,
`PurchaseVerified`, `PurchaseRejected` (sejak Fase 4–5) + `StockFellBelowMinimum` (Fase 8).
Subscriber di modul Notification/Audit mendengarkannya — kode workflow tidak disentuh.

---

## 8. Fase 8 — Ringkasan (selesai) & Fase 9 — Langkah Berikutnya

### Yang dibangun di Fase 8

- **Notifikasi N1–N12** (in-app channel `database` + email channel `mail`). Subscriber di
  `Notification/Listeners/` mendengarkan event domain (Fase 4–5) + `StockFellBelowMinimum`
  (baru, dari `StockService` saat stok melintasi < min). Penerima di-resolve
  `RecipientResolver` (`Notification/Support/`). Titik balik penolakan berbeda per level
  tercermin di subscriber (L1/L2→requester, L3→PIC Stationery).
- **After-commit (ADR-12).** Notifikasi `ShouldQueue` + `$this->afterCommit()` di konstruktor.
  Payload **primitif** (bukan model) agar aman di-queue & Notification bebas import model bisnis.
- **Inbox** `/notifications` + badge unread di header (shared prop `notifications.unread` di
  `HandleInertiaRequests`). Nav "Notifikasi".
- **N12** command `approvals:remind --days=2`, dijadwalkan hari kerja 07:00 (`routes/console.php`).
- **Audit** (`Audit/Support/AuditLogger`): observer `Item` (min/max/create/update/delete,
  **kecuali** kolom saldo — sudah di ledger), `RequestItem` (`quantity_actual`), subscriber auth
  (login/gagal/logout — **tanpa password**). Halaman `/admin/audit-logs` (Administrator saja).
- **Migration 20–21:** `notifications` (uuid, jsonb, partial index unread) + `audit_logs`
  (append-only, `ip_address` inet, `auditable` nullable untuk kejadian tanpa entitas).

### Fase 9 — UAT, Hardening & Go-Live *(roadmap Sprint 14)*

Tidak ada fitur baru — cakupan fungsional blueprint sudah lengkap. Fokus:

- **UAT per aktor** (akun demo §1) + demo alur end-to-end ke pemilik proses bisnis.
- **Migrasi data:** import katalog + master user/departemen + **saldo awal via `ADJUSTMENT`**
  (`stock:adjust`, jangan `UPDATE` langsung), lalu `stock:snapshot --backfill`.
- **Hardening keamanan:** uji Policy per role, CSRF, rate-limit login, header keamanan; jalankan
  `/security-review` bila tersedia.
- **Operasional produksi:** queue worker (Supervisor/Windows Service) untuk notifikasi, cron
  `schedule:run`, `pg_dump` terjadwal, SMTP internal (`MAIL_MAILER` selain `log`).

**Pertanyaan pemilik proses yang MASIH terbuka (perlu dijawab sebelum go-live):**

- **D5 sisa penyerahan sebagian:** bila diserahkan sebagian, sisa yang tak diserahkan
  **hangus** (asumsi saat ini: request tetap `COMPLETED`) atau menyisakan request terbuka?
- **Inkonsistensi Bab 1 vs diagram** (§3 dokumen ini): "permintaan dari PIC Gudang" belum
  dikonfirmasi — diagram (User/Requester sebagai pemohon) dipakai sebagai kebenaran.

---

## 9. Peta Dokumen

| Dokumen | Bacalah bila ingin tahu |
|---|---|
| **HANDOVER.md** (ini) | Konteks kerja, keputusan, jebakan |
| [README.md](../README.md) | Cara menjalankan, perintah, struktur modul |
| [architecture/README.md](architecture/README.md) | Indeks + ringkasan eksekutif |
| [01-requirement-analysis.md](architecture/01-requirement-analysis.md) | Proses bisnis, aktor, D1–D8, matriks notifikasi |
| [02-architecture-blueprint.md](architecture/02-architecture-blueprint.md) | Diagram, 12 ADR, matriks permission, state diagram |
| [03-database-schema.md](architecture/03-database-schema.md) | DDL, constraint, 11 skenario uji integritas |
| [04-roadmap.md](architecture/04-roadmap.md) | Status per fase, deployment, risiko |
| [development-setup.md](development-setup.md) | Prasyarat mesin, ekstensi PHP |

Sumber aslinya: `Blueprint Pengembangan Sistem-Stationery_REV1.0` (disetujui 23 Juni 2025,
VP SIT & VP SGA). PDF-nya berisi gambar yang **tidak ikut terekstrak sebagai teks** —
bila perlu meninjau ulang, ekstrak gambarnya lebih dulu.

---

## 10. Checklist Sebelum Menyatakan Sebuah Fase Selesai

```bash
vendor/bin/pint --test                          # format PHP
vendor/bin/phpstan analyse --memory-limit=1G    # analisis statis level 6
vendor/bin/pest                                 # seluruh test
npm run types && npm run lint && npm run format:check && npm run build
php artisan stock:reconcile                     # saldo selaras dengan ledger
```

Lalu, untuk fase yang menyentuh alur bisnis: **jalankan manual di browser dengan akun
peran yang sesuai**, bukan hanya mengandalkan test. Beberapa bug yang ditemukan di proyek
ini (mis. §6.3) hanya muncul lewat jalur HTTP sungguhan.

**Terakhir:** perbarui status fase di `04-roadmap.md` dan `README.md`, lalu perbarui
dokumen ini bila ada keputusan atau jebakan baru.
