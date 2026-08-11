<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reservasi stok — ADR-07.
 *
 * Menutup celah alokasi ganda: antara PIC Stationery menyetujui kuantitas dan
 * PIC Gudang menyerahkan barang terdapat jeda (melewati approval SGA). Tanpa
 * reservasi, request lain dapat disetujui atas stok fisik yang sama, lalu gagal
 * di gudang setelah terlanjur melewati seluruh approval.
 *
 * CATATAN: request_item_id dibiarkan tanpa foreign key untuk sementara — tabel
 * request_items baru dibuat pada Fase 5. Constraint-nya ditambahkan oleh
 * migration Fase 5 begitu tabel tujuannya ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->unsignedBigInteger('request_item_id')->nullable();
            $table->integer('quantity');
            $table->string('status', 20)->default('HELD');
            $table->timestampTz('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('request_item_id');
        });

        DB::statement(
            "ALTER TABLE stock_reservations ADD CONSTRAINT chk_sr_status
             CHECK (status IN ('HELD','CONSUMED','RELEASED'))"
        );

        DB::statement(
            'ALTER TABLE stock_reservations ADD CONSTRAINT chk_sr_quantity
             CHECK (quantity > 0)'
        );

        /*
         * Satu baris request hanya boleh punya SATU reservasi aktif.
         *
         * Tanpa ini, revisi berulang pada request yang sama akan menumpuk
         * reservasi ganda atas stok yang sama — stok terlihat habis padahal
         * hanya satu request yang benar-benar menahannya.
         *
         * MySQL tidak mendukung UNIQUE index parsial (CREATE UNIQUE INDEX ... WHERE).
         * Padanannya (spec §10.3b): kolom generated yang bernilai request_item_id
         * HANYA saat status = 'HELD', selain itu NULL. Karena beberapa NULL tidak
         * dianggap bertabrakan di UNIQUE index, constraint ini hanya menegakkan
         * keunikan pada reservasi yang benar-benar aktif — identik secara fungsional
         * dengan partial index PostgreSQL.
         *
         * VIRTUAL, bukan STORED: request_item_id menerima foreign key ON DELETE
         * CASCADE (migration Fase 5). MySQL MENOLAK FK dengan aksi CASCADE/SET NULL
         * pada kolom yang menjadi basis kolom generated STORED (error 1215). Kolom
         * VIRTUAL bebas dari batasan itu dan tetap bisa diberi UNIQUE index (InnoDB).
         */
        DB::statement(
            "ALTER TABLE stock_reservations
             ADD COLUMN active_key BIGINT UNSIGNED
             GENERATED ALWAYS AS (CASE WHEN status = 'HELD' THEN request_item_id ELSE NULL END) VIRTUAL"
        );
        DB::statement('ALTER TABLE stock_reservations ADD CONSTRAINT uq_sr_active UNIQUE (active_key)');

        // Indeks parsial PostgreSQL (WHERE status = 'HELD') → indeks biasa di MySQL.
        DB::statement('CREATE INDEX idx_sr_item ON stock_reservations (item_id)');
        DB::statement('CREATE INDEX idx_sr_expires ON stock_reservations (expires_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
