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
 * Organisasi demo — dummy namun menyerupai struktur Inalum, dirangkai membentuk
 * hierarki atasan yang benar sehingga SELURUH alur approval dapat diuji.
 *
 * TIDAK dijalankan di produksi (lihat DatabaseSeeder). Aturan hierarki yang dijaga:
 *   - Setiap staff (requester) punya atasan seorang Pimpinan (MS, role supervisor),
 *     sehingga request-nya punya tujuan approval Level 1 yang valid.
 *   - Setiap Pimpinan (MS) beratasan VP divisinya (juga role supervisor), sehingga
 *     Pimpinan pun dapat mengajukan request.
 *   - VP adalah pucuk organisasi (jabatan tertinggi pada model STAFF/MS/VP) dan
 *     `administrator` memang bukan aktor request — keduanya sengaja tanpa atasan.
 *
 * Approval Level 2 (PIC Stationery) dan Level 3 (Pimpinan SGA) berbasis ROLE global
 * (D2), jadi tidak bergantung pada rantai atasan: berapa pun departemen requester,
 * L2 selalu jatuh ke PIC Stationery dan L3 ke VP SGA.
 *
 * Kata sandi seragam: password
 */
class DemoUserSeeder extends Seeder
{
    public function run(UserService $users): void
    {
        /** @var array<string, int> $dept  kode departemen => id */
        $dept = Department::query()->pluck('id', 'code')->all();

        $vp = UserPosition::VicePresident->value;
        $ms = UserPosition::ManagerSection->value;
        $staff = UserPosition::Staff->value;

        $supervisor = Role::Supervisor->value;

        /**
         * Urut dari atas ke bawah agar setiap atasan sudah dibuat sebelum
         * bawahannya dirujuk. `manager` berisi username atasan (null = pucuk).
         *
         * @var list<array{nip: string, username: string, name: string, dept: string, position: string, manager: string|null, roles: list<string>}> $people
         */
        $people = [
            // --- Vice President (pucuk organisasi, tanpa atasan) ---
            ['nip' => 'SGA001', 'username' => 'vp.sga', 'name' => 'Valention Siburian', 'dept' => 'SGA', 'position' => $vp, 'manager' => null, 'roles' => [Role::SgaManager->value, $supervisor]],
            ['nip' => 'OPS001', 'username' => 'vp.ops', 'name' => 'Rudi Hartono', 'dept' => 'OPS', 'position' => $vp, 'manager' => null, 'roles' => [$supervisor]],
            ['nip' => 'FKT001', 'username' => 'vp.fin', 'name' => 'Sitti Rahmawati', 'dept' => 'FKT', 'position' => $vp, 'manager' => null, 'roles' => [$supervisor]],

            // --- Pimpinan seksi / Manager Section (approver Level 1) ---
            ['nip' => 'GA001', 'username' => 'ms.ga', 'name' => 'Bambang Sutrisno', 'dept' => 'GA', 'position' => $ms, 'manager' => 'vp.sga', 'roles' => [$supervisor]],
            ['nip' => 'PRC001', 'username' => 'ms.prc', 'name' => 'Dewi Anggraini', 'dept' => 'PRC', 'position' => $ms, 'manager' => 'vp.sga', 'roles' => [$supervisor]],
            ['nip' => 'RED001', 'username' => 'ms.red', 'name' => 'Agus Salim', 'dept' => 'RED', 'position' => $ms, 'manager' => 'vp.ops', 'roles' => [$supervisor]],
            ['nip' => 'CAS001', 'username' => 'ms.cas', 'name' => 'Hendra Wijaya', 'dept' => 'CAS', 'position' => $ms, 'manager' => 'vp.ops', 'roles' => [$supervisor]],
            ['nip' => 'CAR001', 'username' => 'ms.car', 'name' => 'Maruli Simanjuntak', 'dept' => 'CAR', 'position' => $ms, 'manager' => 'vp.ops', 'roles' => [$supervisor]],
            ['nip' => 'MEC001', 'username' => 'ms.mec', 'name' => 'Yusuf Maulana', 'dept' => 'MEC', 'position' => $ms, 'manager' => 'vp.ops', 'roles' => [$supervisor]],
            ['nip' => 'ELE001', 'username' => 'ms.ele', 'name' => 'Rizky Pratama', 'dept' => 'ELE', 'position' => $ms, 'manager' => 'vp.ops', 'roles' => [$supervisor]],
            ['nip' => 'FIN001', 'username' => 'ms.fin', 'name' => 'Lina Marlina', 'dept' => 'FIN', 'position' => $ms, 'manager' => 'vp.fin', 'roles' => [$supervisor]],
            ['nip' => 'HRD001', 'username' => 'ms.hrd', 'name' => 'Tono Sugiarto', 'dept' => 'HRD', 'position' => $ms, 'manager' => 'vp.fin', 'roles' => [$supervisor]],
            ['nip' => 'SIT001', 'username' => 'ms.sit', 'name' => 'Emil Salim', 'dept' => 'SIT', 'position' => $ms, 'manager' => 'vp.fin', 'roles' => [$supervisor]],

            // --- Fungsi SGA global: PIC Stationery (L2) & PIC Gudang ---
            ['nip' => 'GA002', 'username' => 'pic.stationery', 'name' => 'Sari Puspita', 'dept' => 'GA', 'position' => $staff, 'manager' => 'ms.ga', 'roles' => [Role::PicStationery->value]],
            ['nip' => 'GA003', 'username' => 'pic.gudang', 'name' => 'Joko Purnomo', 'dept' => 'GA', 'position' => $staff, 'manager' => 'ms.ga', 'roles' => [Role::WarehouseOfficer->value]],

            // --- Administrator (bukan aktor request; sengaja tanpa atasan) ---
            ['nip' => 'ADM001', 'username' => 'admin', 'name' => 'Administrator Sistem', 'dept' => 'SIT', 'position' => $staff, 'manager' => null, 'roles' => [Role::Administrator->value]],

            // --- Staff requester (beratasan Pimpinan seksinya) ---
            ['nip' => 'SIT002', 'username' => 'mawan', 'name' => 'Mawan Irwansyah', 'dept' => 'SIT', 'position' => $staff, 'manager' => 'ms.sit', 'roles' => []],
            ['nip' => 'SIT003', 'username' => 'rina', 'name' => 'Rina Kartika', 'dept' => 'SIT', 'position' => $staff, 'manager' => 'ms.sit', 'roles' => []],
            ['nip' => 'GA004', 'username' => 'budi', 'name' => 'Budi Santoso', 'dept' => 'GA', 'position' => $staff, 'manager' => 'ms.ga', 'roles' => []],
            ['nip' => 'PRC002', 'username' => 'andi', 'name' => 'Andi Nugroho', 'dept' => 'PRC', 'position' => $staff, 'manager' => 'ms.prc', 'roles' => []],
            ['nip' => 'RED002', 'username' => 'dani', 'name' => 'Dani Ramadhan', 'dept' => 'RED', 'position' => $staff, 'manager' => 'ms.red', 'roles' => []],
            ['nip' => 'RED003', 'username' => 'eka', 'name' => 'Eka Putri', 'dept' => 'RED', 'position' => $staff, 'manager' => 'ms.red', 'roles' => []],
            ['nip' => 'CAS002', 'username' => 'fitri', 'name' => 'Fitri Handayani', 'dept' => 'CAS', 'position' => $staff, 'manager' => 'ms.cas', 'roles' => []],
            ['nip' => 'CAR002', 'username' => 'gunawan', 'name' => 'Gunawan Saputra', 'dept' => 'CAR', 'position' => $staff, 'manager' => 'ms.car', 'roles' => []],
            ['nip' => 'MEC002', 'username' => 'bayu', 'name' => 'Bayu Prakoso', 'dept' => 'MEC', 'position' => $staff, 'manager' => 'ms.mec', 'roles' => []],
            ['nip' => 'ELE002', 'username' => 'indra', 'name' => 'Indra Lesmana', 'dept' => 'ELE', 'position' => $staff, 'manager' => 'ms.ele', 'roles' => []],
            ['nip' => 'FIN002', 'username' => 'wawan', 'name' => 'Wawan Setiawan', 'dept' => 'FIN', 'position' => $staff, 'manager' => 'ms.fin', 'roles' => []],
            ['nip' => 'HRD002', 'username' => 'nia', 'name' => 'Nia Rahmawati', 'dept' => 'HRD', 'position' => $staff, 'manager' => 'ms.hrd', 'roles' => []],
        ];

        /** @var array<string, User> $created  username => User */
        $created = [];

        foreach ($people as $person) {
            $attributes = [
                'employee_id' => $person['nip'],
                'username' => $person['username'],
                'name' => $person['name'],
                'email' => $person['username'].'@inalum.id',
                'department_id' => $dept[$person['dept']],
                'position' => $person['position'],
            ];

            if ($person['manager'] !== null) {
                $attributes['manager_id'] = $created[$person['manager']]->id;
            }

            $created[$person['username']] = $this->make($users, $attributes, $person['roles']);
        }

        // Kepala tiap departemen. MTN memakai VP Operasi (Operasi & Pemeliharaan
        // berbagi satu VP pada struktur ini).
        $heads = [
            'SGA' => 'vp.sga', 'OPS' => 'vp.ops', 'MTN' => 'vp.ops', 'FKT' => 'vp.fin',
            'GA' => 'ms.ga', 'PRC' => 'ms.prc', 'RED' => 'ms.red', 'CAS' => 'ms.cas',
            'CAR' => 'ms.car', 'MEC' => 'ms.mec', 'ELE' => 'ms.ele', 'FIN' => 'ms.fin',
            'HRD' => 'ms.hrd', 'SIT' => 'ms.sit',
        ];

        foreach ($heads as $code => $headUsername) {
            Department::where('code', $code)->update(['head_user_id' => $created[$headUsername]->id]);
        }

        $this->command?->info('Organisasi demo dibuat: '.count($created).' akun. Kata sandi seragam: password');
        $this->command?->warn('Jangan pernah menjalankan seeder ini di produksi.');
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
