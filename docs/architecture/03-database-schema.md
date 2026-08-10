# Bagian 3 — Database Schema Draft

**PostgreSQL 16+** · Draft skema untuk ditinjau sebelum migration ditulis.

> Ini adalah **spesifikasi skema**, bukan kode aplikasi. DDL ditulis untuk memperjelas tipe, constraint, dan indeks yang disepakati. Migration Laravel akan dibuat setelah dokumen ini disetujui.

**Konvensi:**
- **Nama constraint mengikuti Laravel**, bukan nama pada DDL di bawah. Migration menghasilkan `<tabel>_<kolom>_foreign`, `<tabel>_<kolom>_unique`, `<tabel>_<kolom>_index`. Nama eksplisit pada DDL ini hanya berlaku untuk constraint yang memang dibuat lewat `DB::statement()` — mis. `chk_users_not_own_manager`. DDL di bawah adalah **spesifikasi bentuk**, bukan skrip yang dijalankan apa adanya.
- PK `bigserial`, FK `bigint`
- Timestamp `timestamptz` (disimpan UTC, ditampilkan Asia/Jakarta)
- Status: `varchar` + `CHECK` (ADR-09), bukan tipe ENUM native
- Uang: `numeric(18,2)` — tidak pernah `float`
- Master data yang pernah dipakai transaksi: `deleted_at` (soft delete)
- Semua tabel transaksional: `created_at`, `updated_at`

---

## 1. Modul Identity & Access

```sql
CREATE TABLE departments (
    id              bigserial PRIMARY KEY,
    code            varchar(20)  NOT NULL UNIQUE,       -- 'SIT', 'SGA'
    name            varchar(150) NOT NULL,
    account_code    varchar(30),                        -- Report by Account (Q3)
    parent_id       bigint REFERENCES departments(id),
    head_user_id    bigint,                             -- FK ditambah setelah users
    is_active       boolean NOT NULL DEFAULT true,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_departments_parent ON departments(parent_id);

CREATE TABLE users (
    id              bigserial PRIMARY KEY,
    employee_id     varchar(30)  NOT NULL UNIQUE,       -- NIP
    username        varchar(50)  NOT NULL UNIQUE,
    name            varchar(150) NOT NULL,
    email           varchar(150) NOT NULL UNIQUE,
    email_verified_at timestamptz,
    password        varchar(255) NOT NULL,
    department_id   bigint NOT NULL REFERENCES departments(id),
    position        varchar(50),                        -- 'STAFF' | 'MS' | 'VP'
    manager_id      bigint REFERENCES users(id),        -- penentu approver L1
    is_active       boolean NOT NULL DEFAULT true,
    last_login_at   timestamptz,
    remember_token  varchar(100),
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    deleted_at      timestamptz,

    CONSTRAINT chk_users_not_own_manager CHECK (manager_id IS DISTINCT FROM id)
);
CREATE INDEX idx_users_manager     ON users(manager_id);
CREATE INDEX idx_users_department  ON users(department_id);
CREATE INDEX idx_users_active      ON users(is_active) WHERE deleted_at IS NULL;

ALTER TABLE departments
    ADD CONSTRAINT fk_departments_head FOREIGN KEY (head_user_id) REFERENCES users(id);
```

**Tabel RBAC** mengikuti skema `spatie/laravel-permission` (ADR-06): `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`. Tidak diulang di sini karena dibuat oleh migration paket.

> **Catatan `chk_users_not_own_manager`:** mencegah user menjadi atasan dirinya sendiri — yang akan membuat approval L1 tak pernah bisa diselesaikan orang lain. Siklus lebih panjang (A→B→A) tidak dapat dicegah oleh CHECK dan divalidasi di `UserService`.

---

## 2. Modul Catalog

