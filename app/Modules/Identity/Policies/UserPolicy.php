<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UserManage->value);
    }

    public function view(User $user, User $target): bool
    {
        // Setiap orang boleh melihat profilnya sendiri.
        return $user->id === $target->id
            || $user->can(Permission::UserManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UserManage->value);
    }

    public function update(User $user, User $target): bool
    {
        return $user->can(Permission::UserManage->value);
    }

    /**
     * Administrator tidak boleh menonaktifkan atau menghapus dirinya sendiri.
     *
     * Tanpa aturan ini, satu-satunya administrator dapat mengunci dirinya keluar
     * dari sistem dan menyisakan aplikasi tanpa pengelola sama sekali.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->id !== $target->id
            && $user->can(Permission::UserManage->value);
    }

    public function manageRoles(User $user): bool
    {
        return $user->can(Permission::RoleManage->value);
    }

    /** Melihat struktur hierarki atasan — mitigasi risiko K1. */
    public function viewHierarchy(User $user): bool
    {
        return $user->can(Permission::UserManage->value);
    }
}
