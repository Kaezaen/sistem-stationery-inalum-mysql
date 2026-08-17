<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Departemen dasar (produksi) — SGA (pemilik proses ATK & approval final) dan SIT
 * (pemegang approval L1 pada alur terpadu). Cukup untuk menampung akun approver.
 *
 * Struktur seksi lengkap organisasi diisi dari data HR: di non-produksi lewat
 * EmployeeSeeder (membaca database/data/employees.csv), di produksi lewat impor HR.
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'SGA', 'name' => 'Support & General Affairs', 'account_code' => '5400'],
            ['code' => 'SIT', 'name' => 'System & Information Technology', 'account_code' => '5303'],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                [
                    'name' => $department['name'],
                    'account_code' => $department['account_code'],
                    'is_active' => true,
                ],
            );
        }
    }
}
