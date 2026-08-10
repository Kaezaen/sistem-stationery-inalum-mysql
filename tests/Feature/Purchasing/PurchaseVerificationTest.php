<?php

declare(strict_types=1);

use App\Modules\Approval\Enums\ApprovalDecision;
use App\Modules\Approval\Exceptions\InvalidStateTransitionException;
use App\Modules\Approval\Models\Approval;
use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Purchasing\Enums\PurchaseStatus;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Services\PurchaseService;
use App\Shared\Exceptions\BusinessRuleException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);

    $this->gudang = User::factory()->create();
    $this->gudang->assignRole([Role::Requester->value, Role::WarehouseOfficer->value]);

    $this->stationery = User::factory()->create();
    $this->stationery->assignRole([Role::Requester->value, Role::PicStationery->value]);

    $this->service = app(PurchaseService::class);
});

/** Membuat pembelian yang sudah menunggu verifikasi. */
function pendingPurchase(User $creator, Item $item, int $qty = 10): Purchase
{
    $purchase = app(PurchaseService::class)->create([
        'purchase_number' => '11123456'.fake()->unique()->numerify('####'),
        'purchase_date' => now()->toDateString(),
        'supplier_name' => 'TOKO SUPPLIER ABC',
    ], [['item_id' => $item->id, 'quantity' => $qty]], $creator);

    return app(PurchaseService::class)->submit($purchase);
}

/*
|--------------------------------------------------------------------------
| Integrasi stok — titik paling kritis alur ini
|--------------------------------------------------------------------------
*/

it('tidak menaikkan stok saat pembelian baru diinput', function (): void {
    // Menaikkan stok saat input akan memaksa koreksi negatif bila kemudian
    // ditolak, dan merusak integritas ledger (§7 Architecture Blueprint).
    $item = Item::factory()->withStock(5)->create();

    pendingPurchase($this->gudang, $item, 20);

    expect($item->refresh()->stock_quantity)->toBe(5)
        ->and(InventoryTransaction::where('item_id', $item->id)->count())->toBe(0);
});

it('menaikkan stok tepat saat diverifikasi', function (): void {
    $item = Item::factory()->withStock(5)->create();
    $purchase = pendingPurchase($this->gudang, $item, 20);

    $this->service->verify($purchase, $this->stationery);

    $item->refresh();

    expect($item->stock_quantity)->toBe(25)
        ->and($purchase->refresh()->status)->toBe(PurchaseStatus::Verified);

    $ledger = InventoryTransaction::where('item_id', $item->id)->sole();

    expect($ledger->quantity)->toBe(20)
        ->and($ledger->quantity_before)->toBe(5)
        ->and($ledger->quantity_after)->toBe(25)
        // Ledger harus dapat ditelusuri kembali ke dokumen sumbernya.
        ->and($ledger->reference_type)->toBe(Purchase::class)
        ->and($ledger->reference_id)->toBe($purchase->id);
});

it('tidak menyentuh stok saat pembelian ditolak', function (): void {
    $item = Item::factory()->withStock(5)->create();
    $purchase = pendingPurchase($this->gudang, $item, 20);

    $this->service->reject($purchase, $this->stationery, 'Jumlah tidak sesuai fisik');

    expect($item->refresh()->stock_quantity)->toBe(5)
        ->and(InventoryTransaction::where('item_id', $item->id)->count())->toBe(0)
        ->and($purchase->refresh()->status)->toBe(PurchaseStatus::Rejected);
});

