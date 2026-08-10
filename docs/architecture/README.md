# Software Architecture Blueprint — Sistem Stationery Inalum

Dokumen arsitektur untuk pengembangan **Sistem Stationery PT Indonesia Asahan Aluminium**, disusun dari `Blueprint Pengembangan Sistem-Stationery_REV1.0` (disetujui 23 Juni 2025, VP SIT & VP SGA).

> **Status: MENUNGGU PERSETUJUAN.** Belum ada kode aplikasi yang ditulis. Implementasi dimulai setelah dokumen ini disetujui.

---

> **Melanjutkan di sesi baru?** Mulai dari [../HANDOVER.md](../HANDOVER.md) — memuat
> keputusan terkunci, konvensi wajib, dan jebakan yang sudah ditemukan.

## Daftar Dokumen

| # | Dokumen | Isi |
|---|---|---|
| 0 | [Serah Terima Konteks](../HANDOVER.md) | Posisi kerja, keputusan, jebakan, langkah berikutnya |
| 1 | [Analisis Requirement](01-requirement-analysis.md) | Proses bisnis, aktor, fitur/modul, workflow approval, status & transisi, kebutuhan database, role & permission, audit trail, notifikasi, reporting |
| 2 | [Architecture Blueprint](02-architecture-blueprint.md) | System Context, Module Breakdown, Domain Model, ERD, Role Permission Matrix, State Diagram Request & Purchasing, Inventory Flow, 12 ADR, Folder Structure |
| 3 | [Database Schema Draft](03-database-schema.md) | DDL PostgreSQL 17 tabel + constraint, indeks, skenario uji integritas |
| 4 | [Development Roadmap](04-roadmap.md) | 10 fase / 15 sprint, arsitektur deployment non-Docker, risiko proyek |

---

## Tech Stack

| Lapis | Teknologi |
|---|---|
| Backend | Laravel 12 · PHP 8.4 |
| Frontend | React 19 · TypeScript · InertiaJS 2 |
| UI | TailwindCSS · shadcn/ui |
| Database | PostgreSQL 16+ |
| Auth | Laravel Built-in Auth + RBAC (`spatie/laravel-permission`) |
| Arsitektur | Monolith Modular · Service Layer Pattern |
| Deployment | Standard Laravel — Nginx + PHP-FPM + Supervisor + Cron (**tanpa Docker**) |

---

## Lima Hal Terpenting untuk Dipahami

### 1. Approval PIC Stationery bersifat kuantitatif, bukan biner
Level 2 **mengubah data** — mengisi `quantity_actual` dan `remark` per baris item (partial approval), bukan sekadar menyetujui/menolak dokumen. Ini terlihat dari wireframe 3.3.2 dan diagram *"Permintaan disetujui / disetujui sebagian?"*.

### 2. Penolakan SGA kembali ke PIC Stationery, bukan ke Requester
Tiga titik penolakan punya tujuan berbeda:

| Ditolak oleh | Yang merevisi |
|---|---|
| Pimpinan User | **Requester** (Bab 3.6) |
| PIC Stationery | Requester (notifikasi) |
| Pimpinan SGA | **PIC Stationery** (Bab 3.7) |

### 3. Stok hanya bergerak di dua titik
- **Masuk:** saat pembelian **diverifikasi** PIC Stationery — bukan saat diinput
- **Keluar:** saat barang **diserahkan** PIC Gudang — bukan saat disetujui

Semua mutasi melewati `StockService` dan meninggalkan jejak di ledger `inventory_transactions` yang bersifat append-only.

### 4. Reservasi stok menutup celah alokasi ganda
Antara approval PIC Stationery dan serah terima terdapat jeda (melewati approval SGA). Tanpa reservasi, dua request dapat disetujui atas stok fisik yang sama. Lihat **ADR-07**.

### 5. Permission saja tidak cukup untuk keamanan
Seorang Pimpinan hanya boleh menyetujui request **bawahan langsungnya**. Aturan kontekstual seperti ini ditegakkan Laravel Policy, bukan permission RBAC. Lihat **§5.2**.

---

## Keputusan Desain (D1–D8)

**Dikunci 6 Agustus 2026** — asumsi hasil analisis diadopsi sebagai keputusan resmi dan menjadi landasan implementasi. Rincian dasar & dampak perubahan di [Analisis §11](01-requirement-analysis.md#11-keputusan-desain-sebelumnya-pertanyaan-terbuka).

| # | Keputusan |
|---|---|
| D1 | Request yang ditolak PIC Stationery bersifat **final** — requester membuat request baru |
| D2 | Pimpinan SGA **berbasis role**, satu jabatan untuk seluruh perusahaan |
| D3 | "Account" pada *Request by Account* = **Departemen/Seksi** |
| D4 | `unit_price` pembelian **nullable**, disembunyikan di UI Fase 1 |
| D5 | Serah terima **sebagian diperbolehkan** bila stok kurang |
| D6 | **Tanpa delegasi approval** di Fase 1 |
| D7 | Request **lintas kategori** dalam satu dokumen diperbolehkan |
| D8 | **Tanpa purge** — data historis dipertahankan penuh |

Keputusan ini masih dapat dikoreksi pemilik proses bisnis. Kolom *Dampak Perubahan* di Analisis §11 menunjukkan biaya revisi masing-masing — seluruhnya Rendah–Sedang, tidak ada yang memerlukan pembongkaran arsitektur.

---

## Ringkasan Timeline

**± 30 minggu (7 bulan)** hingga go-live dengan tim 1 Tech Lead + 2 Fullstack + 1 QA paruh waktu.

Dua sprint yang **tidak boleh dipangkas**: Fase 3 (Inventory Core) dan Fase 5 (Requisition & Approval). Rincian dan opsi percepatan ada di [Roadmap](04-roadmap.md#ringkasan-timeline).
