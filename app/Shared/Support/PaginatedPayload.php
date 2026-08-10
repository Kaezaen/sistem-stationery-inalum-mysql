<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Closure;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Mengubah paginator Eloquent menjadi array siap kirim ke Inertia.
 *
 * Dipakai sebagai pengganti $paginator->through(): through() mempertahankan tipe
 * generik paginator sehingga penggantian model menjadi array tidak terlihat oleh
 * analisis statis. Helper ini memisahkan pemetaan dari struktur paginasi,
 * sehingga hasilnya tetap dapat diperiksa PHPStan.
 *
 * Bentuk keluarannya sengaja disamakan dengan interface Paginated<T> di
 * resources/js/types/index.d.ts.
 */
final class PaginatedPayload
{
    /**
     * @template TModel
     *
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @param  Closure(TModel): array<string, mixed>  $mapper
     * @return array<string, mixed>
     */
    public static function make(LengthAwarePaginator $paginator, Closure $mapper): array
    {
        $data = [];

        foreach ($paginator->items() as $item) {
            $data[] = $mapper($item);
        }

        return [
            'data' => $data,
            'links' => $paginator->linkCollection()->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
