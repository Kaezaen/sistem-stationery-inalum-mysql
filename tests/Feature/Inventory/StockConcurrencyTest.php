<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Uji Konkurensi & Integritas Stok — EXIT CRITERIA FASE 3
|--------------------------------------------------------------------------
|
| Ini pengujian terpenting dalam sistem. Bug stok yang lolos ke fase berikutnya
| akan terwujud sebagai selisih antara stok fisik dan sistem, dan sangat mahal
| ditelusuri setelah data transaksi menumpuk di atasnya.
|
| Mengacu pada T1, T3, dan T8 pada §9 Database Schema.
|
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actor = User::factory()->create();
    $this->stock = app(StockService::class);
});

/*
|--------------------------------------------------------------------------
| T1 — Pengurangan bersamaan atas stok terbatas
|--------------------------------------------------------------------------
*/

// Bukti langsung bahwa SELECT ... FOR UPDATE efektif ada di StockRowLockTest —
// pengujian itu butuh data yang ter-commit sehingga tidak dapat memakai
// RefreshDatabase yang membungkus seluruh test dalam transaksi.

it('menolak pengurangan melebihi stok dan tidak menyisakan jejak ledger', function (): void {
    $item = Item::factory()->withStock(1)->create();

    expect(fn () => $this->stock->decrease($item, 2, $this->actor))
        ->toThrow(InsufficientStockException::class);

    expect($item->refresh()->stock_quantity)->toBe(1)
        ->and(InventoryTransaction::where('item_id', $item->id)->count())->toBe(0);
});

it('hanya melayani satu dari dua pengurangan berturut-turut atas stok satu', function (): void {
    // Inti skenario T1: stok 1, dua permintaan pengurangan. Yang kedua HARUS
    // gagal, dan saldo akhir harus nol — bukan negatif.
    $item = Item::factory()->withStock(1)->create();

    $this->stock->decrease($item, 1, $this->actor);

    expect(fn () => $this->stock->decrease($item->refresh(), 1, $this->actor))
        ->toThrow(InsufficientStockException::class);

    expect($item->refresh()->stock_quantity)->toBe(0)
        ->and(InventoryTransaction::where('item_id', $item->id)->count())->toBe(1);
});

it('menolak stok negatif di level database sebagai jaring pengaman terakhir', function (): void {
    // Bila suatu saat ada bug yang melewati pemeriksaan aplikasi, database
    // harus tetap menolak alih-alih menyimpan saldo negatif diam-diam.
    $item = Item::factory()->withStock(1)->create();

    expect(fn () => DB::table('items')->where('id', $item->id)->update(['stock_quantity' => -1]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

/*
|--------------------------------------------------------------------------
| T3 — Kegagalan di tengah transaksi
|--------------------------------------------------------------------------
*/

it('tidak mengubah stok maupun ledger bila transaksi gagal di tengah', function (): void {
    $item = Item::factory()->withStock(10)->create();

    try {
        DB::transaction(function () use ($item): void {
            $this->stock->increase($item, 5, $this->actor);

            // Kegagalan setelah mutasi berhasil — meniru error di langkah
            // berikutnya pada alur verifikasi pembelian.
            throw new RuntimeException('gagal di tengah');
        });
    } catch (RuntimeException) {
        // diharapkan
    }

    expect($item->refresh()->stock_quantity)->toBe(10)
        ->and(InventoryTransaction::where('item_id', $item->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| T8 — Rekonsiliasi ledger terhadap saldo
|--------------------------------------------------------------------------
*/

it('menjaga saldo tetap identik dengan ledger setelah ratusan mutasi acak', function (): void {
    $item = Item::factory()->withStock(0)->create(['min_stock' => 0, 'max_stock' => 100000]);

    // Urutan tetap (bukan acak sungguhan) agar kegagalan dapat direproduksi
    // persis. Yang diuji adalah konsistensi, bukan keacakan.
    $seed = 12345;
    $applied = 0;

    for ($i = 0; $i < 300; $i++) {
        $seed = ($seed * 1103515245 + 12345) % 2147483648;
        $action = $seed % 3;
        $qty = ($seed % 7) + 1;

        $fresh = $item->refresh();

        if ($action === 0) {
            $this->stock->increase($fresh, $qty, $this->actor);
            $applied++;

            continue;
        }

        if ($action === 1 && $fresh->stock_quantity >= $qty) {
            $this->stock->decrease($fresh, $qty, $this->actor);
            $applied++;

            continue;
        }

        if ($action === 2) {
            $target = max(0, $fresh->stock_quantity + (($seed % 5) - 2));

            if ($this->stock->adjustTo($fresh, $target, $this->actor, 'stock opname') !== null) {
                $applied++;
            }
        }
    }

    $item->refresh();

    // 1. Saldo ter-cache harus sama dengan saldo akhir menurut ledger.
    expect($item->stock_quantity)->toBe($this->stock->ledgerBalance($item));

    // 2. Rantai saldo di ledger harus tersambung: quantity_after baris ke-N
    //    selalu menjadi quantity_before baris ke-N+1. Terputusnya rantai berarti
    //    ada mutasi yang tidak melewati StockService.
    $rows = InventoryTransaction::where('item_id', $item->id)->orderBy('id')->get();

    expect($rows)->toHaveCount($applied);

    $expectedBefore = 0;

    foreach ($rows as $row) {
        expect($row->quantity_before)->toBe($expectedBefore);
        $expectedBefore = $row->quantity_after;
    }

    expect($expectedBefore)->toBe($item->stock_quantity);
});

it('mendeteksi saldo yang menyimpang dari ledger', function (): void {
    // Mensimulasikan bug hipotetis: stok diubah tanpa melewati StockService.
    // Perintah stock:reconcile harus menangkapnya.
    $item = Item::factory()->withStock(0)->create();
    $this->stock->increase($item, 10, $this->actor);

    DB::table('items')->where('id', $item->id)->update(['stock_quantity' => 99]);

    $this->artisan('stock:reconcile')
        ->expectsOutputToContain('saldo tidak selaras')
        ->assertFailed();

    $this->artisan('stock:reconcile', ['--fix' => true])->assertSuccessful();

    expect($item->refresh()->stock_quantity)->toBe(10);
});
