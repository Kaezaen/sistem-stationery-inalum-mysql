# Setup Environment Development Lokal

Panduan menyiapkan mesin developer untuk proyek Sistem Stationery. Untuk deployment produksi, lihat [Roadmap §Arsitektur Deployment](architecture/04-roadmap.md#arsitektur-deployment-non-docker).

---

## 1. Versi yang Diwajibkan

| Komponen | Versi Wajib | Catatan |
|---|---|---|
| PHP | **8.4.x** | Diverifikasi pada 8.4.12 |
| Laravel | **12.x** | **Harus dipin** — lihat §3 |
| Node.js | 20 LTS atau lebih baru | Diverifikasi pada 22.19.0 |
| Composer | 2.x | Diverifikasi pada 2.9.1 |
| PostgreSQL | **16+** | Wajib — lihat §4 |

---

## 2. Ekstensi PHP

Ekstensi berikut **wajib aktif**. Instalasi PHP di Windows umumnya menonaktifkan sebagian besar secara default.

| Ekstensi | Dipakai untuk |
|---|---|
| `pdo_pgsql` | Koneksi PostgreSQL — **tanpa ini aplikasi tidak jalan** |
| `pgsql` | Fungsi native PostgreSQL |
| `mbstring` | Manipulasi string multibyte |
| `bcmath` | Perhitungan uang (`unit_price`, `total_price`) |
| `intl` | Format tanggal & angka lokal Indonesia |
| `gd` | Generasi gambar untuk export PDF |
| `zip` | Export Excel (Laravel Excel) |
| `fileinfo`, `openssl`, `curl` | Bawaan Laravel |
| `pdo_sqlite` | Test cepat (opsional — lihat peringatan §4) |

### Cara mengaktifkan (Windows)

Buka `php.ini` (lokasi: jalankan `php --ini`), hilangkan tanda `;` di depan baris berikut:

```ini
extension=gd
extension=intl
extension=pdo_pgsql
extension=pdo_sqlite
extension=pgsql
```

Pastikan juga `extension_dir` menunjuk ke folder `ext` yang benar:

```ini
extension_dir = "C:\php8.4\ext"
```

Verifikasi:

```bash
php -m
```

> **Sudah dikerjakan pada mesin ini** (6 Agustus 2026): `gd`, `intl`, `pdo_pgsql`, `pdo_sqlite`, dan `pgsql` diaktifkan di `C:\php8.4\php.ini`. Perubahan bersifat aditif — tidak memengaruhi proyek PHP lain di mesin yang sama.

---

## 3. Laravel Harus Dipin ke Versi 12

`composer create-project laravel/laravel` **tanpa versi** akan menarik rilis terbaru — saat ini **Laravel 13**, yang melanggar ketentuan tech stack proyek ini.

Gunakan versi yang dipin:

```bash
composer create-project "laravel/laravel:^12.0" nama-folder
```

Verifikasi setelah instalasi:

```bash
php artisan --version
```

Harus menampilkan `Laravel Framework 12.x.x`. Selain itu, `composer.json` harus memuat `"laravel/framework": "^12.0"`.

> **Mengapa dipin:** ketentuan proyek mewajibkan Laravel 12 + PHP 8.4. Laravel 13 membawa perubahan breaking pada beberapa API dan belum menjadi baseline yang disepakati untuk sistem ini. Constraint `^12.0` tetap menerima patch keamanan 12.x.

---

## 4. PostgreSQL

Skema database proyek ini **bergantung pada fitur khusus PostgreSQL** dan tidak dapat digantikan SQLite untuk pengembangan serius:

| Fitur | Dipakai di |
|---|---|
| Partial index (`WHERE ...`) | `uq_sr_active`, `idx_items_need_to_buy`, `idx_notif_unread` |
| Ekstensi `pg_trgm` + GIN index | Pencarian item (`ILIKE '%kata%'`) |
| Tipe `jsonb` | `approvals.snapshot`, `audit_logs.old_values` |
| Tipe `inet` | `audit_logs.ip_address` |
| `SELECT ... FOR UPDATE` | Penguncian baris saat mutasi stok (ADR-08) |
| `CHECK` constraint kompleks | Seluruh invariant domain |

> ⚠️ **SQLite tidak memadai.** SQLite mengabaikan sebagian `CHECK` constraint, tidak punya `jsonb`/`inet`/`pg_trgm`, dan penguncian barisnya berbeda total. Uji konkurensi stok (T1, T8) **tidak akan valid** di SQLite — padahal justru itu pengujian terpenting dalam sistem ini.

### Setelah PostgreSQL terpasang

```sql
CREATE DATABASE taajri_stationery;
CREATE DATABASE taajri_stationery_test;
CREATE USER taajri WITH PASSWORD 'ganti_password_ini';
GRANT ALL PRIVILEGES ON DATABASE taajri_stationery      TO taajri;
GRANT ALL PRIVILEGES ON DATABASE taajri_stationery_test TO taajri;
```

Ekstensi `pg_trgm` diaktifkan lewat migration, bukan manual.

Konfigurasi `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=taajri_stationery
DB_USERNAME=taajri
DB_PASSWORD=ganti_password_ini
```

**Database test dibuat terpisah** agar `php artisan test` tidak menghapus data pengembangan.

### Sebelum PostgreSQL siap

`SESSION_DRIVER` dan `CACHE_STORE` bernilai `database` agar sama dengan produksi. Konsekuensinya, `php artisan serve` akan menampilkan *connection refused* sebelum PostgreSQL berjalan dan migration dijalankan.

Bila perlu menjalankan aplikasi lebih dulu tanpa database, ubah **`.env` lokal saja** (jangan `.env.example`):

```dotenv
SESSION_DRIVER=file
CACHE_STORE=file
```

Kembalikan ke `database` setelah PostgreSQL siap, agar perilaku lokal tidak menyimpang dari produksi.

---

## 5. Status Mesin Ini

| Prasyarat | Status |
|---|---|
| PHP 8.4.12 | ✅ Terpasang |
| Ekstensi PHP wajib | ✅ Diaktifkan 6 Agu 2026 |
| Composer 2.9.1 | ✅ Terpasang |
| Node 22.19.0 + npm 10.9.3 | ✅ Terpasang |
| Laravel 12 | ✅ Ter-scaffold |
| **PostgreSQL 16+** | ❌ **Belum terpasang** — tidak ada listener di port 5432 |

**PostgreSQL adalah satu-satunya prasyarat yang belum terpenuhi.** Fase 0 (fondasi proyek) dapat diselesaikan tanpanya, namun Fase 1 dan seterusnya membutuhkan koneksi database aktif untuk migration, seeder, dan pengujian.
