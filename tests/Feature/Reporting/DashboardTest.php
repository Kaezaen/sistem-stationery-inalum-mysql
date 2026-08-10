<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Reporting\Services\DashboardService;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Models\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Dashboard monitoring (fitur 5) — payload disesuaikan kewenangan.
|
| Dashboard adalah halaman depan SEMUA pengguna, jadi yang paling penting diuji:
| requester biasa tidak menerima statistik organisasi/stok yang bukan haknya, dan
| Pimpinan User hanya melihat statistik departemennya.
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);
});

it('memberi requester biasa hanya ringkasan request miliknya', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::Requester->value);

    Request::factory()->count(2)->create(['requester_id' => $user->id]);
    Request::factory()->create();   // milik orang lain — tidak dihitung

    $data = app(DashboardService::class)->forUser($user);

    expect($data['myRequests']['total'])->toBe(2)
        ->and($data['orgRequests'])->toBeNull()
        ->and($data['stock'])->toBeNull();
});

it('memberi PIC Stationery statistik organisasi dan stok', function (): void {
    $user = User::factory()->create();
    $user->assignRole([Role::Requester->value, Role::PicStationery->value]);

    $data = app(DashboardService::class)->forUser($user);

    expect($data['orgRequests'])->not->toBeNull()
        ->and($data['orgRequests'])->toHaveKeys(['byStatus', 'trend', 'topItems'])
        ->and($data['stock'])->not->toBeNull()
        ->and($data['orgRequests']['trend'])->toHaveCount(6);
});

it('membatasi statistik Pimpinan User ke departemennya sendiri', function (): void {
    $ownDept = Department::factory()->create();
    $otherDept = Department::factory()->create();

    $supervisor = User::factory()->create(['department_id' => $ownDept->id]);
    $supervisor->assignRole([Role::Requester->value, Role::Supervisor->value]);

    Request::factory()->count(2)->create([
        'department_id' => $ownDept->id,
        'status' => RequestStatus::Completed,
    ]);
    Request::factory()->create([
        'department_id' => $otherDept->id,
        'status' => RequestStatus::Completed,
    ]);

    $data = app(DashboardService::class)->forUser($supervisor);

    $selesai = collect($data['orgRequests']['byStatus'])
        ->firstWhere('label', RequestStatus::Completed->label());

    // Hanya 2 dari departemennya sendiri, bukan 3.
    expect($selesai['count'])->toBe(2);
});

it('merender halaman dashboard', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::Requester->value);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->has('data.myRequests'),
        );
});
