<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Notifications\PurchaseStatusNotification;
use App\Modules\Notification\Notifications\RequestStatusNotification;
use App\Modules\Purchasing\Events\PurchaseRejected;
use App\Modules\Purchasing\Events\PurchaseSubmitted;
use App\Modules\Purchasing\Events\PurchaseVerified;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Requisition\Events\RequestApproved;
use App\Modules\Requisition\Events\RequestCompleted;
use App\Modules\Requisition\Events\RequestRejected;
use App\Modules\Requisition\Events\RequestSubmitted;
use App\Modules\Requisition\Models\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/*
| Wiring notifikasi N1–N10 (matriks §9.1).
|
| Diuji dengan Notification::fake() — memverifikasi PENERIMA & KODE yang benar per
| event/level tanpa bergantung pada antrean/after-commit (yang diverifikasi manual
| di browser). Titik balik penolakan yang berbeda per level ikut dikunci di sini.
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);

    $dept = Department::factory()->create();

    $this->supervisor = User::factory()->create(['department_id' => $dept->id]);
    $this->supervisor->assignRole(Role::Supervisor->value);

    $this->requester = User::factory()->create([
        'department_id' => $dept->id,
        'manager_id' => $this->supervisor->id,
    ]);

    $this->pic = User::factory()->create(['department_id' => $dept->id]);
    $this->pic->assignRole(Role::PicStationery->value);

    $this->sga = User::factory()->create(['department_id' => $dept->id]);
    $this->sga->assignRole(Role::SgaManager->value);

    $this->gudang = User::factory()->create(['department_id' => $dept->id]);
    $this->gudang->assignRole(Role::WarehouseOfficer->value);

    $this->request = Request::factory()->create([
        'requester_id' => $this->requester->id,
        'department_id' => $dept->id,
    ]);

    Notification::fake();
});

function assertRequestCode(User $user, string $code): void
{
    Notification::assertSentTo(
        $user,
        RequestStatusNotification::class,
        fn (RequestStatusNotification $n): bool => $n->toArray($user)['code'] === $code,
    );
}

it('N1: request diajukan → Pimpinan User (atasan requester)', function (): void {
    RequestSubmitted::dispatch($this->request);

    assertRequestCode($this->supervisor, 'N1');
    Notification::assertNotSentTo($this->requester, RequestStatusNotification::class);
});

it('N2/N4/N6: persetujuan mengarah ke level berikutnya', function (): void {
    RequestApproved::dispatch($this->request, 1);
    assertRequestCode($this->pic, 'N2');

    RequestApproved::dispatch($this->request, 2);
    assertRequestCode($this->sga, 'N4');

    RequestApproved::dispatch($this->request, 3);
    assertRequestCode($this->gudang, 'N6');
    assertRequestCode($this->requester, 'N6'); // requester juga diberi tahu
});

it('N3/N5 penolakan L1/L2 → requester, N7 penolakan L3 → PIC Stationery', function (): void {
    RequestRejected::dispatch($this->request, 1, 'Anggaran tidak cukup');
    assertRequestCode($this->requester, 'N3');

    RequestRejected::dispatch($this->request, 3, 'Perlu revisi kuantitas');
    // Penolakan SGA kembali ke PIC Stationery, BUKAN requester (temuan blueprint).
    assertRequestCode($this->pic, 'N7');
});

it('N8: barang diserahkan → requester, in-app saja', function (): void {
    RequestCompleted::dispatch($this->request);

    Notification::assertSentTo(
        $this->requester,
        RequestStatusNotification::class,
        function (RequestStatusNotification $n, array $channels): bool {
            return $n->toArray($this->requester)['code'] === 'N8'
                && $channels === ['database']; // N8 tanpa email (matriks §9.1)
        },
    );
});

it('N9: pembelian diinput → PIC Stationery; N10: hasil verifikasi → pembuat', function (): void {
    $purchase = Purchase::factory()->create(['created_by' => $this->gudang->id]);

    PurchaseSubmitted::dispatch($purchase);
    Notification::assertSentTo(
        $this->pic,
        PurchaseStatusNotification::class,
        fn (PurchaseStatusNotification $n): bool => $n->toArray($this->pic)['code'] === 'N9',
    );

    PurchaseVerified::dispatch($purchase);
    Notification::assertSentTo($this->gudang, PurchaseStatusNotification::class);

    PurchaseRejected::dispatch($purchase, 'Fisik tidak sesuai');
    Notification::assertSentToTimes($this->gudang, PurchaseStatusNotification::class, 2);
});
