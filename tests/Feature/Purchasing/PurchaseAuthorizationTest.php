<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Purchasing\Enums\PurchaseStatus;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);

    $this->gudang = User::factory()->create();
    $this->gudang->assignRole([Role::Requester->value, Role::WarehouseOfficer->value]);

    $this->stationery = User::factory()->create();
    $this->stationery->assignRole([Role::Requester->value, Role::PicStationery->value]);
});

/*
|--------------------------------------------------------------------------
| Pemisahan tugas — kontrol inti alur 3.9 & 3.10
|--------------------------------------------------------------------------
*/

it('melarang pembuat memverifikasi pembeliannya sendiri', function (): void {
    /*
     * Inti kontrol alur pembelian: stok tidak boleh bertambah tanpa pemeriksaan
     * pihak kedua. Diuji pada user yang SENGAJA diberi kedua role sekaligus,
     * karena matriks permission saja tidak akan menangkap kasus ini — hanya
     * Policy yang mengetahui siapa pembuat dokumennya.
     */
    $serbaBisa = User::factory()->create();
    $serbaBisa->assignRole([
        Role::Requester->value,
        Role::WarehouseOfficer->value,
        Role::PicStationery->value,
    ]);

    $item = Item::factory()->withStock(0)->create();

    $purchase = app(PurchaseService::class)->create([
        'purchase_number' => '111000222000',
        'purchase_date' => now()->toDateString(),
        'supplier_name' => 'TOKO ABC',
    ], [['item_id' => $item->id, 'quantity' => 10]], $serbaBisa);

    app(PurchaseService::class)->submit($purchase);

    $this->actingAs($serbaBisa)
        ->post("/purchases/verify/{$purchase->id}")
        ->assertForbidden();

    expect($item->refresh()->stock_quantity)->toBe(0)
        ->and(InventoryTransaction::count())->toBe(0);
});

it('mengizinkan PIC Stationery memverifikasi pembelian orang lain', function (): void {
    $item = Item::factory()->withStock(0)->create();
    $purchase = Purchase::factory()->pending()->create(['created_by' => $this->gudang->id]);
    $purchase->items()->create(['item_id' => $item->id, 'quantity' => 15]);

    $this->actingAs($this->stationery)
        ->post("/purchases/verify/{$purchase->id}")
        ->assertRedirect('/purchases/verify');

    expect($item->refresh()->stock_quantity)->toBe(15);
});

/*
|--------------------------------------------------------------------------
| Batas kewenangan per role
|--------------------------------------------------------------------------
*/

it('menolak requester biasa membuka daftar pembelian', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::Requester->value);

    $this->actingAs($user)->get('/purchases')->assertForbidden();
});

it('menolak PIC Stationery membuat pembelian', function (): void {
    // PIC Stationery memverifikasi, tidak menginput — pembagian peran 3.9/3.10.
    $this->actingAs($this->stationery)->get('/purchases/create')->assertForbidden();
});

it('menolak PIC Gudang memverifikasi pembelian siapa pun', function (): void {
    $purchase = Purchase::factory()->pending()->create(['created_by' => $this->stationery->id]);

    $this->actingAs($this->gudang)
        ->post("/purchases/verify/{$purchase->id}")
        ->assertForbidden();
});

it('mengizinkan PIC Gudang membuka form pembelian', function (): void {
    $this->actingAs($this->gudang)
        ->get('/purchases/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Purchases/Create'));
});

/*
|--------------------------------------------------------------------------
| Penyuntingan dokumen
|--------------------------------------------------------------------------
*/

it('melarang menyunting pembelian milik orang lain', function (): void {
    $lain = User::factory()->create();
    $lain->assignRole([Role::Requester->value, Role::WarehouseOfficer->value]);

    $purchase = Purchase::factory()->rejected()->create(['created_by' => $this->gudang->id]);

    $this->actingAs($lain)->get("/purchases/{$purchase->id}/edit")->assertForbidden();
});

