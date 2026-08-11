<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Bukti Penguncian Baris — inti jaminan konkurensi Fase 3
|--------------------------------------------------------------------------
|
| Seluruh keamanan stok bertumpu pada satu hal: SELECT ... FOR UPDATE benar-benar
| menahan transaksi lain. Test lain hanya membuktikan logika aplikasi menolak
| pengurangan berlebih; berkas ini membuktikan mekanisme di bawahnya.
|
| Memakai DatabaseTruncation, BUKAN RefreshDatabase: RefreshDatabase membungkus
| setiap test dalam transaksi yang tidak pernah di-commit, sehingga koneksi kedua
| tidak akan pernah melihat baris yang diuji — dan test-nya lulus palsu.
|
*/

uses(DatabaseTruncation::class);

/*
 * DatabaseTruncation membersihkan tabel SEBELUM tiap test, sehingga baris dari
 * test TERAKHIR di berkas ini tetap ter-commit setelah berkasnya selesai. Baris
 * sisa itu terlihat oleh berkas test lain — dan bila nilai acak dari factory
 * kebetulan sama, memicu pelanggaran unique yang muncul secara ACAK.
 *
 * Kegagalan seperti itu jauh lebih sulit ditelusuri daripada kegagalan konsisten,
 * karena bergantung pada urutan test dan nilai acak. Karena itu dibersihkan
 * eksplisit di sini.
 */
afterEach(function (): void {
    // MySQL tak punya "TRUNCATE ... RESTART IDENTITY CASCADE" (sintaks PostgreSQL)
    // dan menolak TRUNCATE atas tabel yang direferensikan FK. Matikan pengecekan
    // FK sesaat, lalu TRUNCATE tiap tabel (sekaligus mereset AUTO_INCREMENT).
    DB::statement('SET FOREIGN_KEY_CHECKS=0');

    foreach (['items', 'users', 'uoms', 'categories', 'departments'] as $table) {
        DB::table($table)->truncate();
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=1');
});

it('menahan koneksi lain yang mencoba mengunci baris yang sama', function (): void {
    $user = User::factory()->create();
    $item = Item::factory()->withStock(10)->create();

    // Koneksi kedua ke database yang sama. Didaftarkan saat runtime agar
    // kebutuhan khusus pengujian ini tidak mengotori config aplikasi.
    config()->set('database.connections.probe', config('database.connections.mysql'));

    $probe = DB::connection('probe');

    DB::beginTransaction();

    try {
        // Koneksi utama mengunci baris dan MENAHANNYA.
        DB::table('items')->where('id', $item->id)->lockForUpdate()->first();

        // Tanpa batas lock wait, koneksi kedua akan menunggu selamanya dan test
        // menggantung alih-alih gagal. innodb_lock_wait_timeout (detik, minimum 1)
        // mengubahnya menjadi error 1205 yang dapat diperiksa.
        $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $probe->beginTransaction();

        $blocked = false;
        $message = '';

        try {
            $probe->table('items')->where('id', $item->id)->lockForUpdate()->first();
        } catch (Throwable $e) {
            $message = strtolower($e->getMessage());
            // MySQL 1205: "Lock wait timeout exceeded; try restarting transaction".
            $blocked = str_contains($message, 'lock wait timeout')
                || str_contains($message, 'lock timeout');
        } finally {
            $probe->rollBack();
        }

        expect($blocked)->toBeTrue(
            'Koneksi kedua berhasil mengunci baris yang sedang dikunci koneksi pertama. '
            .'FOR UPDATE tidak efektif — dua mutasi stok bersamaan akan membaca saldo yang sama. '
            .'Pesan: '.$message,
        );
    } finally {
        DB::rollBack();
        $probe->disconnect();
    }

    // Memastikan test benar-benar berjalan atas data nyata, bukan baris kosong.
    expect($item->refresh()->stock_quantity)->toBe(10)
        ->and($user->exists)->toBeTrue();
});

it('membiarkan koneksi lain mengunci baris yang berbeda', function (): void {
    // Kontrol: memastikan yang terkunci adalah BARIS, bukan seluruh tabel.
    // Bila penguncian ternyata selebar tabel, throughput sistem akan runtuh
    // saat banyak item bergerak bersamaan.
    User::factory()->create();
    $itemA = Item::factory()->withStock(5)->create();
    $itemB = Item::factory()->withStock(5)->create();

    config()->set('database.connections.probe', config('database.connections.mysql'));
    $probe = DB::connection('probe');

    DB::beginTransaction();

    try {
        DB::table('items')->where('id', $itemA->id)->lockForUpdate()->first();

        $probe->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $probe->beginTransaction();

        $lockedOther = false;

        try {
            $row = $probe->table('items')->where('id', $itemB->id)->lockForUpdate()->first();
            $lockedOther = $row !== null;
        } finally {
            $probe->rollBack();
        }

        expect($lockedOther)->toBeTrue('Penguncian ternyata memblokir baris lain — terlalu lebar.');
    } finally {
        DB::rollBack();
        $probe->disconnect();
    }
});
