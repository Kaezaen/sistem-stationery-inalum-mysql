# Bagian 1 — Analisis Requirement

**Sistem Stationery — PT Indonesia Asahan Aluminium (Inalum)**
Sumber: `Blueprint Pengembangan Sistem-Stationery_REV1.0`, disetujui 23 Juni 2025 (VP SIT & VP SGA)

> Dokumen ini **belum berisi kode**. Tujuannya adalah menyepakati pemahaman requirement sebelum arsitektur dikunci.

---

## 1. Analisis Proses Bisnis

### 1.1 Tujuan Sistem (dari Bab 1 Pendahuluan)

Menstandarisasi proses **pengajuan** dan **verifikasi pembelian** Alat Tulis Kantor (ATK) agar:

| Tujuan | Implikasi Teknis |
|---|---|
| Mengurangi kesalahan | Validasi berlapis, approval berjenjang, tidak ada input stok bebas |
| Mempercepat pengadaan | Notifikasi otomatis ke PIC berikutnya, filter status siap pakai |
| Meningkatkan akurasi data | Stok tidak boleh diedit manual — hanya bergerak lewat transaksi ber-referensi |
| Transparansi | Setiap keputusan approval tercatat beserta pelakunya |
| Pencatatan historis | Ledger transaksi inventory yang immutable |
| Pelaporan real-time | Query agregat + snapshot periodik |

### 1.2 Dua Value Stream Utama

Sistem ini sebenarnya adalah **dua alur berlawanan arah** yang bertemu di satu entitas stok:

```
OUTBOUND (Request-to-Handover)     ── mengurangi stok
User → Pimpinan User → PIC Stationery → Pimpinan SGA → PIC Gudang → Barang diserahkan

INBOUND (Purchase-to-Stock)        ── menambah stok
PIC Gudang (input pembelian) → PIC Stationery (verifikasi) → Stok bertambah
```

Ditambah dua alur pendukung:
- **Master Data Management** — PIC Stationery mengelola katalog item
- **Inventory Visibility** — PIC Stationery & PIC Gudang memantau posisi stok

### 1.3 Rincian Alur Outbound (Bab 3.1 – 3.7)

| # | Tahap | Aktor | Aksi Kunci | Referensi |
|---|---|---|---|---|
| 1 | Buat Request | User/Requester | Pilih item → Tambah → Ajukan Request | 3.1 |
| 2 | Approval L1 | Pimpinan User (MS/VP) | Setujui **atau** Tolak + alasan | 3.2 |
| 3 | Approval L2 | PIC Stationery | **Isi jumlah item yang diberikan/disetujui** per baris + remark, atau Tolak Seluruhnya | 3.3 |
| 4 | Approval L3 | Pimpinan SGA (MS/VP SGA) | Setujui **atau** Tolak + alasan (read-only qty) | 3.4 |
| 5 | Serah Terima | PIC Gudang | Tekan "Diberikan" | 3.5 |
| 6 | Revisi (tolak L1) | **User/Requester** | Revisi item sesuai catatan → Ajukan ulang | 3.6 |
| 7 | Revisi (tolak L3) | **PIC Stationery** | Revisi item sesuai catatan → Ajukan ulang | 3.7 |

**Temuan penting dari General Workflow (Gambar 2.1):**

1. **Approval L2 bersifat kuantitatif, bukan biner.** Diagram menyebut *"Permintaan disetujui / disetujui sebagian?"* dan wireframe 3.3.2 menampilkan kolom `QUANTITY` (diminta) vs `QUANTITY ACTUAL` (dapat di-adjust ±) + `REMARK` per baris item. Ini adalah **partial approval per line item** — bukan approve/reject seluruh dokumen.
2. **Titik balik penolakan berbeda-beda:**
   - Tolak oleh Pimpinan User → notifikasi kembali ke **User** (User yang revisi)
   - Tolak oleh PIC Stationery → notifikasi kembali ke **User**
   - Tolak oleh Pimpinan SGA → notifikasi kembali ke **PIC Stationery** (PIC Stationery yang revisi, bukan User)
3. **Stok fisik baru berpindah pada tahap "Penyerahan barang ke user"** oleh PIC Gudang, bukan saat approval.

