<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);
});

function warehouseUser(): User
{
    $user = User::factory()->create();
    $user->assignRole([Role::Requester->value, Role::WarehouseOfficer->value]);

    return $user;
}

it('menolak requester biasa membuka Data Inventory', function (): void {
    // Blueprint 3.11 membatasi layar ini untuk PIC Stationery & PIC Gudang.
    $user = User::factory()->create();
    $user->assignRole(Role::Requester->value);

    $this->actingAs($user)->get('/inventory')->assertForbidden();
});

it('menampilkan Data Inventory beserta status stok', function (): void {
    Item::factory()->withStock(15)->create(['min_stock' => 5, 'max_stock' => 10]);
    Item::factory()->withStock(3)->create(['min_stock' => 5, 'max_stock' => 10]);
    Item::factory()->withStock(7)->create(['min_stock' => 5, 'max_stock' => 10]);

    $this->actingAs(warehouseUser())
        ->get('/inventory')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory/Index')
            ->where('items.total', 3),
        );
});

it('menyaring inventory menurut status stok', function (): void {
    Item::factory()->withStock(15)->create(['min_stock' => 5, 'max_stock' => 10]);
    Item::factory()->withStock(3)->create(['min_stock' => 5, 'max_stock' => 10]);
    Item::factory()->withStock(7)->create(['min_stock' => 5, 'max_stock' => 10]);

    $user = warehouseUser();

    $this->actingAs($user)->get('/inventory?status=under')
        ->assertInertia(fn ($page) => $page->where('items.total', 1));

    $this->actingAs($user)->get('/inventory?status=over')
        ->assertInertia(fn ($page) => $page->where('items.total', 1));

    $this->actingAs($user)->get('/inventory?status=on_hand')
        ->assertInertia(fn ($page) => $page->where('items.total', 1));
});

it('menampilkan kartu stok berisi riwayat pergerakan', function (): void {
    $actor = warehouseUser();
    $item = Item::factory()->withStock(0)->create();

    app(StockService::class)->increase($item, 10, $actor);
    app(StockService::class)->decrease($item->refresh(), 4, $actor);

    $this->actingAs($actor)
        ->get("/inventory/{$item->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory/Show')
            ->where('item.stock_quantity', 6)
            ->where('transactions.total', 2),
        );
});

it('menyesuaikan stok lewat perintah artisan', function (): void {
    // Jalur pengisian saldo awal saat go-live — harus dapat diskrip.
    $admin = User::factory()->create();
    $admin->assignRole(Role::Administrator->value);

    $item = Item::factory()->withStock(0)->create(['item_code' => 'ADJ001']);

    $this->artisan('stock:adjust', [
        'item' => 'ADJ001',
        'quantity' => 50,
        '--reason' => 'Saldo Awal',
        '--user' => $admin->username,
    ])->assertSuccessful();

    expect($item->refresh()->stock_quantity)->toBe(50);
});

it('menolak penyesuaian tanpa alasan', function (): void {
    // Koreksi tanpa alasan tidak dapat diaudit.
    Item::factory()->withStock(0)->create(['item_code' => 'ADJ002']);

    $this->artisan('stock:adjust', ['item' => 'ADJ002', 'quantity' => 10])
        ->expectsOutputToContain('wajib diisi')
        ->assertFailed();
});
