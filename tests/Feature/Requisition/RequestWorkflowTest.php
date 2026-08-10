<?php

declare(strict_types=1);

use App\Modules\Approval\Exceptions\InvalidStateTransitionException;
use App\Modules\Approval\Models\Approval;
use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
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

    // Rantai organisasi lengkap: requester -> supervisor.
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

    $this->item = Item::factory()->withStock(100)->create();

    $this->requests = app(RequestService::class);
    $this->approvals = app(RequestApprovalService::class);
});

/** Membuat request yang sudah menunggu approval Pimpinan. */
function submittedRequest(int $qty = 10): Request
{
    $test = test();

    $request = $test->requests->create($test->requester, [
        ['item_id' => $test->item->id, 'quantity' => $qty],
    ]);

    return $test->requests->submit($request);
}

/*
|--------------------------------------------------------------------------
| Alur lengkap tiga level
|--------------------------------------------------------------------------
*/

it('melewati ketiga level approval hingga siap diserahkan', function (): void {
    $request = submittedRequest(10);
    expect($request->status)->toBe(RequestStatus::PendingSupervisor);

    $request = $this->approvals->approveBySupervisor($request, $this->supervisor);
    expect($request->status)->toBe(RequestStatus::PendingStationery);

    $line = $request->items()->sole();
    $request = $this->approvals->approveByStationery($request, $this->stationery, [
        $line->id => ['quantity' => 7, 'remark' => 'stok terbatas'],
    ]);
    expect($request->status)->toBe(RequestStatus::PendingSga);

    $request = $this->approvals->approveBySga($request, $this->sga);
    expect($request->status)->toBe(RequestStatus::ReadyForHandover);
});

it('memberi nomor request berurutan', function (): void {
    $a = submittedRequest();
    $b = submittedRequest();

    expect($a->request_number)->toBe('REQ001')
        ->and($b->request_number)->toBe('REQ002');
});

/*
|--------------------------------------------------------------------------
| Pengaman pengajuan
|--------------------------------------------------------------------------
*/

it('menolak pengajuan bila requester belum punya atasan (Pimpinan User)', function (): void {
    // Akar REQ004: requester tanpa manager_id membuat approval L1 tak punya
    // tujuan, sehingga request akan mandek permanen bila diloloskan.
    $tanpaAtasan = User::factory()->create([
        'department_id' => $this->requester->department_id,
        'manager_id' => null,
    ]);
    $tanpaAtasan->assignRole(Role::Requester->value);

    $request = $this->requests->create($tanpaAtasan, [
        ['item_id' => $this->item->id, 'quantity' => 5],
    ]);

    expect(fn () => $this->requests->submit($request))
        ->toThrow(BusinessRuleException::class, 'belum memiliki atasan');

    // Tidak masuk antrian approval — tetap Draft.
    expect($request->refresh()->status)->toBe(RequestStatus::Draft);
});

/*
|--------------------------------------------------------------------------
| Level 2 — approval KUANTITATIF
|--------------------------------------------------------------------------
*/

it('menyimpan kuantitas dan remark per baris', function (): void {
    // Inti temuan blueprint: approval L2 bukan stempel, melainkan penetapan
    // berapa yang benar-benar akan diberikan (wireframe 3.3.2).
    $request = $this->approvals->approveBySupervisor(submittedRequest(10), $this->supervisor);
    $line = $request->items()->sole();

    $this->approvals->approveByStationery($request, $this->stationery, [
        $line->id => ['quantity' => 7, 'remark' => 'ini catatan'],
    ]);

    $line->refresh();

    expect($line->quantity_approved)->toBe(7)
        ->and($line->remark)->toBe('ini catatan')
        ->and($line->status)->toBe(RequestItemStatus::PartiallyApproved);
});

it('menandai baris disetujui penuh maupun ditolak', function (): void {
    $itemB = Item::factory()->withStock(50)->create();

    $request = $this->requests->create($this->requester, [
        ['item_id' => $this->item->id, 'quantity' => 10],
        ['item_id' => $itemB->id, 'quantity' => 5],
    ]);
    $request = $this->approvals->approveBySupervisor($this->requests->submit($request), $this->supervisor);

    $lines = $request->items()->orderBy('id')->get();

    $this->approvals->approveByStationery($request, $this->stationery, [
        $lines[0]->id => ['quantity' => 10],
        $lines[1]->id => ['quantity' => 0],
    ]);

    expect($lines[0]->refresh()->status)->toBe(RequestItemStatus::Approved)
        ->and($lines[1]->refresh()->status)->toBe(RequestItemStatus::Rejected);
});