### 1.4 Rincian Alur Inbound (Bab 3.9 – 3.10)

| # | Tahap | Aktor | Aksi Kunci |
|---|---|---|---|
| 1 | Input Pembelian | PIC Gudang | No. Pembelian, Tanggal Pembelian, Nama Supplier, pilih item + jumlah → Simpan |
| 2 | Verifikasi | PIC Stationery | Filter "Menunggu Verifikasi" → Diverifikasi **atau** Ditolak + alasan |

**Keputusan yang harus ditegaskan:** stok bertambah **setelah diverifikasi**, bukan saat disimpan. Alasan: jika stok naik saat input, penolakan verifikasi akan memaksa koreksi negatif dan merusak integritas ledger.

### 1.5 Master Data & Inventory (Bab 3.8, 3.11)

- **Tambah Item** (PIC Stationery): `Item Code`, `Item Name`, `UoM`, `Min Stock`, `Max Stock`, `Category`, `Remark`
- **Data Inventory** (PIC Stationery & PIC Gudang): tampilan read-only dengan status stok terhitung

**Aturan status stok** (di-reverse-engineer dari wireframe 3.11.2):

| Contoh | Stock | Min | Max | Status |
|---|---|---|---|---|
| BALL LINER | 15 | 5 | 10 | `Over Stock` |
| PERMANENT MARKER | 3 | 5 | 10 | `Under Stock` |
| ERASER | 7 | 5 | 10 | `Stock On Hand` |

```
stock > max_stock  → OVER_STOCK
stock < min_stock  → UNDER_STOCK
selain itu         → STOCK_ON_HAND
```

---

## 2. Identifikasi Aktor Sistem

| # | Aktor | Sumber | Tanggung Jawab Utama |
|---|---|---|---|
| A1 | **User / Requester** | Lane 1 Gambar 2.1 | Mengajukan request ATK, merevisi bila ditolak Pimpinan User |
| A2 | **Pimpinan User (MS/VP)** | Lane 2 | Approval L1 atas request bawahannya |
| A3 | **PIC Stationery** | Lane 3 | Approval L2 (kuantitatif), kelola master item, verifikasi pembelian, revisi bila ditolak SGA |
| A4 | **Pimpinan SGA (MS/VP SGA)** | Lane 4 | Approval L3 (final) |
| A5 | **PIC Gudang** | Lane 5 | Input pembelian, serah terima barang ke user |
| A6 | **Administrator** | Bab 1 ("PIC atau **Admin** Stationery") | Kelola user, role, mapping atasan, konfigurasi sistem |
| A7 | **System (Scheduler)** | Implisit dari fitur Report & Notifikasi | Snapshot stok bulanan, reminder approval tertunda, notifikasi *Need to Buy* |

**Catatan relasi organisasi:** ERD blueprint memuat `users.manager_id` (self-reference). Ini adalah mekanisme penentuan "Pimpinan User" — approval L1 diarahkan ke atasan langsung requester, bukan ke role global.

> ⚠️ **Inkonsistensi dokumen (perlu konfirmasi):** Bab 1 menyatakan *"permintaan dari PIC Gudang dapat diverifikasi oleh PIC atau Admin Stationery"*, sedangkan seluruh diagram Bab 2–3 menunjukkan permintaan berasal dari **User/Requester**, dan PIC Gudang berperan di sisi pembelian & serah terima. Diagram diperlakukan sebagai sumber kebenaran.

---

## 3. Identifikasi Fitur & Modul

### 3.1 Struktur Menu (diambil langsung dari wireframe)

```
MENU
├── Request Items              → form pengajuan
├── Data Request Items         → daftar request (riwayat)
└── Verify Request Items       → antrian approval (Pending / Approved / Rejected)

Master Data
├── Data List Items            → katalog item
└── Add New Items              → form tambah item

Purchasing
├── Purchasing Items           → form input pembelian
├── Data Purchasing Items      → daftar pembelian
└── Verify Purchasing Items    → antrian verifikasi pembelian

Inventory
└── Data Inventory             → posisi stok + status
```

