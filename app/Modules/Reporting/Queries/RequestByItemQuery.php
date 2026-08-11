<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Modules\Reporting\Support\ReportFilters;
use App\Modules\Reporting\Support\ReportResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * R7 Request by Item — kuantitas diminta vs diserahkan per item.
 *
 * Sangat berguna untuk forecasting: selisih diminta − diserahkan menunjukkan item
 * yang sering tidak terpenuhi penuh. Berbasis request_date.
 *
 * quantity_actual bisa NULL (belum diserahkan) — COALESCE ke 0 agar penjumlahan
 * tidak menghasilkan NULL. $departmentIds membatasi lingkup untuk Pimpinan User.
 */
class RequestByItemQuery
{
    /** @param  list<int>|null  $departmentIds */
    public function handle(ReportFilters $filters, ?array $departmentIds = null): ReportResult
    {
        $rows = DB::table('request_items as ri')
            ->join('requests as r', 'r.id', '=', 'ri.request_id')
            ->join('items as i', 'i.id', '=', 'ri.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->whereBetween('r.request_date', [$filters->from, $filters->until])
            ->when(
                $departmentIds !== null,
                fn (Builder $q): Builder => $q->whereIn('r.department_id', $departmentIds ?? []),
            )
            ->when(
                $filters->categoryId,
                fn (Builder $q): Builder => $q->where('i.category_id', $filters->categoryId),
            )
            ->when($filters->search !== '', function (Builder $q) use ($filters): Builder {
                $like = '%'.$filters->search.'%';

                return $q->where(function (Builder $sub) use ($like): void {
                    $sub->where('i.item_name', 'like', $like)
                        ->orWhere('i.item_code', 'like', $like);
                });
            })
            ->groupBy('i.id', 'i.item_code', 'i.item_name', 'c.name')
            ->orderByDesc('qty_requested')
            ->orderBy('i.item_name')
            ->get([
                'i.item_code',
                'i.item_name',
                'c.name as category',
                DB::raw('SUM(ri.quantity_requested) as qty_requested'),
                DB::raw('COALESCE(SUM(ri.quantity_actual), 0) as qty_actual'),
            ]);

        $mapped = $rows->map(function (object $r): array {
            $requested = (int) $r->qty_requested;
            $actual = (int) $r->qty_actual;

            return [
                'item_code' => $r->item_code,
                'item_name' => $r->item_name,
                'category' => $r->category,
                'qty_requested' => $requested,
                'qty_actual' => $actual,
                'shortfall' => $requested - $actual,
            ];
        })->all();

        return new ReportResult(
            key: 'request-by-item',
            title: 'Laporan Request per Item',
            columns: [
                ['key' => 'item_code', 'label' => 'Item Code'],
                ['key' => 'item_name', 'label' => 'Item Name'],
                ['key' => 'category', 'label' => 'Kategori'],
                ['key' => 'qty_requested', 'label' => 'Qty Diminta', 'align' => 'right', 'numeric' => true],
                ['key' => 'qty_actual', 'label' => 'Qty Diserahkan', 'align' => 'right', 'numeric' => true],
                ['key' => 'shortfall', 'label' => 'Selisih', 'align' => 'right', 'numeric' => true],
            ],
            rows: $mapped,
            filterSchema: ['period' => 'range', 'category' => true, 'department' => true, 'search' => true],
            filters: $filters->toArray(),
            meta: [
                ['label' => 'Jumlah Item', 'value' => count($mapped)],
                ['label' => 'Total Diminta', 'value' => (int) array_sum(array_column($mapped, 'qty_requested'))],
                ['label' => 'Total Diserahkan', 'value' => (int) array_sum(array_column($mapped, 'qty_actual'))],
            ],
            subtitle: sprintf('%s s/d %s', $filters->from, $filters->until),
        );
    }
}