```sql
CREATE TABLE categories (
    id          bigserial PRIMARY KEY,
    code        varchar(30)  NOT NULL UNIQUE,
    name        varchar(100) NOT NULL,
    is_active   boolean NOT NULL DEFAULT true,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now()
);
-- Seed dari wireframe: Stationeries, Drink & Sugar, Disinfectant,
--                      Daily Necessities, Office Tool, Print Expense

CREATE TABLE uoms (
    id          bigserial PRIMARY KEY,
    code        varchar(20) NOT NULL UNIQUE,            -- 'EACH', 'BOX', 'PACK'
    name        varchar(50) NOT NULL,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE items (
    id                bigserial PRIMARY KEY,
    item_code         varchar(30)  NOT NULL UNIQUE,     -- '1709000002'
    item_name         varchar(200) NOT NULL,
    description       text,
    category_id       bigint NOT NULL REFERENCES categories(id),
    uom_id            bigint NOT NULL REFERENCES uoms(id),

    stock_quantity    integer NOT NULL DEFAULT 0,       -- HANYA ditulis StockService
    reserved_quantity integer NOT NULL DEFAULT 0,       -- ADR-07
    min_stock         integer NOT NULL DEFAULT 0,
    max_stock         integer NOT NULL DEFAULT 0,

    remark            text,
    is_active         boolean NOT NULL DEFAULT true,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now(),
    deleted_at        timestamptz,

    CONSTRAINT chk_items_stock_non_negative    CHECK (stock_quantity    >= 0),
    CONSTRAINT chk_items_reserved_non_negative CHECK (reserved_quantity >= 0),
    CONSTRAINT chk_items_reserved_le_stock     CHECK (reserved_quantity <= stock_quantity),
    CONSTRAINT chk_items_min_le_max            CHECK (min_stock <= max_stock)
);
CREATE INDEX idx_items_category ON items(category_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_items_active   ON items(is_active)   WHERE deleted_at IS NULL;

-- Pencarian item (wireframe "Search Items") — trigram untuk ILIKE '%kata%'
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX idx_items_name_trgm ON items USING gin (item_name gin_trgm_ops);
CREATE INDEX idx_items_code_trgm ON items USING gin (item_code gin_trgm_ops);

-- Report "Need to Buy" — indeks parsial, hanya baris yang relevan
CREATE INDEX idx_items_need_to_buy ON items(id)
    WHERE deleted_at IS NULL AND is_active AND stock_quantity < min_stock;
```

**Empat `CHECK` pada `items` adalah jaring pengaman terakhir** (Blueprint §8.1 aturan 5). Bila ada bug di lapisan aplikasi yang menyebabkan stok negatif atau reservasi melebihi stok, database menolak transaksi alih-alih menyimpan data rusak secara diam-diam.

---

## 3. Modul Requisition

