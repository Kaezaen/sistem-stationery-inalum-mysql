<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Enums\UserPosition;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Organisasi nyata + akun approver — untuk lingkungan non-produksi (lihat DatabaseSeeder).
 *
 * Alur approval TERPADU: setiap karyawan dari seksi mana pun mengikuti satu alur
 *   requester -> Pimpinan SIT (L1) -> PIC Stationery (L2) -> Pimpinan SGA (L3) -> PIC Gudang.
 * Ketiga approver berbasis ROLE global (bukan atasan langsung), sehingga karyawan
 * tidak perlu punya manager_id.
 *
 * Data karyawan dibaca dari database/data/employees.csv (namecode, nama, seksi).
 * Berkas itu memuat PII (nama + NIK) dan SENGAJA tidak di-commit (lihat .gitignore);
 * seeder ini melewatinya dengan aman bila berkas tidak ada. Kata sandi seragam: password.
 */
class EmployeeSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(UserService $users): void
    {
        $this->seedApprovers($users);

        $path = database_path('data/employees.csv');

        if (! is_file($path)) {
            $this->command?->warn('Data karyawan (database/data/employees.csv) tidak ada — hanya akun approver dibuat.');

            return;
        }

        [$rows, $sections] = $this->readCsv($path);

        foreach ($sections as $code) {
            Department::updateOrCreate(['code' => $code], ['name' => $code, 'is_active' => true]);
        }

        /** @var array<string, int> $deptIds */
        $deptIds = Department::query()->pluck('id', 'code')->all();

        $this->seedEmployees($rows, $deptIds);
    }

    /**
     * Lima akun approver/fungsi — dibuat lewat UserService agar aturan role dijaga.
     */
    private function seedApprovers(UserService $users): void
    {
        $sit = Department::updateOrCreate(['code' => 'SIT'], ['name' => 'System & Information Technology', 'is_active' => true])->id;
        $sga = Department::updateOrCreate(['code' => 'SGA'], ['name' => 'Support & General Affairs', 'is_active' => true])->id;

        /** @var list<array{0: string, 1: string, 2: string, 3: int, 4: UserPosition, 5: Role}> $accounts */
        $accounts = [
            ['SIT001', 'pimpinan.sit', 'Pimpinan SIT', $sit, UserPosition::ManagerSection, Role::Supervisor],
            ['SGA001', 'pic.stationery', 'PIC Stationery', $sga, UserPosition::Staff, Role::PicStationery],
            ['SGA002', 'vp.sga', 'Pimpinan SGA', $sga, UserPosition::VicePresident, Role::SgaManager],
            ['SGA003', 'pic.gudang', 'PIC Gudang', $sga, UserPosition::Staff, Role::WarehouseOfficer],
            ['SGA004', 'admin', 'Administrator Sistem', $sga, UserPosition::Staff, Role::Administrator],
        ];

        foreach ($accounts as [$nip, $username, $name, $deptId, $position, $role]) {
            $attributes = [
                'employee_id' => $nip,
                'username' => $username,
                'name' => $name,
                'email' => $username.'@inalum.id',
                'department_id' => $deptId,
                'position' => $position->value,
            ];

            $existing = User::where('username', $username)->first();

            if ($existing !== null) {
                $users->update($existing, $attributes, [$role->value]);
            } else {
                $users->create([...$attributes, 'password' => self::PASSWORD], [$role->value]);
            }
        }

        $this->command?->info('Approver: pimpinan.sit (L1) · pic.stationery (L2) · vp.sga (L3) · pic.gudang · admin.');
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $rows
     * @param  array<string, int>  $deptIds
     */
    private function seedEmployees(array $rows, array $deptIds): void
    {
        // Satu hash dipakai ulang untuk 1930 akun — menghitung bcrypt per baris
        // akan makan menit; semua memakai kata sandi seragam untuk UAT.
        $hash = Hash::make(self::PASSWORD);
        $now = now();

        /** @var \Illuminate\Support\Collection<string, int> $existing */
        $existing = User::query()->pluck('id', 'username');

        $chunk = [];
        $usernames = [];
        $created = 0;

        foreach ($rows as [$code, $nama, $seksi]) {
            if ($existing->has($code) || ! isset($deptIds[$seksi])) {
                continue;
            }

            $usernames[] = $code;
            $chunk[] = [
                'employee_id' => $code,
                'username' => $code,
                'name' => $nama !== '' ? $nama : $code,
                'email' => $code.'@inalum.id',
                'password' => $hash,
                'department_id' => $deptIds[$seksi],
                'position' => UserPosition::Staff->value,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) >= 500) {
                User::insert($chunk);
                $created += count($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            User::insert($chunk);
            $created += count($chunk);
        }

        $this->assignRequesterRole($usernames);

        $this->command?->info("Karyawan ter-seed: {$created} akun requester (username = namecode, sandi: password).");
    }

    /**
     * Pasang role `requester` massal lewat pivot langsung — jauh lebih cepat
     * daripada syncRoles per user untuk ribuan baris.
     *
     * @param  list<string>  $usernames
     */
    private function assignRequesterRole(array $usernames): void
    {
        if ($usernames === []) {
            return;
        }

        $roleId = DB::table('roles')
            ->where('name', Role::Requester->value)
            ->where('guard_name', 'web')
            ->value('id');

        if ($roleId === null) {
            return;
        }

        $morph = (new User)->getMorphClass();

        User::query()
            ->whereIn('username', $usernames)
            ->select('id')
            ->chunk(1000, function ($chunkUsers) use ($roleId, $morph): void {
                $pivot = $chunkUsers->map(static fn (User $u): array => [
                    'role_id' => $roleId,
                    'model_type' => $morph,
                    'model_id' => $u->id,
                ])->all();

                DB::table('model_has_roles')->insertOrIgnore($pivot);
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array{0: list<array{0: string, 1: string, 2: string}>, 1: list<string>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [[], []];
        }

        fgetcsv($handle); // lewati header

        $rows = [];
        $sections = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                continue;
            }

            $code = trim((string) $row[0]);
            $nama = trim((string) $row[1]);
            $seksi = trim((string) $row[2]);

            if ($code === '' || $seksi === '') {
                continue;
            }

            $rows[] = [$code, $nama, $seksi];
            $sections[$seksi] = true;
        }

        fclose($handle);

        return [$rows, array_keys($sections)];
    }
}
