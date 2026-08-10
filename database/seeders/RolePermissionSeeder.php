<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Enums\Permission as PermissionEnum;
use App\Modules\Identity\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menerapkan matriks §5.1 Architecture Blueprint ke basis data.
 *
 * Idempoten — aman dijalankan berulang kali, termasuk saat deploy. Permission
 * yang dihapus dari Permission::matrix() akan ikut dicabut dari role, sehingga
 * kode tetap menjadi satu-satunya sumber kebenaran kewenangan.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            foreach (PermissionEnum::values() as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            $matrix = PermissionEnum::matrix();

            foreach (RoleEnum::cases() as $roleEnum) {
                $role = Role::findOrCreate($roleEnum->value, 'web');

                // syncPermissions (bukan givePermissionTo) agar pencabutan
                // kewenangan di kode ikut tercermin di basis data.
                $role->syncPermissions($matrix[$roleEnum->value] ?? []);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'Sinkron %d permission ke %d role.',
            count(PermissionEnum::values()),
            count(RoleEnum::cases()),
        ));
    }
}
