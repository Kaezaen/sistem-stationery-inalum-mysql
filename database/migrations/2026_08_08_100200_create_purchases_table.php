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
        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();

            /*
             * Nomor pembelian DIINPUT MANUAL, tidak digenerate.
             *
             * Wireframe 3.9.2 menampilkan field "Masukkan nomor pembelian", dan
             * contoh nilainya (111234567866) menyerupai nomor faktur pemasok —
             * bukan nomor internal berurutan. Keunikannya tetap dijaga agar satu
             * faktur tidak terinput dua kali dan menaikkan stok berganda.
             */
            $table->string('purchase_number', 30)->unique();

            $table->date('purchase_date');
            $table->string('supplier_name', 200);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestampTz('verification_date')->nullable();
            $table->string('status', 30)->default('DRAFT');
            $table->text('notes')->nullable();
            $table->text('rejection_notes')->nullable();
            $table->smallInteger('revision_count')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('purchase_date');
            $table->index('created_by');
        });

        DB::statement(
            "ALTER TABLE purchases ADD CONSTRAINT chk_purchases_status
             CHECK (status IN ('DRAFT','PENDING_VERIFICATION','VERIFIED','REJECTED'))"
        );

        // Dokumen berstatus VERIFIED tanpa jejak siapa dan kapan memverifikasi
        // tidak dapat dipertanggungjawabkan — padahal di titik itulah stok naik.
        DB::statement(
            "ALTER TABLE purchases ADD CONSTRAINT chk_purchases_verified_fields
             CHECK (status <> 'VERIFIED'
                    OR (verified_by IS NOT NULL AND verification_date IS NOT NULL))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