```sql
CREATE TABLE requests (
    id                     bigserial PRIMARY KEY,
    request_number         varchar(20) NOT NULL UNIQUE,  -- 'REQ001'
    requester_id           bigint NOT NULL REFERENCES users(id),
    department_id          bigint NOT NULL REFERENCES departments(id),  -- snapshot
    request_date           date NOT NULL,
    status                 varchar(30) NOT NULL,
    current_approval_level smallint NOT NULL DEFAULT 0,
    notes                  text,
    revision_count         smallint NOT NULL DEFAULT 0,
    submitted_at           timestamptz,
    completed_at           timestamptz,
    created_at             timestamptz NOT NULL DEFAULT now(),
    updated_at             timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT chk_requests_status CHECK (status IN (
        'DRAFT',
        'PENDING_SUPERVISOR',  'REJECTED_SUPERVISOR',
        'PENDING_STATIONERY',  'REJECTED_STATIONERY',
        'PENDING_SGA',         'REJECTED_SGA',
        'READY_FOR_HANDOVER',
        'COMPLETED', 'CANCELLED'
    ))
);
CREATE INDEX idx_requests_requester  ON requests(requester_id);
CREATE INDEX idx_requests_status     ON requests(status);
CREATE INDEX idx_requests_department ON requests(department_id);
CREATE INDEX idx_requests_date       ON requests(request_date);
-- Antrian approval (layar Verify Request Items) — indeks komposit
CREATE INDEX idx_requests_queue      ON requests(status, request_date DESC);

CREATE TABLE request_items (
    id                 bigserial PRIMARY KEY,
    request_id         bigint NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
    item_id            bigint NOT NULL REFERENCES items(id),
    quantity_requested integer NOT NULL,
    quantity_approved  integer,        -- diisi PIC Stationery (L2)
    quantity_actual    integer,        -- diserahkan PIC Gudang
    remark             text,
    status             varchar(30) NOT NULL DEFAULT 'REQUESTED',
    created_at         timestamptz NOT NULL DEFAULT now(),
    updated_at         timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT chk_ri_qty_requested_positive CHECK (quantity_requested > 0),
    CONSTRAINT chk_ri_qty_approved_range
        CHECK (quantity_approved IS NULL
               OR (quantity_approved >= 0 AND quantity_approved <= quantity_requested)),
    -- quantity_approved WAJIB sudah terisi sebelum quantity_actual boleh diisi.
    -- Tanpa klausa "quantity_approved IS NOT NULL", perbandingan terhadap NULL
    -- menghasilkan NULL — dan PostgreSQL menganggap CHECK bernilai NULL sebagai LULUS,
    -- sehingga PIC Gudang bisa menyerahkan barang yang belum disetujui PIC Stationery.
    CONSTRAINT chk_ri_qty_actual_range
        CHECK (quantity_actual IS NULL
               OR (quantity_approved IS NOT NULL
                   AND quantity_actual >= 0
                   AND quantity_actual <= quantity_approved)),
    CONSTRAINT chk_ri_status CHECK (status IN (
        'REQUESTED','APPROVED','PARTIALLY_APPROVED','REJECTED','ISSUED'
    )),
    CONSTRAINT uq_request_item UNIQUE (request_id, item_id)
);
CREATE INDEX idx_request_items_request ON request_items(request_id);
CREATE INDEX idx_request_items_item    ON request_items(item_id);
```

**Keputusan penting pada `request_items`:**

| Constraint | Alasan |
|---|---|
| `uq_request_item (request_id, item_id)` | Mencegah item yang sama muncul dua baris dalam satu request — wireframe memakai stepper qty, bukan baris ganda. Menyederhanakan reservasi & pelaporan. |
| `quantity_approved <= quantity_requested` | PIC Stationery hanya boleh **mengurangi**, tidak menambah di luar yang diminta. Invariant Domain Model. |
| `quantity_actual <= quantity_approved` | PIC Gudang hanya boleh menyerahkan hingga sebanyak yang disetujui (jawaban Q5: penyerahan sebagian diizinkan). |

---

## 4. Modul Approval (polymorphic)

```sql
CREATE TABLE approvals (
    id               bigserial PRIMARY KEY,
    approvable_type  varchar(100) NOT NULL,   -- Modules\Requisition\Models\Request | ...Purchase
    approvable_id    bigint NOT NULL,
    approver_id      bigint NOT NULL REFERENCES users(id),
    approval_level   smallint NOT NULL,
    approver_role    varchar(50) NOT NULL,    -- snapshot peran saat memutuskan
    status           varchar(20) NOT NULL,
    approval_date    timestamptz NOT NULL DEFAULT now(),
    rejection_notes  text,
    snapshot         jsonb,                   -- kuantitas per baris saat keputusan
    is_superseded    boolean NOT NULL DEFAULT false,
    created_at       timestamptz NOT NULL DEFAULT now(),
    updated_at       timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT chk_approvals_status CHECK (status IN ('APPROVED','REJECTED')),
    CONSTRAINT chk_approvals_rejection_reason
        CHECK (status <> 'REJECTED' OR (rejection_notes IS NOT NULL
                                        AND length(trim(rejection_notes)) > 0))
);
CREATE INDEX idx_approvals_approvable ON approvals(approvable_type, approvable_id);
CREATE INDEX idx_approvals_approver   ON approvals(approver_id);
CREATE INDEX idx_approvals_active     ON approvals(approvable_type, approvable_id)
    WHERE is_superseded = false;
```

`chk_approvals_rejection_reason` menegakkan aturan blueprint *"tekan tombol ditolak dan masukkan alasan penolakan"* di level database — penolakan tanpa alasan mustahil tersimpan, apa pun jalur masuknya.

