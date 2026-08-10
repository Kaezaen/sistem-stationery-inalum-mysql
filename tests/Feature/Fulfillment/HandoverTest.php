<?php

declare(strict_types=1);

use App\Modules\Approval\Exceptions\InvalidStateTransitionException;
use App\Modules\Catalog\Models\Item;
use App\Modules\Fulfillment\Services\HandoverService;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Inventory\Enums\TransactionType;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Requisition\Enums\RequestItemStatus;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Models\Request;
use App\Modules\Requisition\Services\RequestApprovalService;
use App\Modules\Requisition\Services\RequestService;
use App\Shared\Exceptions\BusinessRuleException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);

    $department = Department::factory()->create();

    $this->supervisor = User::factory()->create(['department_id' => $department->id]);
    $this->supervisor->assignRole([Role::Requester->value, Role::Supervisor->value]);

    $this->requester = User::factory()->create([
        'department_id' => $department->id,
        'manager_id' => $this->supervisor->id,
    ]);
    $this->requester->assignRole(Role::Requester->value);

    $this->stationery = User::factory()->create(['department_id' => $department->id]);
    $this->stationery->assignRole([Role::Requester->value, Role::PicStationery->value]);

    $this->sga = User::factory()->create(['department_id' => $department->id]);
    $this->sga->assignRole([Role::Requester->value, Role::SgaManager->value]);

    $this->gudang = User::factory()->create(['department_id' => $department->id]);
    $this->gudang->assignRole([Role::Requester->value, Role::WarehouseOfficer->value]);

    $this->item = Item::factory()->withStock(100)->create();
    $this->handover = app(HandoverService::class);
});

/** Membawa satu request sampai berstatus siap diserahkan. */
function readyRequest(int $requested = 10, ?int $approved = null): Request
{
    $t = test();
    $requests = app(RequestService::class);
    $approvals = app(RequestApprovalService::class);

    $request = $requests->create($t->requester, [
        ['item_id' => $t->item->id, 'quantity' => $requested],
    ]);
    $request = $requests->submit($request);
    $request = $approvals->approveBySupervisor($request, $t->supervisor);

    $request = $approvals->approveByStationery($request, $t->stationery, [
        $request->items()->sole()->id => ['quantity' => $approved ?? $requested],
    ]);

    return $approvals->approveBySga($request, $t->sga);
}

/*
|--------------------------------------------------------------------------
| Penyerahan penuh
|--------------------------------------------------------------------------
*/

it('mengurangi stok fisik dan menyelesaikan request', function (): void {
    $request = readyRequest(10, 7);

    expect($this->item->refresh()->reserved_quantity)->toBe(7);

    $request = $this->handover->handover($request, $this->gudang);

    $this->item->refresh();
    $line = $request->items()->sole();

    expect($request->status)->toBe(RequestStatus::Completed)
        ->and($request->completed_at)->not->toBeNull()
        // Stok fisik berkurang, reservasi lunas.
        ->and($this->item->stock_quantity)->toBe(93)
        ->and($this->item->reserved_quantity)->toBe(0)
        ->and($line->quantity_actual)->toBe(7)
        ->and($line->status)->toBe(RequestItemStatus::Issued);
});

it('mencatat pergerakan keluar di ledger beserta dokumen sumbernya', function (): void {
    $request = readyRequest(10, 7);

    $this->handover->handover($request, $this->gudang);

    $ledger = InventoryTransaction::where('item_id', $this->item->id)->sole();

    expect($ledger->transaction_type)->toBe(TransactionType::Out)
        ->and($ledger->quantity)->toBe(7)
        ->and($ledger->quantity_before)->toBe(100)
        ->and($ledger->quantity_after)->toBe(93)
        ->and($ledger->reference_type)->toBe(Request::class)
        ->and($ledger->reference_id)->toBe($request->id)
        ->and($ledger->performed_by)->toBe($this->gudang->id);
});

it('menandai reservasi sebagai terpakai', function (): void {
    $request = readyRequest(10, 7);
    $reservation = StockReservation::sole();

    $this->handover->handover($request, $this->gudang);

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Consumed);
});

/*
|--------------------------------------------------------------------------
| Penyerahan sebagian — keputusan D5
|--------------------------------------------------------------------------
*/

it('mengizinkan penyerahan kurang dari yang disetujui', function (): void {
    // Stok fisik bisa saja kurang dari yang dijanjikan saat approval.
    $request = readyRequest(10, 7);
    $line = $request->items()->sole();

    $request = $this->handover->handover($request, $this->gudang, [$line->id => 5]);

    expect($request->status)->toBe(RequestStatus::Completed)
        ->and($line->refresh()->quantity_actual)->toBe(5)
        ->and($line->status)->toBe(RequestItemStatus::Issued);
});

it('melepas sisa reservasi yang tidak jadi diserahkan', function (): void {
    /*
     * Bagian paling mudah terlewat pada penyerahan sebagian. Dijanjikan 7,
     * diserahkan 5 — sisa 2 HARUS kembali ke stok tersedia. Bila tidak,
     * 2 unit itu terkunci selamanya: barangnya ada di gudang tetapi tidak
     * dapat dipakai request siapa pun.
     */
    $request = readyRequest(10, 7);
    $line = $request->items()->sole();

    $this->handover->handover($request, $this->gudang, [$line->id => 5]);

    $this->item->refresh();

    expect($this->item->stock_quantity)->toBe(95)
        ->and($this->item->reserved_quantity)->toBe(0)
        ->and($this->item->availableQuantity())->toBe(95);
});

