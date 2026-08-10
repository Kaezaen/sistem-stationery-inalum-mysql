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
         */
        DB::statement(
            "CREATE UNIQUE INDEX uq_sr_active ON stock_reservations (request_item_id)
             WHERE status = 'HELD' AND request_item_id IS NOT NULL"
        );

        DB::statement("CREATE INDEX idx_sr_item ON stock_reservations (item_id) WHERE status = 'HELD'");
        DB::statement("CREATE INDEX idx_sr_expires ON stock_reservations (expires_at) WHERE status = 'HELD'");
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
