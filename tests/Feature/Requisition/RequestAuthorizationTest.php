<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Models\Request;
use App\Modules\Requisition\Services\RequestApprovalService;
use App\Modules\Requisition\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);

    $seksiA = Department::factory()->create(['code' => 'SIT']);
    $seksiB = Department::factory()->create(['code' => 'FIN']);

    // Dua pimpinan dari seksi berbeda — inti pengujian T6.
    $this->pimpinanA = User::factory()->create(['department_id' => $seksiA->id]);
    $this->pimpinanA->assignRole([Role::Requester->value, Role::Supervisor->value]);

    $this->pimpinanB = User::factory()->create(['department_id' => $seksiB->id]);
    $this->pimpinanB->assignRole([Role::Requester->value, Role::Supervisor->value]);

    $this->requester = User::factory()->create([
        'department_id' => $seksiA->id,
        'manager_id' => $this->pimpinanA->id,
    ]);
    $this->requester->assignRole(Role::Requester->value);

    $this->stationery = User::factory()->create(['department_id' => $seksiB->id]);
    $this->stationery->assignRole([Role::Requester->value, Role::PicStationery->value]);

    $this->sga = User::factory()->create(['department_id' => $seksiB->id]);
    $this->sga->assignRole([Role::Requester->value, Role::SgaManager->value]);

    $this->item = Item::factory()->withStock(100)->create();
});

function makeRequest(int $qty = 10): Request
{
    $test = test();

    $request = app(RequestService::class)->create($test->requester, [
        ['item_id' => $test->item->id, 'quantity' => $qty],
    ]);

    return app(RequestService::class)->submit($request);
}

/*
|--------------------------------------------------------------------------
| Alur approval terpadu — L1 oleh Pimpinan mana pun (seluruh seksi)
|--------------------------------------------------------------------------
*/

it('mengizinkan approval Level 1 oleh Pimpinan seksi mana pun', function (): void {
    /*
     * Alur terpadu: L1 (Pimpinan SIT) berbasis role global dan berlaku untuk
     * SELURUH seksi — bukan atasan langsung. Pimpinan B, dari seksi berbeda dari
     * requester, tetap boleh menyetujui L1.
     */
    $request = makeRequest();

    $this->actingAs($this->pimpinanB)
        ->post("/requests/verify/{$request->id}/approve")
        ->assertRedirect('/requests/verify');

    expect($request->refresh()->status)->toBe(RequestStatus::PendingStationery);
});

it('mengizinkan approval Level 1 oleh atasan langsung', function (): void {
    $request = makeRequest();

    $this->actingAs($this->pimpinanA)
        ->post("/requests/verify/{$request->id}/approve")
        ->assertRedirect('/requests/verify');

    expect($request->refresh()->status)->toBe(RequestStatus::PendingStationery);
});

it('mengizinkan penolakan Level 1 oleh Pimpinan seksi mana pun', function (): void {
    $request = makeRequest();

    $this->actingAs($this->pimpinanB)
        ->post("/requests/verify/{$request->id}/reject", ['rejection_notes' => 'tidak setuju'])
        ->assertRedirect();

    expect($request->refresh()->status)->toBe(RequestStatus::RejectedSupervisor);
});

it('menampilkan request pada antrian seluruh Pimpinan (alur terpadu)', function (): void {
    // Setiap Pimpinan (L1) melihat semua request yang menunggu L1, tak peduli
    // seksi requester-nya.
    makeRequest();

    $this->actingAs($this->pimpinanA)
        ->get('/requests/verify')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('requests.total', 1));

    $this->actingAs($this->pimpinanB)
        ->get('/requests/verify')
        ->assertInertia(fn ($page) => $page->where('requests.total', 1));
});

/*
|--------------------------------------------------------------------------
| Batas kewenangan antar level
|--------------------------------------------------------------------------
*/

it('menolak PIC Stationery memutuskan sebelum gilirannya', function (): void {
    $request = makeRequest();

    $this->actingAs($this->stationery)
        ->post("/requests/verify/{$request->id}/approve")
        ->assertForbidden();
});

it('menolak Pimpinan SGA memutuskan sebelum gilirannya', function (): void {
    $request = makeRequest();

    $this->actingAs($this->sga)
        ->post("/requests/verify/{$request->id}/approve")
        ->assertForbidden();
});

