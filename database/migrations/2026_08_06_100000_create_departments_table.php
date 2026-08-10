<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departemen / Seksi.
 *
 * ERD blueprint menyimpan department sebagai teks bebas pada users. Dinormalisasi
 * menjadi tabel tersendiri karena laporan "Request by Account" (R6) mustahil
 * diagregasi dengan andal dari teks bebas. Lihat kesenjangan G1 pada
 * docs/architecture/01-requirement-analysis.md.
 *
 * head_user_id sengaja BELUM diberi foreign key di sini — tabel users belum ada.
 * Constraint-nya ditambahkan oleh migration 2026_08_06_100200.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);

            // Keputusan D3: "Account" pada laporan R6 = Departemen/Seksi.
            // Kolom ini disiapkan agar peralihan ke kode akun GL tidak butuh migrasi.
            $table->string('account_code', 30)->nullable();

            $table->foreignId('parent_id')->nullable()->constrained('departments');
            $table->unsignedBigInteger('head_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
