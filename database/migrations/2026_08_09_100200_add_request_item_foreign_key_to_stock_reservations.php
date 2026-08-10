<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi constraint yang ditunda dari Fase 3.
 *
 * stock_reservations.request_item_id dibuat tanpa foreign key saat itu karena
 * tabel request_items belum ada. Sekarang tabelnya ada, sehingga referensinya
 * dapat ditegakkan database — reservasi tidak akan pernah menunjuk baris request
 * yang tidak ada.
 *
 * cascadeOnDelete: menghapus baris request ikut menghapus reservasinya. Aman
 * karena penghapusan baris hanya terjadi pada request yang belum disetujui,
 * dan pelepasan reserved_quantity ditangani StockReservationService sebelum
 * baris dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->foreign('request_item_id')
                ->references('id')
                ->on('request_items')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table): void {
            $table->dropForeign(['request_item_id']);
        });
    }
};
