<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baris request.
 *
 * Tiga kolom kuantitas mencerminkan tiga peran yang berbeda:
 *   quantity_requested — diminta requester
 *   quantity_approved  — disetujui PIC Stationery (approval L2 yang KUANTITATIF)
 *   quantity_actual    — benar-benar diserahkan PIC Gudang
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');

            $table->integer('quantity_requested');
            $table->integer('quantity_approved')->nullable();
            $table->integer('quantity_actual')->nullable();

            $table->text('remark')->nullable();
            $table->string('status', 30)->default('REQUESTED');
            $table->timestamps();

            $table->index('request_id');
            $table->index('item_id');

            // Satu item hanya boleh satu baris — UI memakai stepper jumlah,
            // bukan baris ganda. Menyederhanakan reservasi dan pelaporan.
            $table->unique(['request_id', 'item_id'], 'uq_request_item');
        });

        DB::statement(
            'ALTER TABLE request_items ADD CONSTRAINT chk_ri_qty_requested_positive
             CHECK (quantity_requested > 0)'
        );

        // PIC Stationery hanya boleh MENGURANGI, tidak menambah di luar permintaan.
        DB::statement(
            'ALTER TABLE request_items ADD CONSTRAINT chk_ri_qty_approved_range
             CHECK (quantity_approved IS NULL
                    OR (quantity_approved >= 0 AND quantity_approved <= quantity_requested))'
        );

        /*
         * Klausa "quantity_approved IS NOT NULL" WAJIB ada.
         *
         * Tanpa itu, perbandingan terhadap NULL menghasilkan NULL — dan
         * PostgreSQL menganggap CHECK bernilai NULL sebagai LULUS. Akibatnya
         * PIC Gudang bisa menyerahkan barang yang belum disetujui L2 sama sekali.
         */
        DB::statement(
            'ALTER TABLE request_items ADD CONSTRAINT chk_ri_qty_actual_range
             CHECK (quantity_actual IS NULL
                    OR (quantity_approved IS NOT NULL
                        AND quantity_actual >= 0
                        AND quantity_actual <= quantity_approved))'
        );

        DB::statement(
            "ALTER TABLE request_items ADD CONSTRAINT chk_ri_status CHECK (status IN (
                'REQUESTED','APPROVED','PARTIALLY_APPROVED','REJECTED','ISSUED'
            ))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('request_items');
    }
};
