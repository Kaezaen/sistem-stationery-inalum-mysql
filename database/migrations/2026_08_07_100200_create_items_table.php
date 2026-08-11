<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog item ATK.
 *
 * stock_quantity dan reserved_quantity dibuat di sini karena secara struktural
 * milik item, namun HANYA boleh ditulis oleh modul Inventory lewat StockService
 * (ADR-08). Modul Catalog tidak pernah menyentuh kedua kolom tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->string('item_code', 30)->unique();
            $table->string('item_name', 200);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('uom_id')->constrained('uoms');

            $table->integer('stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->integer('min_stock')->default(0);
            $table->integer('max_stock')->default(0);

            $table->text('remark')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('uom_id');

            // Pencarian nama/kode item memakai LIKE '%kata%'. MySQL tidak punya
            // pg_trgm; katalog < 5.000 baris sehingga table scan sudah memadai.
            // (Opsional bila kelak perlu: FULLTEXT(item_name, item_code) — semantik
            // berbasis kata, bukan substring; sengaja tidak dipasang di Fase ini.)

            // Laporan "Need to Buy" (R8). MySQL tidak mendukung indeks parsial, dan
            // predikat "stock_quantity < min_stock" (perbandingan antar-kolom) tak
            // dapat diindeks. Indeks biasa pada kolom filter tetap membantu
            // menyaring baris aktif; sisanya diselesaikan saat scan.
            $table->index(['is_active', 'stock_quantity', 'min_stock'], 'idx_items_need_to_buy');
        });

        /*
         * Jaring pengaman terakhir (§8.1 Architecture Blueprint aturan 5).
         *
         * Bila ada bug di lapisan aplikasi yang membuat stok negatif atau
         * reservasi melebihi stok, database menolak transaksi alih-alih
         * menyimpan data rusak secara diam-diam.
         */
        DB::statement('ALTER TABLE items ADD CONSTRAINT chk_items_stock_non_negative CHECK (stock_quantity >= 0)');
        DB::statement('ALTER TABLE items ADD CONSTRAINT chk_items_reserved_non_negative CHECK (reserved_quantity >= 0)');
        DB::statement('ALTER TABLE items ADD CONSTRAINT chk_items_reserved_le_stock CHECK (reserved_quantity <= stock_quantity)');
        DB::statement('ALTER TABLE items ADD CONSTRAINT chk_items_min_le_max CHECK (min_stock <= max_stock)');
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
