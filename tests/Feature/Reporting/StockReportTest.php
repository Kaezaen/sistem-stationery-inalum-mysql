<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Inventory\Models\StockMonthlySnapshot;
use App\Modules\Reporting\Queries\StockByPeriodQuery;
use App\Modules\Reporting\Support\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| R1 Stock by Month & R2 Stock by Year.
|
| Laporan membaca stock_monthly_snapshots — bukan ledger. Di sini snapshot diisi
| langsung lewat factory agar fokusnya pada agregasi laporan, bukan pembangunan
| snapshot (yang sudah diuji terpisah di StockSnapshotTest).
*/

uses(RefreshDatabase::class);

function filters(int $year, int $month): ReportFilters
{
    return new ReportFilters(
        year: $year,
        month: $month,
        from: sprintf('%04d-%02d-01', $year, $month),
        until: sprintf('%04d-%02d-28', $year, $month),
        categoryId: null,
        departmentId: null,
        search: '',
    );
}

it('R1 menampilkan saldo item untuk bulan yang dipilih saja', function (): void {
    $item = Item::factory()->create(['item_code' => 'PEN01', 'item_name' => 'PENA']);

    StockMonthlySnapshot::factory()->create([
        'item_id' => $item->id, 'period_year' => 2026, 'period_month' => 7,
        'opening_balance' => 10, 'total_in' => 5, 'total_out' => 3, 'total_adjustment' => 0, 'closing_balance' => 12,
    ]);
    // Bulan lain tidak boleh ikut muncul.
    StockMonthlySnapshot::factory()->create([
        'item_id' => $item->id, 'period_year' => 2026, 'period_month' => 8,
        'opening_balance' => 12, 'total_in' => 0, 'total_out' => 2, 'total_adjustment' => 0, 'closing_balance' => 10,
    ]);

    $result = app(StockByPeriodQuery::class)->byMonth(filters(2026, 7));

    expect($result->rows)->toHaveCount(1);
    expect($result->rows[0])->toMatchArray([
        'item_code' => 'PEN01',
        'opening' => 10,
        'in' => 5,
        'out' => 3,
        'closing' => 12,
    ]);
});

it('R2 mengagregasi setahun: opening bulan awal, closing bulan akhir, jumlah pergerakan', function (): void {
    $item = Item::factory()->create(['item_code' => 'PEN01', 'item_name' => 'PENA']);

    StockMonthlySnapshot::factory()->create([
        'item_id' => $item->id, 'period_year' => 2026, 'period_month' => 1,
        'opening_balance' => 0, 'total_in' => 100, 'total_out' => 0, 'total_adjustment' => 0, 'closing_balance' => 100,
    ]);
    StockMonthlySnapshot::factory()->create([
        'item_id' => $item->id, 'period_year' => 2026, 'period_month' => 2,
        'opening_balance' => 100, 'total_in' => 0, 'total_out' => 30, 'total_adjustment' => 0, 'closing_balance' => 70,
    ]);
    StockMonthlySnapshot::factory()->create([
        'item_id' => $item->id, 'period_year' => 2026, 'period_month' => 3,
        'opening_balance' => 70, 'total_in' => 0, 'total_out' => 0, 'total_adjustment' => -5, 'closing_balance' => 65,
    ]);

    $result = app(StockByPeriodQuery::class)->byYear(filters(2026, 1));

    expect($result->rows)->toHaveCount(1);
    expect($result->rows[0])->toMatchArray([
        'opening' => 0,      // saldo awal bulan Januari
        'in' => 100,         // jumlah masuk setahun
        'out' => 30,         // jumlah keluar setahun
        'adjustment' => -5,  // penyesuaian bersih setahun
        'closing' => 65,     // saldo akhir bulan Maret
    ]);
});

it('R1 menyaring menurut kategori', function (): void {
    $penA = Item::factory()->create();
    $penB = Item::factory()->create();

    StockMonthlySnapshot::factory()->create([
        'item_id' => $penA->id, 'period_year' => 2026, 'period_month' => 7,
        'opening_balance' => 1, 'closing_balance' => 1,
    ]);
    StockMonthlySnapshot::factory()->create([
        'item_id' => $penB->id, 'period_year' => 2026, 'period_month' => 7,
        'opening_balance' => 2, 'closing_balance' => 2,
    ]);

    $f = new ReportFilters(2026, 7, '2026-07-01', '2026-07-28', $penA->category_id, null, '');
    $result = app(StockByPeriodQuery::class)->byMonth($f);

    expect($result->rows)->toHaveCount(1);
});
