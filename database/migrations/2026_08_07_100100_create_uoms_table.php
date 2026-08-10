<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan ukur (Unit of Measure).
 *
 * Form 3.8.2 memakai input teks bebas untuk UoM, sedangkan wireframe inventory
 * menampilkan nilai baku "EACH". Master ini mencegah varian ejaan (EACH / Each /
 * PCS / Pcs) yang akan memecah pengelompokan pada laporan stok.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uoms', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uoms');
    }
};
