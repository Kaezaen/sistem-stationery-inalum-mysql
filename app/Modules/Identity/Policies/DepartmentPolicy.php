<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UserManage->value);
    }

    public function view(User $user, Department $department): bool
    {
        return $user->can(Permission::UserManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UserManage->value);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can(Permission::UserManage->value);
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can(Permission::UserManage->value);
    }
}