it('mencatat selisih penyerahan pada remark', function (): void {
    $request = readyRequest(10, 7);
    $line = $request->items()->sole();

    $this->handover->handover($request, $this->gudang, [$line->id => 5]);

    expect($line->refresh()->remark)->toContain('Diserahkan 5 dari 7');
});

it('menolak penyerahan melebihi yang disetujui', function (): void {
    $request = readyRequest(10, 7);
    $line = $request->items()->sole();

    expect(fn () => $this->handover->handover($request, $this->gudang, [$line->id => 9]))
        ->toThrow(BusinessRuleException::class);

    expect($this->item->refresh()->stock_quantity)->toBe(100)
        ->and(InventoryTransaction::count())->toBe(0);
});

it('menolak penyerahan yang seluruhnya nol', function (): void {
    // Sama saja dengan tidak menyerahkan apa pun — request tidak boleh
    // ditutup seolah-olah sudah dipenuhi.
    $request = readyRequest(10, 7);
    $line = $request->items()->sole();

    expect(fn () => $this->handover->handover($request, $this->gudang, [$line->id => 0]))
        ->toThrow(BusinessRuleException::class);

    expect($request->refresh()->status)->toBe(RequestStatus::ReadyForHandover);
});

/*
|--------------------------------------------------------------------------
| Integritas
|--------------------------------------------------------------------------
*/

it('menolak serah terima ganda', function (): void {
    $request = readyRequest(10, 7);

    $this->handover->handover($request, $this->gudang);

    expect(fn () => $this->handover->handover($request->refresh(), $this->gudang))
        ->toThrow(InvalidStateTransitionException::class);

    // Stok tidak boleh berkurang dua kali.
    expect($this->item->refresh()->stock_quantity)->toBe(93)
        ->and(InventoryTransaction::count())->toBe(1);
});

it('menolak serah terima sebelum disetujui Pimpinan SGA', function (): void {
    $requests = app(RequestService::class);
    $approvals = app(RequestApprovalService::class);

    $request = $requests->create($this->requester, [['item_id' => $this->item->id, 'quantity' => 5]]);
    $request = $approvals->approveBySupervisor($requests->submit($request), $this->supervisor);

    expect(fn () => $this->handover->handover($request, $this->gudang))
        ->toThrow(InvalidStateTransitionException::class);
});

it('menjaga saldo tetap selaras dengan ledger setelah serah terima', function (): void {
    $request = readyRequest(10, 7);

    $this->handover->handover($request, $this->gudang);

    $this->artisan('stock:reconcile')->assertSuccessful();
});

/*
|--------------------------------------------------------------------------
| Otorisasi
|--------------------------------------------------------------------------
*/

it('menolak requester menyerahkan barangnya sendiri', function (): void {
    $request = readyRequest(10, 7);

    $this->actingAs($this->requester)
        ->post("/handover/{$request->id}")
        ->assertForbidden();

    expect($this->item->refresh()->stock_quantity)->toBe(100);
});

it('menolak PIC Stationery melakukan serah terima', function (): void {
    // Pemisahan tugas: yang menetapkan kuantitas bukan yang menyerahkan barang.
    $request = readyRequest(10, 7);

    $this->actingAs($this->stationery)
        ->post("/handover/{$request->id}")
        ->assertForbidden();
});

it('mengizinkan PIC Gudang menyerahkan lewat HTTP', function (): void {
    $request = readyRequest(10, 7);
    $line = $request->items()->sole();

    $this->actingAs($this->gudang)
        ->post("/handover/{$request->id}", ['quantities' => [$line->id => 7]])
        ->assertRedirect("/handover/{$request->id}/receipt");

    expect($request->refresh()->status)->toBe(RequestStatus::Completed);
});

it('menampilkan antrian menunggu pengambilan', function (): void {
    readyRequest(10, 7);

    $this->actingAs($this->gudang)
        ->get('/handover')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Fulfillment/Index')
            ->where('requests.total', 1),
        );
});

it('menampilkan bukti serah terima', function (): void {
    $request = readyRequest(10, 7);
    $this->handover->handover($request, $this->gudang);

    $this->actingAs($this->gudang)
        ->get("/handover/{$request->id}/receipt")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Fulfillment/Receipt')
            ->where('request.request_number', $request->request_number),
        );
});

/*
|--------------------------------------------------------------------------
| Pembersih reservasi kedaluwarsa
|--------------------------------------------------------------------------
*/

it('melepas reservasi kedaluwarsa lewat perintah artisan', function (): void {
    readyRequest(10, 7);

    StockReservation::query()->update(['expires_at' => now()->subDay()]);

    $this->artisan('stock:release-expired-reservations')
        ->expectsOutputToContain('1 reservasi kedaluwarsa dilepas')
        ->assertSuccessful();

    expect($this->item->refresh()->reserved_quantity)->toBe(0);
});