it('melarang menyunting pembelian yang sudah diverifikasi', function (): void {
    // Menyunting dokumen yang sudah menaikkan stok membuat ledger dan dokumen
    // sumbernya tidak lagi cocok.
    $purchase = Purchase::factory()->create([
        'created_by' => $this->gudang->id,
        'status' => PurchaseStatus::Verified,
        'verified_by' => $this->stationery->id,
        'verification_date' => now(),
    ]);

    $this->actingAs($this->gudang)->get("/purchases/{$purchase->id}/edit")->assertForbidden();
});

it('mengizinkan pembuat menyunting pembelian yang ditolak', function (): void {
    $purchase = Purchase::factory()->rejected()->create(['created_by' => $this->gudang->id]);

    $this->actingAs($this->gudang)
        ->get("/purchases/{$purchase->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Purchases/Edit'));
});

it('menolak menghapus pembelian yang sudah menaikkan stok', function (): void {
    $purchase = Purchase::factory()->create([
        'created_by' => $this->gudang->id,
        'status' => PurchaseStatus::Verified,
        'verified_by' => $this->stationery->id,
        'verification_date' => now(),
    ]);

    // Policy update() menolak lebih dulu karena status tidak lagi editable.
    $this->actingAs($this->gudang)
        ->delete("/purchases/{$purchase->id}")
        ->assertForbidden();

    expect(Purchase::find($purchase->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Alur lewat HTTP
|--------------------------------------------------------------------------
*/

it('menyimpan pembelian dan langsung mengantrikannya untuk verifikasi', function (): void {
    // Wireframe 3.9.2 hanya punya tombol Simpan — tidak ada langkah "ajukan"
    // terpisah, sehingga dokumen harus langsung masuk antrian.
    $item = Item::factory()->create();

    $this->actingAs($this->gudang)->post('/purchases', [
        'purchase_number' => '111234567866',
        'purchase_date' => now()->toDateString(),
        'supplier_name' => 'TOKO SUPPLIER ABC',
        'items' => [['item_id' => $item->id, 'quantity' => 90]],
    ])->assertRedirect('/purchases');

    $purchase = Purchase::where('purchase_number', '111234567866')->firstOrFail();

    expect($purchase->status)->toBe(PurchaseStatus::PendingVerification);
});

it('menolak nomor pembelian yang sudah pernah diinput', function (): void {
    // Faktur yang sama terinput dua kali akan menaikkan stok berganda.
    Purchase::factory()->create(['purchase_number' => '111234567866']);
    $item = Item::factory()->create();

    $this->actingAs($this->gudang)->post('/purchases', [
        'purchase_number' => '111234567866',
        'purchase_date' => now()->toDateString(),
        'supplier_name' => 'TOKO LAIN',
        'items' => [['item_id' => $item->id, 'quantity' => 5]],
    ])->assertSessionHasErrors('purchase_number');
});

it('menolak penolakan tanpa alasan lewat HTTP', function (): void {
    $purchase = Purchase::factory()->pending()->create(['created_by' => $this->gudang->id]);

    $this->actingAs($this->stationery)
        ->post("/purchases/reject/{$purchase->id}", ['rejection_notes' => ''])
        ->assertSessionHasErrors('rejection_notes');
});

it('menampilkan antrian verifikasi per tab', function (): void {
    Purchase::factory()->pending()->create(['created_by' => $this->gudang->id]);
    Purchase::factory()->rejected()->create(['created_by' => $this->gudang->id]);

    $this->actingAs($this->stationery)
        ->get('/purchases/verify')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Purchases/Verify/Index')
            ->where('purchases.total', 1),
        );

    $this->actingAs($this->stationery)
        ->get('/purchases/verify?tab=rejected')
        ->assertInertia(fn ($page) => $page->where('purchases.total', 1));
});
