<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat keputusan approval — polymorphic.
 *
 * ERD blueprint mengikat tabel ini ke request_id saja. Dibuat polymorphic karena
 * Purchase juga melewati verifikasi: tanpa itu, seluruh tabel dan logikanya harus
 * diduplikasi untuk alur pembelian.
 *
 * Baris di sini IMMUTABLE setelah dibuat. Revisi tidak menimpa keputusan lama,
 * melainkan menandainya is_superseded — sehingga riwayat lengkap tetap terlihat,
 * termasuk keputusan yang kemudian dianulir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->string('approvable_type', 100);
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('approver_id')->constrained('users');
            $table->smallInteger('approval_level');

            // Snapshot peran saat memutuskan. Peran seseorang dapat berubah;
            // riwayat harus tetap menunjukkan kapasitasnya saat itu.
            $table->string('approver_role', 50);

            $table->string('status', 20);
            $table->timestampTz('approval_date')->useCurrent();
            $table->text('rejection_notes')->nullable();

            // Kuantitas per baris saat keputusan diambil — bukti apa yang
            // sebenarnya disetujui, meski dokumennya kemudian direvisi.
            $table->jsonb('snapshot')->nullable();

            $table->boolean('is_superseded')->default(false);
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
            $table->index('approver_id');
        });

        DB::statement(
            "ALTER TABLE approvals ADD CONSTRAINT chk_approvals_status
             CHECK (status IN ('APPROVED','REJECTED'))"
        );

        /*
         * Blueprint mensyaratkan "tekan tombol ditolak dan masukkan alasan
         * penolakan". Ditegakkan di database agar berlaku untuk semua jalur —
         * penolakan tanpa alasan mustahil tersimpan, apa pun kodenya.
         */
        DB::statement(
            "ALTER TABLE approvals ADD CONSTRAINT chk_approvals_rejection_reason
             CHECK (status <> 'REJECTED'
                    OR (rejection_notes IS NOT NULL AND CHAR_LENGTH(TRIM(rejection_notes)) > 0))"
        );

        // PostgreSQL memakai indeks parsial (WHERE is_superseded = false) untuk
        // pencarian approval aktif. MySQL tak punya indeks parsial; menyertakan
        // is_superseded sebagai kolom indeks membuat pencarian approval aktif
        // (approvable + is_superseded = false) tetap terlayani, sekaligus tidak
        // menduplikasi indeks (approvable_type, approvable_id) yang sudah ada.
        DB::statement(
            'CREATE INDEX idx_approvals_active ON approvals (approvable_type, approvable_id, is_superseded)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