Menu berikut **belum ada di wireframe** namun diwajibkan oleh Bab 1 (fitur 5, 6, 7) dan harus ditambahkan:

```
Reporting                      → 8 laporan (lihat §10)
Notification                   → inbox notifikasi
Administration                 → user, role, permission, master kategori
Dashboard                      → statistik permintaan (fitur 5: "Pelaporan/Monitoring")
```

### 3.2 Pemetaan ke Modul Teknis

| Modul | Cakupan | Menu Terkait |
|---|---|---|
| **M1 — Identity & Access** | Login, user, role, permission, struktur organisasi, mapping atasan | Administration |
| **M2 — Catalog (Master Data)** | Item, kategori, UoM, min/max stock | Data List Items, Add New Items |
| **M3 — Requisition** | Request, request line, mesin status, revisi | Request Items, Data Request Items |
| **M4 — Approval** | Approval berjenjang generik + riwayat keputusan | Verify Request Items, Verify Purchasing Items |
| **M5 — Fulfillment** | Serah terima barang, pengurangan stok | Verify Request Items (filter Pengambilan) |
| **M6 — Purchasing** | Dokumen pembelian, line pembelian, verifikasi | Purchasing Items, Data/Verify Purchasing Items |
| **M7 — Inventory** | Ledger transaksi, saldo stok, status stok, reservasi | Data Inventory |
| **M8 — Notification** | Notifikasi in-app + email ke PIC terkait | Notification |
| **M9 — Reporting** | 8 laporan + dashboard + export | Reporting, Dashboard |
| **M10 — Audit** | Jejak audit teknis & bisnis | Administration → Audit Log |
| **M11 — Platform (Shared)** | Penomoran dokumen, enum, base classes, helper | — |

---

## 4. Identifikasi Workflow Approval

Terdapat **dua workflow approval yang berbeda karakter**:

### Workflow A — Request Approval (3 level + 1 fulfillment)

| Level | Approver | Sifat Keputusan | Ditolak → kembali ke |
|---|---|---|---|
| L1 | Pimpinan User (atasan langsung requester) | Biner: Setujui / Tolak + alasan | Requester |
| L2 | PIC Stationery | **Kuantitatif per baris** + remark, atau Tolak Seluruhnya | Requester |
| L3 | Pimpinan SGA | Biner: Setujui / Tolak + alasan | PIC Stationery |
| — | PIC Gudang (fulfillment, bukan approval) | Serah terima → stok keluar | — |

### Workflow B — Purchase Verification (1 level)

| Level | Approver | Sifat Keputusan | Ditolak → kembali ke |
|---|---|---|---|
| L1 | PIC Stationery | Biner: Diverifikasi / Ditolak + alasan | PIC Gudang |

### Karakteristik yang harus didukung engine approval

1. **Approver dinamis** (L1 = `users.manager_id`) vs **approver berbasis role** (L2, L3).
2. **Mutasi data saat approval** — L2 mengubah `quantity_actual`, sehingga approval bukan sekadar stempel status.
3. **Rejection routing berbeda** per level.
4. **Idempotency** — satu request tidak boleh di-approve dua kali (race condition antar approver).
5. **Riwayat lengkap** — setiap keputusan tersimpan, termasuk approval yang di-supersede setelah revisi.

---

## 5. Identifikasi Status & Transisi Workflow

### 5.1 Status Request (Header)

| Kode | Label UI (blueprint) | Sumber |
|---|---|---|
| `DRAFT` | Draft | implisit (belum "Ajukan Request") |
| `PENDING_SUPERVISOR` | Pending Approval Pimpinan | wireframe 3.1.2 |
| `REJECTED_SUPERVISOR` | Ditolak Pimpinan | filter di 3.6 |
| `PENDING_STATIONERY` | Pending Approval PIC Stationery | wireframe 3.3.2 |
| `REJECTED_STATIONERY` | Ditolak PIC Stationery | tombol "Ditolak Seluruhnya" 3.3.2 |
| `PENDING_SGA` | Pending Approval Pimpinan SGA | wireframe 3.4.2 |
| `REJECTED_SGA` | Ditolak Pimpinan SGA | filter di 3.7 |
| `READY_FOR_HANDOVER` | Pengambilan Item | wireframe 3.5.2 |
| `COMPLETED` | Selesai / Diberikan | tombol "Diberikan" 3.5.2 |
| `CANCELLED` | Dibatalkan | *usulan tambahan* |

