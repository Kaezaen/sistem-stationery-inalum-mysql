<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Exceptions\ManagerCycleException;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);
});

function makeAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Administrator->value);

    return $user;
}

/*
|--------------------------------------------------------------------------
| Otorisasi
|--------------------------------------------------------------------------
*/

it('menolak user tanpa kewenangan membuka kelola user', function (): void {
    $user = User::factory()->create();
    $user->assignRole(Role::Requester->value);

    $this->actingAs($user)->get('/admin/users')->assertForbidden();
});

it('mengizinkan administrator membuka kelola user', function (): void {
    $this->actingAs(makeAdmin())
        ->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Users/Index'));
});

it('menolak requester membuat user lewat endpoint langsung', function (): void {
    // Menyembunyikan tombol di React tidak menghalangi pemanggilan endpoint.
    // Inilah alasan setiap action wajib diperiksa Policy di sisi server.
    $user = User::factory()->create();
    $user->assignRole(Role::Requester->value);

    $this->actingAs($user)
        ->post('/admin/users', ['name' => 'Penyusup'])
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Pembuatan user
|--------------------------------------------------------------------------
*/

it('membuat user beserta rolenya', function (): void {
    $department = Department::factory()->create();

    $this->actingAs(makeAdmin())->post('/admin/users', [
        'employee_id' => 'SIT999',
        'username' => 'baru',
        'name' => 'User Baru',
        'email' => 'baru@inalum.id',
        'password' => 'RahasiaKuat#2026',
        'password_confirmation' => 'RahasiaKuat#2026',
        'department_id' => $department->id,
        'position' => 'STAFF',
        'is_active' => true,
        'roles' => [Role::PicStationery->value],
    ])->assertRedirect('/admin/users');

    $created = User::where('username', 'baru')->firstOrFail();

    expect($created->hasRole(Role::PicStationery->value))->toBeTrue();
});

it('selalu melekatkan role requester pada setiap user', function (): void {
    // "Role dasar seluruh pegawai" — tanpa ini user tidak dapat mengajukan
    // request sama sekali, padahal itu hak setiap pegawai.
    $department = Department::factory()->create();

    $user = app(UserService::class)->create([
        'employee_id' => 'SIT888',
        'username' => 'tanpa.role',
        'name' => 'Tanpa Role',
        'email' => 'tanpa.role@inalum.id',
        'password' => 'RahasiaKuat#2026',
        'department_id' => $department->id,
    ], []);

    expect($user->hasRole(Role::Requester->value))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Siklus atasan — risiko K1
|--------------------------------------------------------------------------
*/

it('menolak user menjadi atasan dirinya sendiri', function (): void {
    $user = User::factory()->create();

    expect(fn () => app(UserService::class)->guardAgainstManagerCycle($user, $user->id))
        ->toThrow(ManagerCycleException::class);
});

it('menolak siklus atasan dua tingkat', function (): void {
    // A atasan B; menetapkan B sebagai atasan A membentuk lingkaran tertutup —
    // rantai approval tidak akan pernah mencapai puncak.
    $a = User::factory()->create();
    $b = User::factory()->create(['manager_id' => $a->id]);

    expect(fn () => app(UserService::class)->guardAgainstManagerCycle($a, $b->id))
        ->toThrow(ManagerCycleException::class);
});

it('menolak siklus atasan tiga tingkat', function (): void {
    $a = User::factory()->create();
    $b = User::factory()->create(['manager_id' => $a->id]);
    $c = User::factory()->create(['manager_id' => $b->id]);

    expect(fn () => app(UserService::class)->guardAgainstManagerCycle($a, $c->id))
        ->toThrow(ManagerCycleException::class);
});

it('menerima rantai atasan yang sah', function (): void {
    $vp = User::factory()->create();
    $ms = User::factory()->create(['manager_id' => $vp->id]);
    $staff = User::factory()->create();

    app(UserService::class)->guardAgainstManagerCycle($staff, $ms->id);
})->throwsNoExceptions();

it('mengeluarkan bawahan dari daftar kandidat atasan', function (): void {
    // Mencegah siklus terpilih dari UI sejak awal.
    $manager = User::factory()->create();
    $subordinate = User::factory()->create(['manager_id' => $manager->id]);
    $grandchild = User::factory()->create(['manager_id' => $subordinate->id]);

    $ids = array_column(app(UserService::class)->managerCandidates($manager), 'id');

    expect($ids)
        ->not->toContain($manager->id)
        ->not->toContain($subordinate->id)
        ->not->toContain($grandchild->id);
});

it('mengenali atasan langsung', function (): void {
    $manager = User::factory()->create();
    $staff = User::factory()->create(['manager_id' => $manager->id]);
    $orang_lain = User::factory()->create();

    expect($manager->isDirectManagerOf($staff))->toBeTrue()
        ->and($orang_lain->isDirectManagerOf($staff))->toBeFalse()
        ->and($staff->isDirectManagerOf($manager))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Perlindungan akun sendiri
|--------------------------------------------------------------------------
*/

it('melarang administrator menghapus akunnya sendiri', function (): void {
    // Tanpa aturan ini, satu-satunya administrator dapat mengunci dirinya keluar
    // dan menyisakan sistem tanpa pengelola.
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->delete("/admin/users/{$admin->id}")
        ->assertForbidden();
});
