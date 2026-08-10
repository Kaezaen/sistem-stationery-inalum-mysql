<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Modules\Purchasing\Enums\PurchaseStatus;
use App\Modules\Reporting\Support\ReportFilters;
use App\Modules\Reporting\Support\ReportResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * R3 Purchasing — pembelian yang sudah DIVERIFIKASI dalam satu periode.
 *
 * Hanya status VERIFIED yang dihitung: hanya di titik itu stok benar-benar
 * bertambah (§7 & §8 Architecture Blueprint). Pembelian yang masih menunggu atau
 * ditolak belum menjadi realisasi pengadaan, sehingga tidak boleh muncul di
 * laporan realisasi.
 *
 * Berbasis purchase_date (tanggal transaksi), bukan created_at (§10.1). Kolom
 * unit_price/total_price sengaja tidak ditampilkan — disembunyikan pada UI Fase 1
 * (keputusan D4).
 */
class PurchasingReportQuery
{
    public function handle(ReportFilters $filters): ReportResult
    {
        $rows = DB::table('purchase_items as pi')
            ->join('purchases as p', 'p.id', '=', 'pi.purchase_id')
            ->join('items as i', 'i.id', '=', 'pi.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->where('p.status', PurchaseStatus::Verified->value)
            ->whereBetween('p.purchase_date', [$filters->from, $filters->until])
            ->when($filters->categoryId, fn (Builder $q): Builder => $q->where('i.category_id', $filters->categoryId))
            ->when($filters->search !== '', function (Builder $q) use ($filters): Builder {
                $like = '%'.$filters->search.'%';

                return $q->where(function (Builder $sub) use ($like): void {
                    $sub->where('i.item_name', 'ilike', $like)
                        ->orWhere('i.item_code', 'ilike', $like)
                        ->orWhere('p.supplier_name', 'ilike', $like)
                        ->orWhere('p.purchase_number', 'ilike', $like);
                });
            })
            ->orderByDesc('p.purchase_date')
            ->orderBy('p.purchase_number')
            ->orderBy('i.item_name')
            ->get([
                'p.purchase_date',
                'p.purchase_number',
                'p.supplier_name',
                'i.item_code',
                'i.item_name',
                'c.name as category',
                'pi.quantity',
            ]);

        $mapped = $rows->map(fn (object $r): array => [
            'purchase_date' => $r->purchase_date,
            'purchase_number' => $r->purchase_number,
            'supplier_name' => $r->supplier_name,
            'item_code' => $r->item_code,
            'item_name' => $r->item_name,
            'category' => $r->category,
            'quantity' => (int) $r->quantity,
        ])->all();

        return new ReportResult(
            key: 'purchasing',
            title: 'Laporan Pembelian',
            columns: [
                ['key' => 'purchase_date', 'label' => 'Tanggal', 'format' => 'date'],
                ['key' => 'purchase_number', 'label' => 'No. Pembelian'],
                ['key' => 'supplier_name', 'label' => 'Supplier'],
                ['key' => 'item_code', 'label' => 'Item Code'],
                ['key' => 'item_name', 'label' => 'Item Name'],
                ['key' => 'category', 'label' => 'Kategori'],
                ['key' => 'quantity', 'label' => 'Qty', 'align' => 'right', 'numeric' => true],
            ],
            rows: $mapped,
            filterSchema: ['period' => 'range', 'category' => true, 'search' => true],
            filters: $filters->toArray(),
            meta: [
                ['label' => 'Jumlah Pembelian', 'value' => count(array_unique(array_column($mapped, 'purchase_number')))],
                ['label' => 'Jumlah Baris', 'value' => count($mapped)],
                ['label' => 'Total Qty', 'value' => (int) array_sum(array_column($mapped, 'quantity'))],
            ],
            subtitle: sprintf('%s s/d %s · hanya Diverifikasi', $filters->from, $filters->until),
        );
    }
}