### 5.2 Status Request Item (Baris)

Diperlukan karena approval L2 bersifat per-baris:

| Kode | Arti |
|---|---|
| `REQUESTED` | Kuantitas awal dari requester |
| `APPROVED` | `quantity_actual == quantity_requested` |
| `PARTIALLY_APPROVED` | `0 < quantity_actual < quantity_requested` |
| `REJECTED` | `quantity_actual == 0` |
| `ISSUED` | Sudah diserahkan PIC Gudang |

### 5.3 Status Purchase

| Kode | Label UI |
|---|---|
| `DRAFT` | Draft |
| `PENDING_VERIFICATION` | Pending Approval / Menunggu Verifikasi |
| `VERIFIED` | Diverifikasi → **stok bertambah di sini** |
| `REJECTED` | Ditolak |

### 5.4 Status Inventory (Terhitung, bukan disimpan)

`OVER_STOCK` · `UNDER_STOCK` · `STOCK_ON_HAND`

> Status ini **tidak disimpan sebagai kolom** karena merupakan turunan murni dari `stock_quantity`, `min_stock`, `max_stock`. Menyimpannya akan menimbulkan risiko data basi. ERD blueprint memuat `items.status` — akan diklarifikasi sebagai *status aktif/non-aktif item*, bukan status stok.

*(Diagram state lengkap ada di Bagian 2 — Architecture Blueprint.)*

---

## 6. Identifikasi Kebutuhan Database

### 6.1 Entitas dari ERD Blueprint (Bab 4)

| Entitas | Field Kunci di ERD |
|---|---|
| `Users` | user_id, username, full_name, email, department, role, **manager_id** |
| `Roles` | role_id, role_name, description |
| `Requests` | request_id, user_id, request_date, status, **current_approval_level**, notes |
| `Request Items` | request_item_id, request_id, item_id, quantity_requested, **quantity_approved**, **quantity_actual**, status |
| `Items` | item_id, item_name, description, category, unit, stock_quantity, min_stock, max_stock, status |
| `Approvals` | approval_id, request_id, approver_id, approval_level, status, approval_date, rejection_notes |
| `Purchases` | purchase_id, purchase_number, purchase_date, supplier_name, created_by, verified_by, verification_date, status, notes |
| `Purchase Items` | purchase_item_id, purchase_id, item_id, quantity, unit_price, total_price |
| `Inventory Transactions` | transaction_id, item_id, request_id, purchase_id, quantity, transaction_type, transaction_date, performed_by, adjustment_reason |

### 6.2 Kesenjangan yang Teridentifikasi (perlu ditambahkan)

| # | Kebutuhan | Alasan |
|---|---|---|
| G1 | Tabel `departments` (seksi) | ERD menyimpan `department` sebagai teks bebas. Wireframe menampilkan "Departement/Seksi: SIT", dan laporan *Request by Account* butuh pengelompokan yang konsisten. Teks bebas mustahil diagregasi dengan andal. |
| G2 | Tabel `categories` | Wireframe punya 6 kategori tetap (Stationeries, Drink & Sugar, Disinfectant, Daily Necessities, Office Tool, Print Expense) + filter "All". Perlu master, bukan string. |
| G3 | Tabel `uoms` (opsional) | Form "Add New Items" memakai input bebas; wireframe inventory menampilkan `EACH`. Master UoM mencegah varian ejaan. |
| G4 | `item_code` UNIQUE | Wireframe menampilkan kode sebagai identitas bisnis (mis. `1709000002`). ERD tidak menyebut constraint. |
| G5 | Tabel `document_sequences` | Format `REQ001` butuh generator nomor anti-duplikat. |
| G6 | Kolom `quantity_before` / `quantity_after` di ledger | Auditability — memungkinkan rekonstruksi saldo & deteksi drift. |
| G7 | Tabel `audit_logs` | Bab 1 mensyaratkan "pencatatan historis" & transparansi. |
| G8 | Tabel `notifications` | Fitur 6 (notifikasi ke PIC terkait). |
| G9 | Tabel `stock_monthly_snapshots` | Laporan *Stock by Month* / *Stock by Year* butuh saldo akhir periode; menghitung ulang dari ledger tiap kali akan lambat seiring waktu. |
| G10 | Kolom `reserved_quantity` di `items` | Mencegah stok yang sudah disetujui L2/L3 "dijual dua kali" sebelum diserahkan. Lihat §6.3. |
| G11 | Polymorphic pada `approvals` | Approval dipakai oleh Request **dan** Purchase. ERD hanya mengikat ke `request_id`. |
| G12 | Soft delete pada master data | Item yang pernah dipakai transaksi tidak boleh dihapus permanen. |

