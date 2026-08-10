<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Enums\UserPosition;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use Illuminate\Database\Seeder;

/**
 * Satu akun per aktor, dirangkai membentuk hierarki atasan yang benar.
 *
 * TIDAK dijalankan di produksi (lihat DatabaseSeeder). Tujuannya agar seluruh
 * alur approval Fase 5 dapat diuji tanpa menunggu data HR selesai diimpor.
 *
 * Kata sandi seragam: password
 */
class DemoUserSeeder extends Seeder
{
    public function run(UserService $users): void
    {
        $sga = Department::where('code', 'SGA')->firstOrFail();
        $sit = Department::where('code', 'SIT')->firstOrFail();

        $admin = $this->make($users, [
            'employee_id' => 'ADM001',
            'username' => 'admin',
            'name' => 'Administrator Sistem',
            'email' => 'admin@inalum.id',
            'department_id' => $sga->id,
            'position' => UserPosition::Staff->value,
        ], [Role::Administrator->value]);

        // Pimpinan SGA — approver Level 3, tanpa atasan (pucuk rantai approval).
        $sgaManager = $this->make($users, [
            'employee_id' => 'SGA001',
            'username' => 'vp.sga',
            'name' => 'Valention Siburian',
            'email' => 'vp.sga@inalum.id',
            'department_id' => $sga->id,
            'position' => UserPosition::VicePresident->value,
        ], [Role::SgaManager->value]);

        // Pimpinan seksi requester — approver Level 1.
        $supervisor = $this->make($users, [
            'employee_id' => 'SIT001',
            'username' => 'ms.sit',
            'name' => 'Emil Salim',
            'email' => 'ms.sit@inalum.id',
            'department_id' => $sit->id,
            'position' => UserPosition::ManagerSection->value,
        ], [Role::Supervisor->value]);

        $this->make($users, [
            'employee_id' => 'SGA002',
            'username' => 'pic.stationery',
            'name' => 'PIC Stationery',
            'email' => 'pic.stationery@inalum.id',
            'department_id' => $sga->id,
            'position' => UserPosition::Staff->value,
            'manager_id' => $sgaManager->id,
        ], [Role::PicStationery->value]);

        $this->make($users, [
            'employee_id' => 'SGA003',
            'username' => 'pic.gudang',
            'name' => 'PIC Gudang',
            'email' => 'pic.gudang@inalum.id',
            'department_id' => $sga->id,
            'position' => UserPosition::Staff->value,
            'manager_id' => $sgaManager->id,
        ], [Role::WarehouseOfficer->value]);

        // Requester biasa — atasannya MS SIT, sehingga approval L1 request
        // miliknya jatuh ke user tersebut.
        $this->make($users, [
            'employee_id' => 'SIT002',
            'username' => 'mawan',
            'name' => 'Mawan Irwansyah',
            'email' => 'mawan@inalum.id',
            'department_id' => $sit->id,
            'position' => UserPosition::Staff->value,
            'manager_id' => $supervisor->id,
        ], []);

        $sit->update(['head_user_id' => $supervisor->id]);
        $sga->update(['head_user_id' => $sgaManager->id]);

        $this->command?->info('Akun demo dibuat. Kata sandi seragam: password');
        $this->command?->warn('Jangan pernah menjalankan seeder ini di produksi.');

        unset($admin);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $roles
     */
    private function make(UserService $users, array $attributes, array $roles): User
    {
        $existing = User::where('username', $attributes['username'])->first();

        if ($existing !== null) {
            return $users->update($existing, $attributes, $roles);
        }

        return $users->create([...$attributes, 'password' => 'password'], $roles);
    }
}