`approver_role` disimpan sebagai **snapshot** karena peran seseorang dapat berubah; riwayat approval harus tetap menunjukkan kapasitas orang tersebut saat memutuskan.

---

## 5. Modul Purchasing

```sql
CREATE TABLE purchases (
    id                bigserial PRIMARY KEY,
    purchase_number   varchar(30) NOT NULL UNIQUE,   -- '111234567866'
    purchase_date     date NOT NULL,
    supplier_name     varchar(200) NOT NULL,
    created_by        bigint NOT NULL REFERENCES users(id),
    verified_by       bigint REFERENCES users(id),
    verification_date timestamptz,
    status            varchar(30) NOT NULL DEFAULT 'DRAFT',
    notes             text,
    rejection_notes   text,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT chk_purchases_status CHECK (status IN (
        'DRAFT','PENDING_VERIFICATION','VERIFIED','REJECTED'
    )),
    CONSTRAINT chk_purchases_verified_fields
        CHECK (status <> 'VERIFIED'
               OR (verified_by IS NOT NULL AND verification_date IS NOT NULL))
);
CREATE INDEX idx_purchases_status  ON purchases(status);
CREATE INDEX idx_purchases_date    ON purchases(purchase_date);
CREATE INDEX idx_purchases_creator ON purchases(created_by);

CREATE TABLE purchase_items (
    id          bigserial PRIMARY KEY,
    purchase_id bigint NOT NULL REFERENCES purchases(id) ON DELETE CASCADE,
    item_id     bigint NOT NULL REFERENCES items(id),
    quantity    integer NOT NULL,
    unit_price  numeric(18,2),          -- nullable (Q4)
    total_price numeric(18,2),          -- nullable (Q4)
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT chk_pi_quantity_positive CHECK (quantity > 0),
    CONSTRAINT chk_pi_price_non_negative
        CHECK (unit_price IS NULL OR unit_price >= 0),
    CONSTRAINT uq_purchase_item UNIQUE (purchase_id, item_id)
);
CREATE INDEX idx_purchase_items_purchase ON purchase_items(purchase_id);
CREATE INDEX idx_purchase_items_item     ON purchase_items(item_id);
```

`unit_price`/`total_price` dibuat **nullable** karena ERD blueprint memuatnya sedangkan wireframe 3.9.2 tidak menampilkannya (pertanyaan terbuka Q4). Kolom disiapkan sekarang agar penambahan harga di kemudian hari tidak memerlukan migrasi pada tabel yang sudah berisi data.

---

## 6. Modul Inventory