### 6.3 Risiko Konkurensi Stok (harus diputuskan)

Terdapat celah waktu antara **PIC Stationery menyetujui kuantitas** dan **PIC Gudang menyerahkan barang** (melewati approval SGA). Dalam celah ini, request lain dapat disetujui atas stok fisik yang sama.

**Skenario kegagalan:** stok BALL LINER = 10. Request A disetujui 10, Request B disetujui 10. Keduanya lolos SGA. PIC Gudang hanya bisa memenuhi satu — sistem menampilkan janji yang tidak bisa ditepati, dan stok berpotensi menjadi negatif.

**Rekomendasi:** terapkan `reserved_quantity`. Stok tersedia = `stock_quantity - reserved_quantity`. Reservasi dibuat saat approval L2 dan dilepas saat serah terima atau penolakan. (Detail di Bagian 2 §ADR-07.)

### 6.4 Karakteristik Non-Fungsional Database

- **Engine:** PostgreSQL (wajib)
- **Volume estimasi:** ATK internal satu perusahaan → puluhan ribu baris/tahun. Bukan big data; indeks konvensional cukup.
- **Integritas:** foreign key aktif, `CHECK` constraint untuk kuantitas non-negatif
- **Presisi uang:** `numeric(18,2)` — jangan `float`
- **Timezone:** simpan `timestamptz`, tampilkan Asia/Jakarta

---

## 7. Identifikasi Kebutuhan Role & Permission

### 7.1 Role Sistem

| Role | Slug | Keterangan |
|---|---|---|
| Requester | `requester` | Role dasar seluruh pegawai |
| Pimpinan User | `supervisor` | Diberikan ke MS/VP yang memiliki bawahan |
| PIC Stationery | `pic_stationery` | Penjaga master data & kuantitas |
| Pimpinan SGA | `sga_manager` | Approver final |
| PIC Gudang | `warehouse_officer` | Pembelian & serah terima |
| Administrator | `administrator` | Superuser aplikasi |

### 7.2 Prinsip Otorisasi

Otorisasi sistem ini **tidak cukup berbasis role saja**. Diperlukan dua lapis:

1. **Lapis Permission (statis)** — "Apakah role ini boleh mengakses fitur approve request?"
2. **Lapis Policy (kontekstual)** — "Apakah *user ini* adalah atasan dari requester *request ini*, dan apakah request sedang berada di status yang tepat?"

Tanpa lapis kedua, seorang Pimpinan dari seksi lain dapat menyetujui request yang bukan wewenangnya. Ini adalah **kebutuhan keamanan wajib**, bukan opsional.

*(Matriks lengkap ada di Bagian 2.)*

---

## 8. Identifikasi Kebutuhan Audit Trail

Dua jenis jejak yang **harus dipisahkan**, karena berbeda audiens dan siklus hidup:

### 8.1 Business Audit Trail — tabel `approvals`

Riwayat keputusan yang bermakna bisnis dan **ditampilkan ke pengguna** (timeline approval pada detail request).

Merekam: siapa memutuskan, level berapa, hasil apa, kapan, alasan penolakan, dan snapshot kuantitas saat keputusan diambil.

### 8.2 Technical Audit Trail — tabel `audit_logs`

Perubahan data granular untuk keperluan investigasi/kepatuhan, **tidak ditampilkan di UI operasional**.

