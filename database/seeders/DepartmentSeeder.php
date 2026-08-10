<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Departemen awal.
 *
 * SIT dan SGA diambil dari blueprint: SIT muncul sebagai seksi requester pada
 * wireframe, SGA sebagai unit pemilik proses dan pemberi approval final.
 * Struktur lengkap organisasi diisi lewat import data HR sebelum go-live —
 * lihat risiko K1 pada roadmap.
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'SGA', 'name' => 'Support & General Affairs', 'account_code' => null],
            ['code' => 'SIT', 'name' => 'System & Information Technology', 'account_code' => null],
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
