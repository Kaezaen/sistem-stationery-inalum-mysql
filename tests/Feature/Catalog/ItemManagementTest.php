<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\Uom;
use App\Modules\Catalog\Services\ItemService;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Shared\Exceptions\BusinessRuleException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);
});

function picStationery(): User
{
    $user = User::factory()->create();
    $user->assignRole([Role::Requester->value, Role::PicStationery->value]);

    return $user;
}

function plainRequester(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Requester->value);

    return $user;
}

/*
|--------------------------------------------------------------------------
| Otorisasi
|--------------------------------------------------------------------------
*/

it('mengizinkan seluruh pegawai melihat katalog', function (): void {
    // Tanpa akses katalog, requester tidak mungkin membuat request sama sekali.
    $this->actingAs(plainRequester())
        ->get('/items')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Items/Index'));
});

it('menolak requester membuka form tambah item', function (): void {
    $this->actingAs(plainRequester())->get('/items/create')->assertForbidden();
});

it('menolak requester menyimpan item lewat endpoint langsung', function (): void {
    $this->actingAs(plainRequester())
        ->post('/items', ['item_code' => 'X'])
        ->assertForbidden();
});

it('mengizinkan PIC Stationery mengelola item', function (): void {
    $this->actingAs(picStationery())
        ->get('/items/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Items/Create'));
});

/*
|--------------------------------------------------------------------------
| Pembuatan item
|--------------------------------------------------------------------------
*/

it('membuat item sesuai form blueprint', function (): void {
    $category = Category::factory()->create();
    $uom = Uom::factory()->create();

    $this->actingAs(picStationery())->post('/items', [
        'item_code' => '1709000002',
        'item_name' => 'BALL LINER, KENKO-SIZE 0,5-BLUE',
        'category_id' => $category->id,
        'uom_id' => $uom->id,
        'min_stock' => 5,
        'max_stock' => 10,
        'remark' => 'catatan',
        'is_active' => true,
    ])->assertRedirect('/items');

    $item = Item::where('item_code', '1709000002')->firstOrFail();

    expect($item->item_name)->toBe('BALL LINER, KENKO-SIZE 0,5-BLUE')
        ->and($item->min_stock)->toBe(5)
        ->and($item->max_stock)->toBe(10)
        // Item baru selalu bermula tanpa stok — stok hanya masuk lewat
        // verifikasi pembelian, tidak pernah lewat form master data.
        ->and($item->stock_quantity)->toBe(0)
        ->and($item->reserved_quantity)->toBe(0);
});

it('menolak item_code duplikat', function (): void {
    $existing = Item::factory()->create(['item_code' => '1709000002']);

    $this->actingAs(picStationery())->post('/items', [
        'item_code' => '1709000002',
        'item_name' => 'Item lain',
        'category_id' => $existing->category_id,
        'uom_id' => $existing->uom_id,
        'min_stock' => 0,
        'max_stock' => 10,
    ])->assertSessionHasErrors('item_code');
});

it('menolak min_stock melebihi max_stock', function (): void {
    $category = Category::factory()->create();
    $uom = Uom::factory()->create();

    $this->actingAs(picStationery())->post('/items', [
        'item_code' => 'X001',
        'item_name' => 'Item',
        'category_id' => $category->id,
        'uom_id' => $uom->id,
        'min_stock' => 20,
        'max_stock' => 10,
    ])->assertSessionHasErrors('max_stock');
});

it('menolak min melebihi max juga di lapis service', function (): void {
    // Import massal dan seeder tidak melewati FormRequest, sehingga aturan ini
    // harus ditegakkan ulang di Service.
    $category = Category::factory()->create();
    $uom = Uom::factory()->create();

    expect(fn () => app(ItemService::class)->create([
        'item_code' => 'X002',
        'item_name' => 'Item',
        'category_id' => $category->id,
        'uom_id' => $uom->id,
        'min_stock' => 20,
        'max_stock' => 10,
    ]))->toThrow(BusinessRuleException::class);
});