Wajib merekam minimal:

| Kejadian | Alasan |
|---|---|
| Perubahan `items.min_stock` / `max_stock` | Memengaruhi laporan *Need to Buy* |
| Perubahan `quantity_actual` oleh PIC Stationery | Titik paling rawan sengketa |
| Setiap mutasi stok | Sudah tercakup `inventory_transactions` (immutable) |
| Perubahan role/permission user | Keamanan |
| Login berhasil & gagal | Keamanan |
| Penghapusan (soft delete) master data | Kepatuhan |

**Prinsip:** `inventory_transactions` bersifat **append-only** — tidak ada UPDATE/DELETE. Koreksi dilakukan dengan transaksi `ADJUSTMENT` yang berlawanan arah, disertai `adjustment_reason`. Ini menjaga ledger tetap dapat direkonsiliasi.

---

## 9. Identifikasi Kebutuhan Notifikasi

Fitur 6: *"Notifikasi ke PIC terkait"*. Diagram 2.1 memperlihatkan node **Notifikasi** secara eksplisit pada tiga titik penolakan.

### 9.1 Matriks Notifikasi

| # | Pemicu | Penerima | Kanal | Prioritas |
|---|---|---|---|---|
| N1 | Request diajukan | Pimpinan User (atasan requester) | In-app + Email | Wajib |
| N2 | Disetujui Pimpinan User | PIC Stationery | In-app + Email | Wajib |
| N3 | **Ditolak Pimpinan User** | Requester | In-app + Email | Wajib (di diagram) |
| N4 | Disetujui PIC Stationery | Pimpinan SGA | In-app + Email | Wajib |
| N5 | **Ditolak PIC Stationery** | Requester | In-app + Email | Wajib (di diagram) |
| N6 | Disetujui Pimpinan SGA | PIC Gudang + Requester | In-app + Email | Wajib |
| N7 | **Ditolak Pimpinan SGA** | PIC Stationery | In-app + Email | Wajib (di diagram) |
| N8 | Barang diserahkan | Requester | In-app | Wajib |
| N9 | Pembelian diinput | PIC Stationery | In-app + Email | Wajib |
| N10 | Pembelian diverifikasi/ditolak | PIC Gudang | In-app + Email | Wajib |
| N11 | Stok mencapai `min_stock` | PIC Stationery + PIC Gudang | In-app + Email | Turunan *Need to Buy* |
| N12 | Approval tertunda > N hari | Approver + atasannya | Email | Usulan (SLA) |

### 9.2 Kebutuhan Teknis

- Notifikasi harus **asinkron** (queue) agar tidak memperlambat transaksi approval
- Notifikasi harus dikirim **setelah** transaksi database commit — bukan di dalamnya
- Perlu preferensi per-user (opt-out email) — *nice to have*

---

## 10. Identifikasi Kebutuhan Reporting

Delapan laporan wajib dari Bab 1 fitur 7:

| # | Laporan | Sumber Data | Dimensi | Catatan Teknis |
|---|---|---|---|---|
| R1 | **Stock by Month** | snapshot bulanan + ledger | Item × Bulan | Butuh saldo awal, masuk, keluar, saldo akhir |
| R2 | **Stock by Year** | agregasi R1 | Item × Tahun | Turunan R1 |
| R3 | **Purchasing** | `purchases` + `purchase_items` | Periode, supplier, item | Hanya status `VERIFIED` |
| R4 | **Request by Month** | `requests` | Bulan × status | |
| R5 | **Request by Year** | `requests` | Tahun × status | |
| R6 | **Request by Account** | `requests` × `departments` | Departemen/Seksi (atau kode akun) | ⚠️ butuh klarifikasi — lihat catatan |
| R7 | **Request by Item** | `request_items` | Item, qty diminta vs diserahkan | Sangat berguna untuk forecasting |
| R8 | **Need to Buy** | `items` | Item dengan `stock < min_stock` | Usulan kuantitas beli = `max_stock - stock` |

Ditambah **fitur 5 — Pelaporan/Monitoring**: dashboard statistik permintaan (jumlah request per status, tren, top item).

