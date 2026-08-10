<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Exceptions\ManagerCycleException;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi seluruh perubahan data user.
 *
 * Controller tidak boleh menyentuh model User secara langsung untuk operasi tulis —
 * penetapan atasan dan role punya aturan yang harus berlaku di semua jalur masuk
 * (form admin, seeder, import massal, artisan command).
 */
class UserService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $roles
     */
    public function create(array $data, array $roles = []): User
    {
        return DB::transaction(function () use ($data, $roles): User {
            $managerId = isset($data['manager_id']) ? (int) $data['manager_id'] : null;

            $user = User::create([
                'employee_id' => $data['employee_id'],
                'username' => $data['username'],
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'department_id' => $data['department_id'],
                'position' => $data['position'] ?? null,
                'manager_id' => $managerId,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Divalidasi setelah user ada agar id-nya dapat ikut diperiksa.
            if ($managerId !== null) {
                $this->guardAgainstManagerCycle($user, $managerId);
            }

            $this->syncRoles($user, $roles);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $roles  null = jangan sentuh role
     */
    public function update(User $user, array $data, ?array $roles = null): User
    {
        return DB::transaction(function () use ($user, $data, $roles): User {
            if (array_key_exists('manager_id', $data)) {
                $managerId = $data['manager_id'] === null ? null : (int) $data['manager_id'];

                if ($managerId !== null) {
                    $this->guardAgainstManagerCycle($user, $managerId);
                }
            }

            $attributes = array_filter(
                [
                    'employee_id' => $data['employee_id'] ?? null,
                    'username' => $data['username'] ?? null,
                    'name' => $data['name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'department_id' => $data['department_id'] ?? null,
                    'position' => $data['position'] ?? null,
                    'is_active' => $data['is_active'] ?? null,
                ],
                static fn (mixed $v): bool => $v !== null,
            );

            // manager_id ditangani terpisah karena null adalah nilai yang sah
            // (pucuk organisasi tidak punya atasan) sehingga tidak boleh difilter.
            if (array_key_exists('manager_id', $data)) {
                $attributes['manager_id'] = $data['manager_id'];
            }

            if (isset($data['password']) && $data['password'] !== '') {
                $attributes['password'] = $data['password'];
            }

            $user->update($attributes);

            if ($roles !== null) {
                $this->syncRoles($user, $roles);
            }

            return $user->refresh();
        });
    }

    /**
     * Setiap user selalu memegang role dasar `requester`.
     *
     * Blueprint menyebut Requester sebagai "role dasar seluruh pegawai", sehingga
     * role fungsional hanya memuat permission tambahan. Menegakkannya di sini
     * mencegah user tanpa hak mengajukan request sama sekali.
     *
     * @param  list<string>  $roles
     */
    public function syncRoles(User $user, array $roles): void
    {
        $roles[] = Role::Requester->value;

        $user->syncRoles(array_values(array_unique($roles)));
    }

    /**
     * Menolak penetapan atasan yang membentuk siklus.
     *
     * CHECK constraint di database hanya mencegah kasus paling sederhana
     * (user menjadi atasan dirinya sendiri). Siklus lebih panjang — A atasan B,
     * lalu B ditetapkan sebagai atasan A — hanya dapat dideteksi dengan menelusuri
     * rantai, dan itulah yang dilakukan di sini.
     *
     * @throws ManagerCycleException
     */
    public function guardAgainstManagerCycle(User $user, int $managerId): void
    {
        if ($managerId === $user->id) {
            throw ManagerCycleException::forChain([$user->name, $user->name]);
        }

        $chain = [$user->name];
        $seen = [$user->id => true];
        $currentId = $managerId;
        $depth = 0;

        while ($currentId !== null && $depth < 50) {
            $current = User::query()
                ->select(['id', 'name', 'manager_id'])
                ->find($currentId);

            if ($current === null) {
                return;
            }

            $chain[] = $current->name;

            if (isset($seen[$current->id])) {
                throw ManagerCycleException::forChain($chain);
            }

            $seen[$current->id] = true;
            $currentId = $current->manager_id;
            $depth++;
        }
    }

    /**
     * Kandidat atasan untuk seorang user.
     *
     * Mengecualikan dirinya sendiri DAN seluruh bawahannya (langsung maupun tidak),
     * sehingga siklus tidak mungkin dipilih dari UI sejak awal — validasi di atas
     * tinggal menjadi jaring pengaman untuk jalur non-UI.
     *
     * @return list<array{id: int, name: string, employee_id: string}>
     */
    public function managerCandidates(?User $exclude = null): array
    {
        $excludedIds = [];

        if ($exclude !== null) {
            $excludedIds = [$exclude->id, ...$this->descendantIds($exclude)];
        }

        return User::query()
            ->active()
            ->when($excludedIds !== [], fn ($q) => $q->whereNotIn('id', $excludedIds))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id'])
            ->map(static fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'employee_id' => $u->employee_id,
            ])
            ->all();
    }

    /**
     * Seluruh id bawahan secara rekursif.
     *
     * @return list<int>
     */
    public function descendantIds(User $user): array
    {
        /** @var list<int> $collected */
        $collected = [];
        $frontier = [$user->id];

        for ($depth = 0; $depth < 50; $depth++) {
            $children = User::query()
                ->whereIn('manager_id', $frontier)
                ->whereNotIn('id', [...$collected, $user->id])
                ->pluck('id')
                ->all();

            if (count($children) === 0) {
                break;
            }

            $collected = [...$collected, ...$children];
            $frontier = $children;
        }

        return array_values(array_unique($collected));
    }

    public function recordLogin(User $user): void
    {
        $user->forceFill(['last_login_at' => now()])->save();
    }
}
