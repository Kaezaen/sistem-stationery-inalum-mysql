<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Modules\Reporting\Support\ReportFilters;
use App\Modules\Reporting\Support\ReportResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * R8 Need to Buy — item yang stoknya di bawah batas minimum.
 *
 * Usulan jumlah beli = max_stock - stock_quantity, sehingga pembelian
 * mengembalikan stok ke batas maksimum (blueprint fitur 7 R8, sejalan dengan
 * Item::suggestedPurchaseQuantity()).
 *
 * Predikat WHERE-nya sengaja dicocokkan dengan indeks parsial idx_items_need_to_buy
 * (deleted_at IS NULL AND is_active AND stock_quantity < min_stock) agar
 * PostgreSQL memakai indeks itu alih-alih memindai seluruh tabel item.
 */
class NeedToBuyQuery
{
    public function handle(ReportFilters $filters): ReportResult
    {
        $rows = DB::table('items as i')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'i.uom_id')
            ->whereNull('i.deleted_at')
            ->where('i.is_active', true)
            ->whereColumn('i.stock_quantity', '<', 'i.min_stock')
            ->when($filters->categoryId, fn (Builder $q): Builder => $q->where('i.category_id', $filters->categoryId))
            ->when($filters->search !== '', function (Builder $q) use ($filters): Builder {
                $like = '%'.$filters->search.'%';

                return $q->where(function (Builder $sub) use ($like): void {
                    $sub->where('i.item_name', 'ilike', $like)
                        ->orWhere('i.item_code', 'ilike', $like);
                });
            })
            // Paling mendesak (kekurangan terbesar) di atas.
            ->orderByRaw('(i.min_stock - i.stock_quantity) DESC')
            ->orderBy('i.item_name')
            ->get([
                'i.item_code',
                'i.item_name',
                'c.name as category',
                'u.code as uom',
                'i.stock_quantity',
                'i.min_stock',
                'i.max_stock',
                DB::raw('(i.max_stock - i.stock_quantity) as suggested'),
            ]);

        $mapped = $rows->map(fn (object $r): array => [
            'item_code' => $r->item_code,
            'item_name' => $r->item_name,
            'category' => $r->category,
            'uom' => $r->uom,
            'stock' => (int) $r->stock_quantity,
            'min_stock' => (int) $r->min_stock,
            'max_stock' => (int) $r->max_stock,
            'suggested' => (int) $r->suggested,
        ])->all();

        return new ReportResult(
            key: 'need-to-buy',
            title: 'Laporan Need to Buy',
            columns: [
                ['key' => 'item_code', 'label' => 'Item Code'],
                ['key' => 'item_name', 'label' => 'Item Name'],
                ['key' => 'category', 'label' => 'Kategori'],
                ['key' => 'uom', 'label' => 'UoM'],
                ['key' => 'stock', 'label' => 'Stok', 'align' => 'right', 'numeric' => true],
                ['key' => 'min_stock', 'label' => 'Min Stock', 'align' => 'right', 'numeric' => true],
                ['key' => 'max_stock', 'label' => 'Max Stock', 'align' => 'right', 'numeric' => true],
                ['key' => 'suggested', 'label' => 'Usulan Beli', 'align' => 'right', 'numeric' => true],
            ],
            rows: $mapped,
            filterSchema: ['period' => null, 'category' => true, 'search' => true],
            filters: $filters->toArray(),
            meta: [
                ['label' => 'Item di Bawah Minimum', 'value' => count($mapped)],
                ['label' => 'Total Usulan Beli', 'value' => (int) array_sum(array_column($mapped, 'suggested'))],
            ],
            subtitle: 'Stok di bawah minimum · usulan beli = maks − stok',
        );
    }
}
