<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Otorisasi laporan Sprint 11.
|
| Laporan bersifat baca saja; penegakannya di tingkat permission. Yang diuji:
| user tanpa permission laporan terkait ditolak (403), yang punya diizinkan (200),
| dan permission satu jenis laporan tidak membuka jenis lain (matriks §5.1).
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);
});

function userWithRole(string ...$roles): User
{
    $user = User::factory()->create();
    $user->assignRole($roles);

    return $user;
}

it('menolak requester biasa dari seluruh laporan', function (): void {
    $requester = userWithRole(Role::Requester->value);

    $this->actingAs($requester)->get('/reports/stock-by-month')->assertForbidden();
    $this->actingAs($requester)->get('/reports/purchasing')->assertForbidden();
    $this->actingAs($requester)->get('/reports/need-to-buy')->assertForbidden();
});

it('mengizinkan PIC Stationery membuka laporan stok, pembelian, dan need to buy', function (): void {
    $pic = userWithRole(Role::Requester->value, Role::PicStationery->value);

    $this->actingAs($pic)->get('/reports/stock-by-month')->assertOk();
    $this->actingAs($pic)->get('/reports/stock-by-year')->assertOk();
    $this->actingAs($pic)->get('/reports/purchasing')->assertOk();
    $this->actingAs($pic)->get('/reports/need-to-buy')->assertOk();
});

it('mengizinkan PIC Gudang membuka laporan stok, pembelian, dan need to buy', function (): void {
    $gudang = userWithRole(Role::Requester->value, Role::WarehouseOfficer->value);

    $this->actingAs($gudang)->get('/reports/stock-by-month')->assertOk();
    $this->actingAs($gudang)->get('/reports/purchasing')->assertOk();
    $this->actingAs($gudang)->get('/reports/need-to-buy')->assertOk();
});

it('menolak Pimpinan User dari laporan stok yang bukan wewenangnya', function (): void {
    // Supervisor hanya memegang report.request.view, bukan report.stock.view.
    $supervisor = userWithRole(Role::Requester->value, Role::Supervisor->value);

    $this->actingAs($supervisor)->get('/reports/stock-by-month')->assertForbidden();
    $this->actingAs($supervisor)->get('/reports/need-to-buy')->assertForbidden();
});
