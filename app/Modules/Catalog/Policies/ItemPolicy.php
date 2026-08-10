<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;

class ItemPolicy
{
    /** Seluruh pegawai boleh melihat katalog — tanpa itu request tidak mungkin dibuat. */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ItemView->value);
    }

    public function view(User $user, Item $item): bool
    {
        return $user->can(Permission::ItemView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ItemCreate->value);
    }

    public function update(User $user, Item $item): bool
    {
        return $user->can(Permission::ItemUpdate->value);
    }

    public function delete(User $user, Item $item): bool
    {
        return $user->can(Permission::ItemDelete->value);
    }

    public function import(User $user): bool
    {
        return $user->can(Permission::ItemCreate->value);
    }
}
