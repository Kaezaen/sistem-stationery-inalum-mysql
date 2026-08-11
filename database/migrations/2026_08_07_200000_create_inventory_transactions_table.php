<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger pergerakan stok — APPEND ONLY.
 *
 * Tabel ini adalah sumber kebenaran stok (ADR-08). items.stock_quantity hanyalah
 * saldo ter-cache yang harus selalu dapat direkonsiliasi terhadap isi tabel ini.
 *
 * TIDAK PERNAH di-UPDATE atau di-DELETE. Koreksi dilakukan dengan menambah
 * transaksi ADJUSTMENT berlawanan arah beserta alasannya, sehingga riwayat
 * kesalahan pun tetap terlihat saat audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->string('transaction_type', 20);

            /*
             * quantity SELALU positif; arah ditentukan transaction_type.
             *
             * Menyimpan angka negatif untuk pengeluaran memaksa setiap query
             * agregat mengingat konvensi tanda, dan satu kesalahan tanda merusak
             * seluruh laporan. Dengan besaran selalu positif, SUM per tipe
             * menjadi eksplisit dan tidak ambigu.
             */
            $table->integer('quantity');

            // Memungkinkan rekonstruksi saldo dan deteksi drift tanpa menghitung
            // ulang seluruh ledger dari awal.
            $table->integer('quantity_before');
            $table->integer('quantity_after');

            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestampTz('transaction_date')->useCurrent();
            $table->foreignId('performed_by')->constrained('users');
            $table->text('adjustment_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_id', 'transaction_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('transaction_date');
            $table->index('transaction_type');
        });

        DB::statement(
            "ALTER TABLE inventory_transactions ADD CONSTRAINT chk_it_type
             CHECK (transaction_type IN ('IN','OUT','ADJUSTMENT'))"
        );

        DB::statement(
            'ALTER TABLE inventory_transactions ADD CONSTRAINT chk_it_quantity_positive
             CHECK (quantity > 0)'
        );

        DB::statement(
            'ALTER TABLE inventory_transactions ADD CONSTRAINT chk_it_balance_non_negative
             CHECK (quantity_after >= 0)'
        );

        // Koreksi tanpa alasan tidak dapat diaudit — ditolak di level database
        // agar berlaku untuk semua jalur masuk, termasuk perintah artisan.
        DB::statement(
            "ALTER TABLE inventory_transactions ADD CONSTRAINT chk_it_adjustment_needs_reason
             CHECK (transaction_type <> 'ADJUSTMENT'
                    OR (adjustment_reason IS NOT NULL AND CHAR_LENGTH(TRIM(adjustment_reason)) > 0))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