it('menaikkan stok seluruh baris dalam satu dokumen', function (): void {
    $a = Item::factory()->withStock(0)->create();
    $b = Item::factory()->withStock(3)->create();

    $purchase = $this->service->create([
        'purchase_number' => '111234567866',
        'purchase_date' => now()->toDateString(),
        'supplier_name' => 'TOKO SUPPLIER ABC',
    ], [
        ['item_id' => $a->id, 'quantity' => 90],
        ['item_id' => $b->id, 'quantity' => 50],
    ], $this->gudang);

    $this->service->submit($purchase);
    $this->service->verify($purchase, $this->stationery);

    expect($a->refresh()->stock_quantity)->toBe(90)
        ->and($b->refresh()->stock_quantity)->toBe(53)
        ->and(InventoryTransaction::count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Mesin status
|--------------------------------------------------------------------------
*/

it('menolak verifikasi ganda atas dokumen yang sama', function (): void {
    // Dua verifikator menekan tombol nyaris bersamaan: yang kedua harus gagal,
    // bukan menaikkan stok untuk kedua kalinya.
    $item = Item::factory()->withStock(0)->create();
    $purchase = pendingPurchase($this->gudang, $item, 10);

    $this->service->verify($purchase, $this->stationery);

    expect(fn () => $this->service->verify($purchase->refresh(), $this->stationery))
        ->toThrow(InvalidStateTransitionException::class);

    expect($item->refresh()->stock_quantity)->toBe(10)
        ->and(InventoryTransaction::where('item_id', $item->id)->count())->toBe(1);
});

it('menolak verifikasi dokumen yang masih draft', function (): void {
    $item = Item::factory()->withStock(0)->create();

    $purchase = $this->service->create([
        'purchase_number' => '111000111000',
        'purchase_date' => now()->toDateString(),
        'supplier_name' => 'TOKO ABC',
    ], [['item_id' => $item->id, 'quantity' => 5]], $this->gudang);

    expect(fn () => $this->service->verify($purchase, $this->stationery))
        ->toThrow(InvalidStateTransitionException::class);
});

it('menolak dokumen yang sudah diverifikasi', function (): void {
    // VERIFIED bersifat terminal: stok sudah bertambah dan mungkin sudah dipakai.
    $item = Item::factory()->withStock(0)->create();
    $purchase = pendingPurchase($this->gudang, $item, 10);

    $this->service->verify($purchase, $this->stationery);

    expect(fn () => $this->service->reject($purchase->refresh(), $this->stationery, 'berubah pikiran'))
        ->toThrow(InvalidStateTransitionException::class);
});

it('mengizinkan pengajuan ulang setelah ditolak', function (): void {
    $item = Item::factory()->withStock(0)->create();
    $purchase = pendingPurchase($this->gudang, $item, 10);

    $this->service->reject($purchase, $this->stationery, 'Jumlah tidak sesuai fisik');
    $revised = $this->service->revise($purchase->refresh());

    expect($revised->status)->toBe(PurchaseStatus::PendingVerification)
        ->and($revised->revision_count)->toBe(1)
        ->and($revised->rejection_notes)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Riwayat approval
|--------------------------------------------------------------------------
*/

it('mencatat keputusan verifikasi beserta pelakunya', function (): void {
    $item = Item::factory()->withStock(0)->create();
    $purchase = pendingPurchase($this->gudang, $item, 10);

    $this->service->verify($purchase, $this->stationery);

    $approval = Approval::where('approvable_id', $purchase->id)->sole();

    expect($approval->status)->toBe(ApprovalDecision::Approved)
        ->and($approval->approver_id)->toBe($this->stationery->id)
        ->and($approval->approvable_type)->toBe(Purchase::class)
        // Snapshot menjadi bukti apa yang sebenarnya diverifikasi.
        ->and($approval->snapshot['purchase_number'])->toBe($purchase->purchase_number);
});

it('menyimpan riwayat penolakan meski dokumen direvisi', function (): void {
    // Auditor harus tetap melihat bahwa dokumen ini pernah ditolak, oleh siapa,
    // dan dengan alasan apa — bukan hanya keadaan akhirnya.
    $item = Item::factory()->withStock(0)->create();
    $purchase = pendingPurchase($this->gudang, $item, 10);

    $this->service->reject($purchase, $this->stationery, 'Jumlah tidak sesuai fisik');
    $this->service->revise($purchase->refresh());

    $approvals = Approval::where('approvable_id', $purchase->id)->get();

    expect($approvals)->toHaveCount(1)
        ->and($approvals->first()->is_superseded)->toBeTrue()
        ->and($approvals->first()->rejection_notes)->toBe('Jumlah tidak sesuai fisik');
});

it('menolak penolakan tanpa alasan', function (): void {
    $item = Item::factory()->withStock(0)->create();
    $purchase = pendingPurchase($this->gudang, $item, 10);

    expect(fn () => $this->service->reject($purchase, $this->stationery, '   '))
        ->toThrow(BusinessRuleException::class);
});

/*
|--------------------------------------------------------------------------
| Validasi isi dokumen
|--------------------------------------------------------------------------
*/

it('menolak pembelian tanpa item', function (): void {
    expect(fn () => $this->service->create([
        'purchase_number' => '111222333444',
        'purchase_date' => now()->toDateString(),
        'supplier_name' => 'TOKO ABC',
    ], [], $this->gudang))->toThrow(BusinessRuleException::class);
});

it('menolak item yang sama pada dua baris', function (): void {
    $item = Item::factory()->create();

    expect(fn () => $this->service->create([
        'purchase_number' => '111222333555',
        'purchase_date' => now()->toDateString(),
        'supplier_name' => 'TOKO ABC',
    ], [
        ['item_id' => $item->id, 'quantity' => 5],
        ['item_id' => $item->id, 'quantity' => 3],
    ], $this->gudang))->toThrow(BusinessRuleException::class);
});
