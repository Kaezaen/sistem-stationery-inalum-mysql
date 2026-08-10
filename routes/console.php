<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Penjadwalan
|--------------------------------------------------------------------------
|
| Dijalankan oleh satu entri cron di server:
|   * * * * * cd /var/www/stationery/current && php artisan schedule:run
|
*/

// Melepas stok yang dikunci untuk request yang tidak pernah diambil.
// Tanpa ini, stok terlihat habis padahal barangnya masih ada di gudang.
Schedule::command('stock:release-expired-reservations')
    ->dailyAt('01:00')
    ->withoutOverlapping();

// Memeriksa keselarasan saldo ter-cache terhadap ledger (ADR-08).
// Tanpa --fix: hanya melaporkan. Selisih harus ditelusuri, bukan ditimpa.
Schedule::command('stock:reconcile')
    ->dailyAt('02:00')
    ->withoutOverlapping();

// Snapshot saldo bulan yang baru saja selesai — sumber laporan R1/R2.
// Dijalankan tanggal 1 tiap bulan, setelah seluruh transaksi bulan lalu final.
Schedule::command('stock:snapshot')
    ->monthlyOn(1, '00:30')
    ->withoutOverlapping();

// Menyegarkan snapshot bulan BERJALAN tiap hari agar kolom bulan ini pada R1 dan
// dashboard tidak kosong sampai bulan tersebut ditutup. Idempoten — hanya menimpa
// angka periode yang sama, tidak menggandakan baris.
Schedule::command('stock:snapshot --current')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Pengingat approval tertunda > 2 hari (N12). Dikirim pagi hari kerja agar
// approver menerimanya saat mulai bekerja.
Schedule::command('approvals:remind --days=2')
    ->weekdays()
    ->dailyAt('07:00')
    ->withoutOverlapping();