it('menolak kuantitas melebihi yang diminta', function (): void {
    // PIC Stationery hanya boleh MENGURANGI, tidak menambah.
    $request = $this->approvals->approveBySupervisor(submittedRequest(10), $this->supervisor);
    $line = $request->items()->sole();

    expect(fn () => $this->approvals->approveByStationery($request, $this->stationery, [
        $line->id => ['quantity' => 15],
    ]))->toThrow(BusinessRuleException::class);
});

it('menolak persetujuan yang mengosongkan seluruh baris', function (): void {
    // Menyetujui nol untuk semua baris sama dengan menolak, tetapi tanpa alasan
    // yang tercatat — harus lewat tombol "Ditolak Seluruhnya".
    $request = $this->approvals->approveBySupervisor(submittedRequest(10), $this->supervisor);
    $line = $request->items()->sole();

    expect(fn () => $this->approvals->approveByStationery($request, $this->stationery, [
        $line->id => ['quantity' => 0],
    ]))->toThrow(BusinessRuleException::class);
});

it('menolak keputusan yang melewatkan salah satu baris', function (): void {
    $itemB = Item::factory()->withStock(50)->create();

    $request = $this->requests->create($this->requester, [
        ['item_id' => $this->item->id, 'quantity' => 10],
        ['item_id' => $itemB->id, 'quantity' => 5],
    ]);
    $request = $this->approvals->approveBySupervisor($this->requests->submit($request), $this->supervisor);
    $lines = $request->items()->orderBy('id')->get();

    expect(fn () => $this->approvals->approveByStationery($request, $this->stationery, [
        $lines[0]->id => ['quantity' => 10],
    ]))->toThrow(BusinessRuleException::class);
});

/*
|--------------------------------------------------------------------------
| Reservasi stok — T9 & T10
|--------------------------------------------------------------------------
*/

it('mengunci stok saat PIC Stationery menyetujui', function (): void {
    $request = $this->approvals->approveBySupervisor(submittedRequest(10), $this->supervisor);
    $line = $request->items()->sole();

    $this->approvals->approveByStationery($request, $this->stationery, [
        $line->id => ['quantity' => 7],
    ]);

    $this->item->refresh();

    // Stok fisik belum berkurang — barangnya masih di gudang.
    expect($this->item->stock_quantity)->toBe(100)
        ->and($this->item->reserved_quantity)->toBe(7)
        ->and($this->item->availableQuantity())->toBe(93);
});

it('melepas reservasi saat Pimpinan SGA menolak', function (): void {
    // T9. Stok yang dikunci harus kembali tersedia untuk request lain.
    $request = $this->approvals->approveBySupervisor(submittedRequest(10), $this->supervisor);
    $line = $request->items()->sole();

    $request = $this->approvals->approveByStationery($request, $this->stationery, [
        $line->id => ['quantity' => 7],
    ]);
    expect($this->item->refresh()->reserved_quantity)->toBe(7);

    $this->approvals->rejectBySga($request, $this->sga, 'Anggaran tidak mencukupi');

    expect($this->item->refresh()->reserved_quantity)->toBe(0)
        ->and($this->item->availableQuantity())->toBe(100);
});

it('mencegah dua request mengunci stok yang sama', function (): void {
    // Inti ADR-07 pada alur request: stok terbatas 100, dua request masing-masing
    // meminta 60. Yang kedua tidak boleh ikut dikunci.
    $a = $this->approvals->approveBySupervisor(submittedRequest(60), $this->supervisor);
    $b = $this->approvals->approveBySupervisor(submittedRequest(60), $this->supervisor);

    $this->approvals->approveByStationery($a, $this->stationery, [
        $a->items()->sole()->id => ['quantity' => 60],
    ]);

    expect(fn () => $this->approvals->approveByStationery($b, $this->stationery, [
        $b->items()->sole()->id => ['quantity' => 60],
    ]))->toThrow(App\Modules\Inventory\Exceptions\InsufficientStockException::class);

    expect($this->item->refresh()->reserved_quantity)->toBe(60);
});