```sql
CREATE TABLE inventory_transactions (
    id                bigserial PRIMARY KEY,
    item_id           bigint NOT NULL REFERENCES items(id),
    transaction_type  varchar(20) NOT NULL,
    quantity          integer NOT NULL,          -- selalu POSITIF; arah dari type
    quantity_before   integer NOT NULL,
    quantity_after    integer NOT NULL,
    reference_type    varchar(100),              -- polymorphic: Request | Purchase | NULL
    reference_id      bigint,
    transaction_date  timestamptz NOT NULL DEFAULT now(),
    performed_by      bigint NOT NULL REFERENCES users(id),
    adjustment_reason text,
    created_at        timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT chk_it_type CHECK (transaction_type IN ('IN','OUT','ADJUSTMENT')),
    CONSTRAINT chk_it_quantity_positive CHECK (quantity > 0),
    CONSTRAINT chk_it_balance_non_negative CHECK (quantity_after >= 0),
    CONSTRAINT chk_it_adjustment_needs_reason
        CHECK (transaction_type <> 'ADJUSTMENT'
               OR (adjustment_reason IS NOT NULL
                   AND length(trim(adjustment_reason)) > 0))
);
CREATE INDEX idx_it_item      ON inventory_transactions(item_id, transaction_date);
CREATE INDEX idx_it_reference ON inventory_transactions(reference_type, reference_id);
CREATE INDEX idx_it_date      ON inventory_transactions(transaction_date);
CREATE INDEX idx_it_type      ON inventory_transactions(transaction_type);

CREATE TABLE stock_reservations (
    id              bigserial PRIMARY KEY,
    item_id         bigint NOT NULL REFERENCES items(id),
    request_item_id bigint NOT NULL REFERENCES request_items(id) ON DELETE CASCADE,
    quantity        integer NOT NULL,
    status          varchar(20) NOT NULL DEFAULT 'HELD',
    expires_at      timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT chk_sr_status   CHECK (status IN ('HELD','CONSUMED','RELEASED')),
    CONSTRAINT chk_sr_quantity CHECK (quantity > 0)
);
CREATE UNIQUE INDEX uq_sr_active ON stock_reservations(request_item_id)
    WHERE status = 'HELD';
CREATE INDEX idx_sr_item    ON stock_reservations(item_id) WHERE status = 'HELD';
CREATE INDEX idx_sr_expires ON stock_reservations(expires_at) WHERE status = 'HELD';

CREATE TABLE stock_monthly_snapshots (
    id              bigserial PRIMARY KEY,
    item_id         bigint NOT NULL REFERENCES items(id),
    period_year     smallint NOT NULL,
    period_month    smallint NOT NULL,
    opening_balance integer NOT NULL,
    total_in        integer NOT NULL DEFAULT 0,
    total_out       integer NOT NULL DEFAULT 0,
    total_adjustment integer NOT NULL DEFAULT 0,
    closing_balance integer NOT NULL,
    generated_at    timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT chk_sms_month CHECK (period_month BETWEEN 1 AND 12),
    CONSTRAINT uq_sms_item_period UNIQUE (item_id, period_year, period_month)
);
CREATE INDEX idx_sms_period ON stock_monthly_snapshots(period_year, period_month);
```

**Mengapa `quantity` selalu positif dan arah ditentukan `transaction_type`:** menyimpan angka negatif untuk pengeluaran membuat setiap query agregat harus mengingat konvensi tanda, dan satu kesalahan tanda merusak seluruh laporan. Dengan besaran selalu positif, `SUM` per tipe menjadi eksplisit dan tak ambigu.

**`uq_sr_active`** — unique partial index memastikan satu baris request hanya boleh punya **satu** reservasi aktif, sehingga revisi berulang tidak menumpuk reservasi ganda atas stok yang sama.

---

## 7. Modul Pendukung

```sql
-- Laravel notifications (bawaan, disesuaikan ke PostgreSQL)
CREATE TABLE notifications (
    id              uuid PRIMARY KEY,
    type            varchar(255) NOT NULL,
    notifiable_type varchar(255) NOT NULL,
    notifiable_id   bigint NOT NULL,
    data            jsonb NOT NULL,
    read_at         timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_notif_notifiable ON notifications(notifiable_type, notifiable_id);
CREATE INDEX idx_notif_unread     ON notifications(notifiable_id) WHERE read_at IS NULL;

CREATE TABLE audit_logs (
    id             bigserial PRIMARY KEY,
    auditable_type varchar(100) NOT NULL,
    auditable_id   bigint NOT NULL,
    user_id        bigint REFERENCES users(id),
    event          varchar(50) NOT NULL,      -- created | updated | deleted | login | ...
    old_values     jsonb,
    new_values     jsonb,
    ip_address     inet,
    user_agent     text,
    created_at     timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX idx_audit_auditable ON audit_logs(auditable_type, auditable_id);
CREATE INDEX idx_audit_user      ON audit_logs(user_id);
CREATE INDEX idx_audit_created   ON audit_logs(created_at);

-- Generator nomor dokumen (REQ001, dst) — anti-duplikat via row lock
CREATE TABLE document_sequences (
    id            bigserial PRIMARY KEY,
    document_type varchar(30) NOT NULL,   -- 'REQUEST' | 'PURCHASE'
    period_year   smallint NOT NULL,
    last_number   integer NOT NULL DEFAULT 0,
    updated_at    timestamptz NOT NULL DEFAULT now(),

    CONSTRAINT uq_docseq UNIQUE (document_type, period_year)
);
```

