<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->integer('quantity');

            /*
             * Keputusan D4: harga NULLABLE.
             *
             * ERD blueprint memuat unit_price dan total_price, sedangkan wireframe
             * 3.9.2 tidak menampilkannya. Kolom disiapkan sekarang agar penambahan
             * harga di kemudian hari tidak memerlukan migrasi pada tabel yang
             * sudah berisi data.
             */
            $table->decimal('unit_price', 18, 2)->nullable();
            $table->decimal('total_price', 18, 2)->nullable();

            $table->timestamps();

            $table->index('purchase_id');
            $table->index('item_id');

            // Satu item tidak boleh muncul dua baris dalam satu dokumen —
            // UI memakai stepper jumlah, bukan baris ganda.
            $table->unique(['purchase_id', 'item_id'], 'uq_purchase_item');
        });

        DB::statement(
            'ALTER TABLE purchase_items ADD CONSTRAINT chk_pi_quantity_positive
             CHECK (quantity > 0)'
        );

        DB::statement(
            'ALTER TABLE purchase_items ADD CONSTRAINT chk_pi_price_non_negative
             CHECK (unit_price IS NULL OR unit_price >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
