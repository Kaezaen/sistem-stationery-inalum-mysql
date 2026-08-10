<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot saldo stok per bulan — §6 Database Schema (kesenjangan G9).
 *
 * Laporan Stock by Month (R1) & Stock by Year (R2) membutuhkan saldo awal, masuk,
 * keluar, dan saldo akhir per periode. Menghitung ulang dari ledger setiap kali
 * laporan dibuka akan makin lambat seiring bertambahnya transaksi; snapshot ini
 * memindahkan biaya agregasi ke proses terjadwal bulanan (command stock:snapshot).
 *
 * Tabel bersifat TURUNAN: seluruh nilainya dapat dibangun ulang dari
 * inventory_transactions kapan saja (lihat stock:snapshot --backfill). Karena itu
 * ia hanya menyimpan generated_at — bukan created_at/updated_at penuh.
 *
 * CATATAN: kolom total_adjustment mengikuti DDL §6 (bukan diagram ERD §4 yang
 * melewatkannya). Tanpa memisahkan penyesuaian, saldo akhir tidak akan rekonsiliasi
 * dengan ledger untuk item yang pernah di-stock-opname.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_monthly_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('items');
            $table->smallInteger('period_year');
            $table->smallInteger('period_month');
            $table->integer('opening_balance');
            $table->integer('total_in')->default(0);
            $table->integer('total_out')->default(0);
            $table->integer('total_adjustment')->default(0);
            $table->integer('closing_balance');
            $table->timestampTz('generated_at')->useCurrent();

            $table->index(['period_year', 'period_month'], 'idx_sms_period');
        });

        DB::statement(
            'ALTER TABLE stock_monthly_snapshots ADD CONSTRAINT chk_sms_month
             CHECK (period_month BETWEEN 1 AND 12)'
        );

        /*
         * Satu item hanya boleh punya SATU baris per periode. Ini yang membuat
         * regenerasi snapshot idempoten: stock:snapshot memakai updateOrInsert
         * atas kunci ini, sehingga menjalankan ulang bulan yang sama hanya
         * memperbarui angkanya, tidak menggandakan baris.
         */
        DB::statement(
            'ALTER TABLE stock_monthly_snapshots ADD CONSTRAINT uq_sms_item_period
             UNIQUE (item_id, period_year, period_month)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_monthly_snapshots');
    }
};