/*
|--------------------------------------------------------------------------
| Perlindungan stok
|--------------------------------------------------------------------------
*/

it('tidak mengizinkan stok diubah lewat form item', function (): void {
    // stock_quantity sengaja tidak fillable. Bahkan bila dikirim dari form,
    // nilainya harus diabaikan — stok hanya bergerak lewat StockService (ADR-08).
    $item = Item::factory()->withStock(10)->create();

    $this->actingAs(picStationery())->put("/items/{$item->id}", [
        'item_code' => $item->item_code,
        'item_name' => $item->item_name,
        'category_id' => $item->category_id,
        'uom_id' => $item->uom_id,
        'min_stock' => 5,
        'max_stock' => 20,
        'stock_quantity' => 9999,
        'reserved_quantity' => 500,
    ])->assertRedirect('/items');

    expect($item->refresh()->stock_quantity)->toBe(10)
        ->and($item->reserved_quantity)->toBe(0);
});

it('menolak menonaktifkan item yang masih bersisa stok', function (): void {
    // Barangnya masih ada secara fisik di gudang; menghilangkan catatannya
    // membuat stok fisik dan sistem langsung berselisih.
    $item = Item::factory()->withStock(7)->create();

    expect(fn () => app(ItemService::class)->guardCanDelete($item))
        ->toThrow(BusinessRuleException::class);
});

it('menolak menonaktifkan item yang sedang direservasi', function (): void {
    $item = Item::factory()->withStock(10, 4)->create();

    expect(fn () => app(ItemService::class)->guardCanDelete($item))
        ->toThrow(BusinessRuleException::class);
});

it('menonaktifkan item tanpa menghapus permanen', function (): void {
    // Request dan pembelian lama merujuk pada item ini; penghapusan permanen
    // akan merusak laporan historis.
    $item = Item::factory()->create();

    app(ItemService::class)->deactivate($item);

    expect(Item::find($item->id))->toBeNull()
        ->and(Item::withTrashed()->find($item->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Perilaku domain
|--------------------------------------------------------------------------
*/

it('menghitung jumlah tersedia setelah dikurangi reservasi', function (): void {
    $item = Item::factory()->withStock(10, 4)->create();

    expect($item->availableQuantity())->toBe(6);
});

it('tidak pernah mengembalikan jumlah tersedia negatif', function (): void {
    $item = Item::factory()->make(['stock_quantity' => 3, 'reserved_quantity' => 5]);

    expect($item->availableQuantity())->toBe(0);
});

it('mengusulkan jumlah pembelian hingga batas maksimum', function (): void {
    $item = Item::factory()->withStock(3)->create(['min_stock' => 5, 'max_stock' => 10]);

    expect($item->suggestedPurchaseQuantity())->toBe(7);
});

/*
|--------------------------------------------------------------------------
| Pencarian & filter
|--------------------------------------------------------------------------
*/

it('mencari item berdasarkan kode maupun nama', function (): void {
    Item::factory()->create(['item_code' => '1709000002', 'item_name' => 'BALL LINER BIRU']);
    Item::factory()->create(['item_code' => '1709000031', 'item_name' => 'PERMANENT MARKER']);

    expect(Item::query()->search('BALL')->count())->toBe(1)
        ->and(Item::query()->search('1709000031')->count())->toBe(1)
        ->and(Item::query()->search('')->count())->toBe(2);
});

it('mendaftar item yang perlu dibeli', function (): void {
    Item::factory()->withStock(3)->create(['min_stock' => 5, 'max_stock' => 10]);
    Item::factory()->withStock(7)->create(['min_stock' => 5, 'max_stock' => 10]);
    Item::factory()->withStock(15)->create(['min_stock' => 5, 'max_stock' => 10]);

    expect(Item::query()->needsRestock()->count())->toBe(1);
});
