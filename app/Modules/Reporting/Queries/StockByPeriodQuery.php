<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Modules\Reporting\Support\ReportFilters;
use App\Modules\Reporting\Support\ReportResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * R1 Stock by Month & R2 Stock by Year — dibaca dari stock_monthly_snapshots.
 *
 * Laporan stok TIDAK membaca ledger secara langsung: uji arsitektur menegakkan
 * bahwa inventory_transactions hanya boleh disentuh dari modul Inventory. Snapshot
 * bulanan (command stock:snapshot) adalah jembatannya — di situlah biaya agregasi
 * ledger dibayar sekali, lalu laporan tinggal membacanya (ADR-04 + §6 G9).
 *
 * Kolom snapshot yang bisa negatif hanya total_adjustment (penyesuaian bisa
 * menambah maupun mengurangi); saldo dan pergerakan lainnya selalu ≥ 0.
 */
class StockByPeriodQuery
{
    /**
     * @return list<array{key: string, label: string, align?: string, numeric?: bool}>
     */
    private function columns(): array
    {
        return [
            ['key' => 'item_code', 'label' => 'Item Code'],
            ['key' => 'item_name', 'label' => 'Item Name'],
            ['key' => 'category', 'label' => 'Kategori'],
            ['key' => 'opening', 'label' => 'Saldo Awal', 'align' => 'right', 'numeric' => true],
            ['key' => 'in', 'label' => 'Masuk', 'align' => 'right', 'numeric' => true],
            ['key' => 'out', 'label' => 'Keluar', 'align' => 'right', 'numeric' => true],
            ['key' => 'adjustment', 'label' => 'Penyesuaian', 'align' => 'right', 'numeric' => true],
            ['key' => 'closing', 'label' => 'Saldo Akhir', 'align' => 'right', 'numeric' => true],
        ];
    }

    /** R1 — satu bulan tertentu, satu baris per item. */
    public function byMonth(ReportFilters $filters): ReportResult
    {
        $rows = $this->baseItemJoin()
            ->where('sms.period_year', $filters->year)
            ->where('sms.period_month', $filters->month)
            ->when($filters->categoryId, fn (Builder $q): Builder => $q->where('i.category_id', $filters->categoryId))
            ->when($filters->search !== '', fn (Builder $q): Builder => $this->applySearch($q, $filters->search))
            ->orderBy('i.item_name')
            ->get([
                'i.item_code',
                'i.item_name',
                'c.name as category',
                'sms.opening_balance',
                'sms.total_in',
                'sms.total_out',
                'sms.total_adjustment',
                'sms.closing_balance',
            ]);

        $mapped = $rows->map(fn (object $r): array => [
            'item_code' => $r->item_code,
            'item_name' => $r->item_name,
            'category' => $r->category,
            'opening' => (int) $r->opening_balance,
            'in' => (int) $r->total_in,
            'out' => (int) $r->total_out,
            'adjustment' => (int) $r->total_adjustment,
            'closing' => (int) $r->closing_balance,
        ])->all();

        return new ReportResult(
            key: 'stock-by-month',
            title: 'Laporan Stok per Bulan',
            columns: $this->columns(),
            rows: $mapped,
            filterSchema: ['period' => 'month', 'category' => true, 'search' => true],
            filters: $filters->toArray(),
            meta: $this->totals($mapped),
            subtitle: sprintf('Periode %04d-%02d', $filters->year, $filters->month),
        );
    }

    /** R2 — satu tahun, agregasi seluruh bulan dalam tahun itu. */
    public function byYear(ReportFilters $filters): ReportResult
    {
        // opening = saldo awal bulan paling awal; closing = saldo akhir bulan
        // paling akhir dalam tahun itu. array_agg + ORDER BY mengambil keduanya
        // dalam satu query tanpa subquery berkorelasi.
        $rows = $this->baseItemJoin()
            ->where('sms.period_year', $filters->year)
            ->when($filters->categoryId, fn (Builder $q): Builder => $q->where('i.category_id', $filters->categoryId))
            ->when($filters->search !== '', fn (Builder $q): Builder => $this->applySearch($q, $filters->search))
            ->groupBy('i.id', 'i.item_code', 'i.item_name', 'c.name')
            ->orderBy('i.item_name')
            ->get([
                'i.item_code',
                'i.item_name',
                'c.name as category',
                DB::raw('(array_agg(sms.opening_balance ORDER BY sms.period_month ASC))[1] as opening_balance'),
                DB::raw('SUM(sms.total_in) as total_in'),
                DB::raw('SUM(sms.total_out) as total_out'),
                DB::raw('SUM(sms.total_adjustment) as total_adjustment'),
                DB::raw('(array_agg(sms.closing_balance ORDER BY sms.period_month DESC))[1] as closing_balance'),
            ]);

        $mapped = $rows->map(fn (object $r): array => [
            'item_code' => $r->item_code,
            'item_name' => $r->item_name,
            'category' => $r->category,
            'opening' => (int) $r->opening_balance,
            'in' => (int) $r->total_in,
            'out' => (int) $r->total_out,
            'adjustment' => (int) $r->total_adjustment,
            'closing' => (int) $r->closing_balance,
        ])->all();

        return new ReportResult(
            key: 'stock-by-year',
            title: 'Laporan Stok per Tahun',
            columns: $this->columns(),
            rows: $mapped,
            filterSchema: ['period' => 'year', 'category' => true, 'search' => true],
            filters: $filters->toArray(),
            meta: $this->totals($mapped),
            subtitle: sprintf('Tahun %04d', $filters->year),
        );
    }

    private function baseItemJoin(): Builder
    {
        return DB::table('stock_monthly_snapshots as sms')
            ->join('items as i', 'i.id', '=', 'sms.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id');
    }

    private function applySearch(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(function (Builder $sub) use ($like): void {
            $sub->where('i.item_name', 'ilike', $like)
                ->orWhere('i.item_code', 'ilike', $like);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{label: string, value: int}>
     */
    private function totals(array $rows): array
    {
        return [
            ['label' => 'Jumlah Item', 'value' => count($rows)],
            ['label' => 'Total Masuk', 'value' => (int) array_sum(array_column($rows, 'in'))],
            ['label' => 'Total Keluar', 'value' => (int) array_sum(array_column($rows, 'out'))],
        ];
    }
}
