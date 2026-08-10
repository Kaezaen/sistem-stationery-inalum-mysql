<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\TransactionType;
use App\Modules\Inventory\Models\InventoryTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Saldo awal via ADJUSTMENT (command stock:seed-initial).
|
| Yang dikunci: stok terisi ke max_stock LEWAT LEDGER (bukan UPDATE langsung) —
| sehingga saldo tetap rekonsiliasi — dan menjalankan ulang tidak menggandakan
| baris ledger (idempoten).
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole(Role::Administrator->value);
});

it('mengisi seluruh item ke max_stock lewat transaksi ADJUSTMENT', function (): void {
    $a = Item::factory()->create(['min_stock' => 10, 'max_stock' => 100]);
    $b = Item::factory()->create(['min_stock' => 5, 'max_stock' => 50]);

    $this->artisan('stock:seed-initial', ['--reason' => 'Saldo Awal Uji'])->assertSuccessful();

    expect($a->refresh()->stock_quantity)->toBe(100)
        ->and($b->refresh()->stock_quantity)->toBe(50)
        // Tiap pengisian menghasilkan satu baris ledger ADJUSTMENT — bukan UPDATE senyap.
        ->and(InventoryTransaction::query()->ofType(TransactionType::Adjustment)->count())->toBe(2)
        // Saldo ter-cache = ledger (jaminan rekonsiliasi).
        ->and($a->stock_quantity)->toBe(
            (int) InventoryTransaction::where('item_id', $a->id)->latest('id')->value('quantity_after'),
        );
});

it('idempoten — menjalankan ulang tidak menambah baris ledger', function (): void {
    Item::factory()->create(['max_stock' => 100]);

    $this->artisan('stock:seed-initial', ['--reason' => 'Saldo Awal Uji'])->assertSuccessful();
    $ledgerAfterFirst = InventoryTransaction::count();

    $this->artisan('stock:seed-initial', ['--reason' => 'Saldo Awal Uji'])->assertSuccessful();

    // Item sudah bersaldo max → adjustTo tidak mencatat apa pun.
    expect(InventoryTransaction::count())->toBe($ledgerAfterFirst);
});

it('mengabaikan item non-aktif', function (): void {
    Item::factory()->inactive()->create(['max_stock' => 100]);

    $this->artisan('stock:seed-initial', ['--reason' => 'Saldo Awal Uji'])->assertSuccessful();

    expect(InventoryTransaction::count())->toBe(0);
});
