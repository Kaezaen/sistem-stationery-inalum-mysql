<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Enums\Permission as PermissionEnum;
use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Models\User;
use App\Shared\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Menampilkan matriks role-permission secara read-only.
 *
 * Role dan permission sengaja TIDAK dapat diubah lewat UI: keduanya adalah
 * turunan langsung matriks §5.1 Architecture Blueprint yang dikunci di
 * Permission::matrix() dan diterapkan oleh RolePermissionSeeder. Mengizinkan
 * perubahan lewat UI akan membuat kode dan basis data saling bertentangan tanpa
 * jejak — persis jenis penyimpangan yang paling sulit dilacak saat audit.
 *
 * Perubahan kewenangan dilakukan lewat perubahan kode + seeder, sehingga
 * tercatat di version control.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        $this->authorize('manageRoles', User::class);

        $matrix = PermissionEnum::matrix();

        $roles = array_map(
            static fn (RoleEnum $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
                'permissions' => $matrix[$role->value] ?? [],
                'users_count' => Role::query()
                    ->where('name', $role->value)
                    ->withCount('users')
                    ->value('users_count') ?? 0,
            ],
            RoleEnum::cases(),
        );

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'allPermissions' => PermissionEnum::values(),
        ]);
    }
}