**Cara kerja `document_sequences`:** nomor diambil dengan `SELECT ... FOR UPDATE` di dalam transaksi yang sama dengan penyimpanan dokumen. Pendekatan ini dipilih ketimbang `MAX(request_number) + 1` — yang menghasilkan duplikat saat dua user submit bersamaan — dan ketimbang PostgreSQL `SEQUENCE`, yang meninggalkan lubang nomor saat transaksi di-rollback (tidak diterima untuk dokumen approval yang harus berurutan rapi).

---

## 8. Ringkasan Tabel

| # | Tabel | Modul | Jenis | Perkiraan Baris/Tahun |
|---|---|---|---|---|
| 1 | `departments` | Identity | Master | < 100 |
| 2 | `users` | Identity | Master | < 3.000 |
| 3 | `roles` / `permissions` / pivot | Identity | Master | < 200 |
| 4 | `categories` | Catalog | Master | < 20 |
| 5 | `uoms` | Catalog | Master | < 20 |
| 6 | `items` | Catalog | Master | < 5.000 |
| 7 | `requests` | Requisition | Transaksi | ~ 10.000 |
| 8 | `request_items` | Requisition | Transaksi | ~ 40.000 |
| 9 | `approvals` | Approval | Transaksi | ~ 35.000 |
| 10 | `purchases` | Purchasing | Transaksi | ~ 1.000 |
| 11 | `purchase_items` | Purchasing | Transaksi | ~ 5.000 |
| 12 | `inventory_transactions` | Inventory | **Ledger** | ~ 45.000 |
| 13 | `stock_reservations` | Inventory | Transaksi | ~ 40.000 |
| 14 | `stock_monthly_snapshots` | Inventory | Turunan | ~ 60.000 |
| 15 | `notifications` | Notification | Transaksi | ~ 150.000 |
| 16 | `audit_logs` | Audit | Log | ~ 200.000 |
| 17 | `document_sequences` | Platform | Sistem | < 10 |

**Total ± 17 tabel inti.** Volume tahunan berada jauh di bawah batas yang memerlukan partisi tabel atau replika baca. PostgreSQL satu instans dengan indeks di atas sudah memadai untuk beberapa tahun ke depan. `notifications` dan `audit_logs` adalah kandidat pertama untuk kebijakan arsip bila kelak diperlukan (terkait Q8).

---

## 9. Skenario Uji Integritas Wajib

Pengujian berikut **harus** ada sebelum rilis — masing-masing memvalidasi satu invariant yang bila gagal akan merusak data secara diam-diam.

| # | Skenario | Ekspektasi |
|---|---|---|
| T1 | Dua serah terima bersamaan atas item stok 1 | Satu berhasil, satu gagal `InsufficientStockException`; stok akhir 0, tidak negatif |
| T2 | Dua user submit request bersamaan | Dua `request_number` berbeda, tanpa duplikat |
| T3 | Verifikasi pembelian gagal di tengah transaksi | Stok tidak berubah, ledger tidak menyisakan baris |
| T4 | Penolakan tanpa alasan | Ditolak database (`chk_approvals_rejection_reason`) |
| T5 | `quantity_approved` > `quantity_requested` | Ditolak database |
| T6 | Approval L1 oleh pimpinan seksi lain | Ditolak Policy (403) |
| T7 | Approve dua kali pada request yang sama | Yang kedua gagal `InvalidStateTransitionException` |
| T8 | `SUM(ledger)` vs `items.stock_quantity` setelah 1.000 transaksi acak | Selalu identik |
| T9 | Penolakan SGA melepas reservasi | `reserved_quantity` kembali ke nilai semula |
| T10 | Request dibatalkan setelah reservasi | Reservasi berstatus `RELEASED`, stok tersedia pulih |
| T11 | Isi `quantity_actual` saat `quantity_approved` masih NULL | Ditolak database (`chk_ri_qty_actual_range`) — barang tidak boleh diserahkan sebelum disetujui L2 |

---

**Lanjut ke:** [Bagian 4 — Development Roadmap](04-roadmap.md)
