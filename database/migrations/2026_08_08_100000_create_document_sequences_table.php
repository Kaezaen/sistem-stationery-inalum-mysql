<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penomoran dokumen berurutan tanpa lubang.
 *
 * Nomor diambil dengan SELECT ... FOR UPDATE di dalam transaksi yang sama dengan
 * penyimpanan dokumen.
 *
 * Dua alternatif yang ditolak:
 *   - MAX(nomor) + 1 menghasilkan duplikat saat dua user submit bersamaan.
 *   - PostgreSQL SEQUENCE meninggalkan lubang nomor saat transaksi di-rollback,
 *     dan dokumen approval harus berurutan rapi untuk keperluan audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type', 30);
            $table->smallInteger('period_year');
            $table->integer('last_number')->default(0);
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['document_type', 'period_year'], 'uq_docseq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