### 10.1 Kebutuhan Lintas Laporan
- Filter periode (dari–sampai), kategori, departemen, item
- Export **Excel** dan **PDF** (standar pelaporan internal korporat)
- Laporan berbasis **tanggal transaksi**, bukan `created_at`

> ⚠️ **Perlu klarifikasi — "Account" pada laporan R6.** Istilah ini ambigu: dapat berarti (a) **kode akun GL/cost center** — didukung fakta bahwa `item_code` di wireframe (`1709000002`) menyerupai nomor akun, atau (b) **akun/user pemohon**, atau (c) **departemen/seksi**. Interpretasi ini menentukan apakah `departments` perlu kolom `account_code`. **Dijadikan pertanyaan terbuka Q3.**

---

## 11. Keputusan Desain (sebelumnya Pertanyaan Terbuka)

> **Status: DIKUNCI — disetujui 6 Agustus 2026.** Asumsi kerja hasil analisis diadopsi sebagai keputusan resmi dan menjadi landasan implementasi. Bila kelak pemilik proses bisnis mengoreksi salah satu keputusan, kolom **Dampak Perubahan** menunjukkan biaya revisinya.

| # | Keputusan | Dasar | Dampak Perubahan |
|---|---|---|---|
| **D1** | Request yang **Ditolak PIC Stationery** bersifat **final/terminal** — tidak dapat direvisi. Requester membuat request baru. | Bab 3.6 & 3.7 hanya mengatur revisi untuk penolakan Pimpinan User & SGA. | **Rendah.** Menambah transisi `REJECTED_STATIONERY → PENDING_STATIONERY` di `RequestWorkflow` + satu Policy. |
| **D2** | **Pimpinan SGA berbasis role**, satu jabatan untuk seluruh perusahaan — bukan per unit. | Tidak ada indikasi pemecahan per unit di blueprint. | **Sedang.** Bila per unit, approver L3 harus di-resolve dari `departments`, seperti L1. |
| **D3** | "Account" pada *Request by Account* = **Departemen/Seksi**. Kolom `departments.account_code` disediakan sejak awal. | Wireframe menampilkan "Departement/Seksi: SIT" sebagai identitas requester. | **Rendah.** Kolom sudah ada; hanya mengubah `GROUP BY` pada query laporan. |
| **D4** | `unit_price` & `total_price` pembelian **nullable** — kolom disiapkan di skema, **disembunyikan di UI Fase 1**. | ERD memuatnya, wireframe 3.9.2 tidak menampilkannya. | **Rendah.** Menampilkan field di UI + mengisi data lama. Tidak perlu migrasi. |
| **D5** | Bila stok tidak cukup saat serah terima, PIC Gudang **boleh menyerahkan sebagian**; selisih dicatat pada remark. | Konsekuensi wajar dari `quantity_actual` yang terpisah dari `quantity_approved`. | — |
| **D6** | **Tanpa delegasi approval** di Fase 1. Struktur data disiapkan agar penambahannya tidak memerlukan migrasi besar. | Tidak disebut di blueprint. | **Sedang.** Perlu tabel `approval_delegations` + perubahan resolusi approver. |
| **D7** | User **boleh** meminta item lintas kategori dalam satu dokumen. | Filter kategori di wireframe bersifat bantuan pencarian, bukan pembatas. | — |
| **D8** | **Tanpa purge** — data historis dipertahankan penuh. | Tuntutan "pencatatan historis" pada Bab 1. | **Rendah.** `notifications` & `audit_logs` adalah kandidat arsip pertama bila volume jadi masalah. |

**Konsekuensi D1 terhadap state machine:** `REJECTED_STATIONERY` adalah state terminal. Diagram §6 Bagian 2 sudah mencerminkan hal ini.

**Konsekuensi D2 terhadap otorisasi:** approval L3 memakai pengecekan permission murni (`request.approve.l3`), tanpa lapis Policy kontekstual seperti L1. Ini menyederhanakan `RequestPolicy` — namun bila D2 berubah, L3 harus mengikuti pola L1.

---

**Lanjut ke:** [Bagian 2 — Software Architecture Blueprint](02-architecture-blueprint.md)