it('menolak requester menyetujui requestnya sendiri', function (): void {
    $request = makeRequest();

    $this->actingAs($this->requester)
        ->post("/requests/verify/{$request->id}/approve")
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Kerahasiaan antar requester
|--------------------------------------------------------------------------
*/

it('menolak requester melihat request milik orang lain', function (): void {
    $lain = User::factory()->create(['manager_id' => $this->pimpinanB->id]);
    $lain->assignRole(Role::Requester->value);

    $request = makeRequest();

    $this->actingAs($lain)->get("/requests/{$request->id}")->assertForbidden();
});

it('mengizinkan atasan langsung melihat request bawahannya', function (): void {
    $request = makeRequest();

    $this->actingAs($this->pimpinanA)
        ->get("/requests/{$request->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Requests/Show'));
});

it('membatasi daftar request pada milik sendiri', function (): void {
    makeRequest();

    $lain = User::factory()->create(['manager_id' => $this->pimpinanB->id]);
    $lain->assignRole(Role::Requester->value);

    $this->actingAs($lain)
        ->get('/requests')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('requests.total', 0));
});

/*
|--------------------------------------------------------------------------
| Dua jalur revisi — aktor tidak boleh tertukar
|--------------------------------------------------------------------------
*/

it('menolak PIC Stationery merevisi penolakan Pimpinan User', function (): void {
    // Bab 3.6 menugaskan revisi ini kepada REQUESTER.
    $request = app(RequestApprovalService::class)
        ->rejectBySupervisor(makeRequest(), $this->pimpinanA, 'Jumlah berlebihan');

    $this->actingAs($this->stationery)
        ->get("/requests/{$request->id}/revise")
        ->assertForbidden();
});

it('menolak requester merevisi penolakan Pimpinan SGA', function (): void {
    // Bab 3.7 menugaskan revisi ini kepada PIC STATIONERY.
    $approvals = app(RequestApprovalService::class);

    $request = $approvals->approveBySupervisor(makeRequest(), $this->pimpinanA);
    $request = $approvals->approveByStationery($request, $this->stationery, [
        $request->items()->sole()->id => ['quantity' => 10],
    ]);
    $request = $approvals->rejectBySga($request, $this->sga, 'Kurangi jumlahnya');

    $this->actingAs($this->requester)
        ->get("/requests/{$request->id}/revise")
        ->assertForbidden();
});

it('mengizinkan requester merevisi penolakan Pimpinan User', function (): void {
    $request = app(RequestApprovalService::class)
        ->rejectBySupervisor(makeRequest(), $this->pimpinanA, 'Jumlah berlebihan');

    $this->actingAs($this->requester)
        ->get("/requests/{$request->id}/revise")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Requests/Revise'));
});

/*
|--------------------------------------------------------------------------
| Alur lewat HTTP
|--------------------------------------------------------------------------
*/

it('membuat request dan langsung mengantrikannya ke Pimpinan', function (): void {
    // Wireframe 3.1.2 hanya punya tombol "Submit Request".
    $this->actingAs($this->requester)->post('/requests', [
        'items' => [['item_id' => $this->item->id, 'quantity' => 10]],
    ])->assertRedirect('/requests');

    expect(Request::sole()->status)->toBe(RequestStatus::PendingSupervisor);
});

it('menolak request tanpa item', function (): void {
    $this->actingAs($this->requester)
        ->post('/requests', ['items' => []])
        ->assertSessionHasErrors('items');
});

it('menolak request atas item non-aktif', function (): void {
    $nonAktif = Item::factory()->inactive()->create();

    $this->actingAs($this->requester)
        ->post('/requests', ['items' => [['item_id' => $nonAktif->id, 'quantity' => 1]]])
        ->assertSessionHasErrors('items.0.item_id');
});

it('menolak pembatalan setelah kuantitas ditetapkan', function (): void {
    // Stok sudah dikunci; pembatalan sepihak tidak lagi diizinkan.
    $approvals = app(RequestApprovalService::class);

    $request = $approvals->approveBySupervisor(makeRequest(), $this->pimpinanA);
    $request = $approvals->approveByStationery($request, $this->stationery, [
        $request->items()->sole()->id => ['quantity' => 10],
    ]);

    $this->actingAs($this->requester)
        ->post("/requests/{$request->id}/cancel")
        ->assertForbidden();
});
