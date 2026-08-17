<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Collection;

/**
 * Menentukan penerima tiap notifikasi (matriks §9.1).
 *
 * Ditempatkan di Support/ (bukan Services/) karena konvensi menuntut berkas
 * Services/ berakhiran "Service"; "Resolver" lebih tepat menggambarkan perannya.
 * Diisolasi agar aturan "siapa menerima apa" mudah dibaca dan diuji, terpisah dari
 * mekanik pengiriman. Notification boleh bergantung pada Identity (arah ke bawah).
 *
 * Hanya user AKTIF yang dikembalikan — pegawai non-aktif tidak perlu dikirimi
 * approval yang tidak akan mereka kerjakan.
 */
class RecipientResolver
{
    /**
     * Seluruh user aktif yang memegang sebuah role.
     *
     * @return Collection<int, User>
     */
    public function withRole(Role $role): Collection
    {
        return User::query()
            ->role($role->value)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Satu user tertentu bila masih aktif (mis. requester, pembuat pembelian).
     *
     * @return Collection<int, User>
     */
    public function only(?User $user): Collection
    {
        return $user instanceof User && $user->is_active ? collect([$user]) : collect();
    }
}
