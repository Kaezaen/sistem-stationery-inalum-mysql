<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Urutan penting: role & permission harus ada sebelum user dibuat, dan
     * departemen harus ada sebelum user karena users.department_id wajib diisi.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DepartmentSeeder::class,
            CategorySeeder::class,
            UomSeeder::class,
            StationeryItemSeeder::class,
        ]);

        // Organisasi & akun (karyawan nyata + approver) hanya untuk non-produksi.
        if (! app()->environment('production')) {
            $this->call(EmployeeSeeder::class);
        }
    }
}
