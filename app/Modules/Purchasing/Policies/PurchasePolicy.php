<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Policies;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Purchasing\Models\Purchase;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PurchaseView->value);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->can(Permission::PurchaseView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PurchaseCreate->value);
    }

    /**
     * Hanya pembuat dokumen yang boleh menyunting, dan hanya selama dokumen
     * masih dapat disunting. Verifikator tidak boleh mengubah isi dokumen yang
     * ia periksa — itu akan meniadakan makna verifikasi.
     */
    public function update(User $user, Purchase $purchase): bool
    {
        return $user->can(Permission::PurchaseUpdate->value)
            && $purchase->created_by === $user->id
            && $purchase->status->isEditable();
    }

    /**
     * Pemisahan tugas: pembuat dokumen TIDAK boleh memverifikasi miliknya
     * sendiri. Tanpa aturan ini, stok dapat bertambah tanpa pemeriksaan pihak
     * kedua — kontrol yang justru menjadi inti alur 3.9 dan 3.10 blueprint.
     */
    public function verify(User $user, Purchase $purchase): bool
    {
        return $user->can(Permission::PurchaseVerify->value)
            && $purchase->created_by !== $user->id;
    }
}
