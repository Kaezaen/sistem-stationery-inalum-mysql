<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Layanan pendukung laporan: daftar opsi filter dan pembatasan lingkup penglihatan.
 *
 * SQL agregat tiap laporan ada di Query Object masing-masing (ADR-04); service ini
 * menampung yang lintas laporan — opsi kategori/departemen untuk kontrol filter,
 * dan aturan siapa boleh melihat data siapa.
 */
class ReportService
{
    /** @return list<array{id: int, name: string}> */
    public function categoryOptions(): array
    {
        return DB::table('categories')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (object $c): array => ['id' => (int) $c->id, 'name' => (string) $c->name])
            ->all();
    }

    /** @return list<array{id: int, name: string}> */
    public function departmentOptions(): array
    {
        return DB::table('departments')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (object $d): array => ['id' => (int) $d->id, 'name' => (string) $d->name])
            ->all();
    }

    /**
     * Departemen yang boleh dilihat user pada laporan request, atau null = semua.
     *
     * Matriks §5.1 memberi Pimpinan User permission report.request.view bertanda ◐
     * "unit sendiri" — ia hanya boleh melihat requestor dari departemennya. PIC
     * Stationery, Pimpinan SGA, dan Administrator memegang request.view.all
     * sehingga melihat seluruh departemen (null).
     *
     * Ini pembatasan LINGKUP, bukan izin akses: user dengan report.request.view
     * tetap boleh membuka laporannya, hanya barisnya yang disaring.
     *
     * @return list<int>|null
     */
    public function visibleDepartmentIds(User $user): ?array
    {
        if ($user->can(Permission::RequestViewAll->value)) {
            return null;
        }

        return [$user->department_id];
    }
}