/*
|--------------------------------------------------------------------------
| Dua jalur revisi dengan aktor berbeda
|--------------------------------------------------------------------------
*/

it('mengembalikan penolakan Pimpinan User ke antrian Pimpinan', function (): void {
    // Bab 3.6 — direvisi REQUESTER, kembali ke level 1.
    $request = submittedRequest(10);
    $request = $this->approvals->rejectBySupervisor($request, $this->supervisor, 'Jumlah berlebihan');

    expect($request->status)->toBe(RequestStatus::RejectedSupervisor);

    $request = $this->approvals->revise($request, $this->requester);

    expect($request->status)->toBe(RequestStatus::PendingSupervisor)
        ->and($request->revision_count)->toBe(1);
});

it('mengembalikan penolakan Pimpinan SGA ke antrian SGA', function (): void {
    // Bab 3.7 — direvisi PIC STATIONERY, kembali ke level 3 (BUKAN ke level 1).
    $request = $this->approvals->approveBySupervisor(submittedRequest(10), $this->supervisor);
    $line = $request->items()->sole();

    $request = $this->approvals->approveByStationery($request, $this->stationery, [
        $line->id => ['quantity' => 10],
    ]);
    $request = $this->approvals->rejectBySga($request, $this->sga, 'Kurangi jumlahnya');

    expect($request->status)->toBe(RequestStatus::RejectedSga);

    $request = $this->approvals->revise($request, $this->stationery, [
        $line->id => ['quantity' => 5],
    ]);

    expect($request->status)->toBe(RequestStatus::PendingSga)
        ->and($line->refresh()->quantity_approved)->toBe(5)
        // Reservasi lama dilepas, diganti mengikuti kuantitas baru.
        ->and($this->item->refresh()->reserved_quantity)->toBe(5);
});

it('menyimpan riwayat penolakan meski request direvisi', function (): void {
    $request = submittedRequest(10);
    $request = $this->approvals->rejectBySupervisor($request, $this->supervisor, 'Jumlah berlebihan');
    $this->approvals->revise($request, $this->requester);

    $approval = Approval::where('approvable_id', $request->id)->sole();

    expect($approval->is_superseded)->toBeTrue()
        ->and($approval->rejection_notes)->toBe('Jumlah berlebihan');
});

/*
|--------------------------------------------------------------------------
| Mesin status
|--------------------------------------------------------------------------
*/

it('menjadikan penolakan PIC Stationery bersifat final', function (): void {
    // Keputusan D1 — tidak ada jalur revisi dari status ini.
    $request = $this->approvals->approveBySupervisor(submittedRequest(10), $this->supervisor);
    $request = $this->approvals->rejectByStationery($request, $this->stationery, 'Barang tidak tersedia');

    expect($request->status)->toBe(RequestStatus::RejectedStationery)
        ->and($request->status->isTerminal())->toBeTrue();

    expect(fn () => $this->approvals->revise($request, $this->requester))
        ->toThrow(InvalidStateTransitionException::class);
});

it('menolak approval ganda pada level yang sama', function (): void {
    // T7 — dua approver menekan tombol nyaris bersamaan.
    $request = submittedRequest(10);

    $this->approvals->approveBySupervisor($request, $this->supervisor);

    expect(fn () => $this->approvals->approveBySupervisor($request->refresh(), $this->supervisor))
        ->toThrow(InvalidStateTransitionException::class);
});

it('menolak melompati level approval', function (): void {
    // Request yang baru diajukan belum boleh disentuh SGA.
    $request = submittedRequest(10);

    expect(fn () => $this->approvals->approveBySga($request, $this->sga))
        ->toThrow(InvalidStateTransitionException::class);
});

it('menolak penolakan tanpa alasan di setiap level', function (): void {
    // T4 — berlaku seragam untuk ketiga level.
    $request = submittedRequest(10);

    expect(fn () => $this->approvals->rejectBySupervisor($request, $this->supervisor, '  '))
        ->toThrow(BusinessRuleException::class);
});
