<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Notification\Notifications\PendingApprovalNotification;
use App\Modules\Purchasing\Enums\PurchaseStatus;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Models\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/*
| N12 — pengingat approval tertunda (command approvals:remind).
|
| Dokumen yang menunggu di satu tahap lebih lama dari ambang hari dikirimi
| pengingat ke approver yang berlaku pada tahap itu; yang masih baru tidak.
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

    $this->dept = $dept;
});

/** Membuat request pada status tertentu dengan updated_at yang dituakan. */
function agedRequest(User $requester, Department $dept, RequestStatus $status, int $daysAgo): Request
{
    $request = Request::factory()->create([
        'requester_id' => $requester->id,
        'department_id' => $dept->id,
        'status' => $status,
    ]);

    // Menua tanpa memicu timestamp otomatis.
    Request::query()->whereKey($request->id)->update(['updated_at' => now()->subDays($daysAgo)]);

    return $request;
}

it('mengingatkan approver dokumen yang tertunda melebihi ambang', function (): void {
    agedRequest($this->requester, $this->dept, RequestStatus::PendingSupervisor, 5);
    agedRequest($this->requester, $this->dept, RequestStatus::PendingStationery, 5);

    $purchase = Purchase::factory()->create([
        'status' => PurchaseStatus::PendingVerification,
        'created_by' => $this->pic->id,
    ]);
    Purchase::query()->whereKey($purchase->id)->update(['updated_at' => now()->subDays(5)]);

    Notification::fake();

    $this->artisan('approvals:remind', ['--days' => 2])->assertSuccessful();

    // PENDING_SUPERVISOR → atasan requester; PENDING_STATIONERY & pembelian → PIC.
    Notification::assertSentTo($this->supervisor, PendingApprovalNotification::class);
    Notification::assertSentToTimes($this->pic, PendingApprovalNotification::class, 2);
});

it('tidak mengingatkan dokumen yang masih baru', function (): void {
    agedRequest($this->requester, $this->dept, RequestStatus::PendingSupervisor, 0);

    Notification::fake();

    $this->artisan('approvals:remind', ['--days' => 2])->assertSuccessful();

    Notification::assertNothingSent();
});
