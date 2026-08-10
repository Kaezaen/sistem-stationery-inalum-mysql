<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ItemView->value);
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can(Permission::ItemView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CategoryManage->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can(Permission::CategoryManage->value);
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can(Permission::CategoryManage->value);
    }
}
