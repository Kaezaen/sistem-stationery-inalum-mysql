<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\Department;
use App\Modules\Reporting\Queries\RequestByAccountQuery;
use App\Modules\Reporting\Queries\RequestByItemQuery;
use App\Modules\Reporting\Queries\RequestByPeriodQuery;
use App\Modules\Reporting\Support\ReportFilters;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Models\Request;
use App\Modules\Requisition\Models\RequestItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| R4/R5 Request by Month/Year · R6 Request by Account · R7 Request by Item.
|
| Semua berbasis request_date. Yang penting diuji selain agregasi: pembatasan
| lingkup Pimpinan User (◐ "unit sendiri") — laporan menyaring baris menurut
| departemen yang boleh dilihat.
*/

uses(RefreshDatabase::class);

function reqYearFilters(int $year): ReportFilters
{
    return new ReportFilters($year, 1, "$year-01-01", "$year-12-31", null, null, '');
}

function reqRangeFilters(string $from, string $until): ReportFilters
{
    return new ReportFilters(2026, 7, $from, $until, null, null, '');
}

it('R4 mengelompokkan request per bulan dan status', function (): void {
    Request::factory()->create(['request_date' => '2026-07-05', 'status' => RequestStatus::Completed]);
    Request::factory()->create(['request_date' => '2026-07-20', 'status' => RequestStatus::PendingSupervisor]);
    Request::factory()->create(['request_date' => '2026-08-01', 'status' => RequestStatus::Completed]);

    $result = app(RequestByPeriodQuery::class)->byMonth(reqYearFilters(2026));

    $juli = collect($result->rows)->firstWhere('period', 'Juli');
    $agustus = collect($result->rows)->firstWhere('period', 'Agustus');

    expect($result->rows)->toHaveCount(12)   // rapat: 12 bulan
        ->and($juli['total'])->toBe(2)
        ->and($juli[RequestStatus::Completed->value])->toBe(1)
        ->and($juli[RequestStatus::PendingSupervisor->value])->toBe(1)
        ->and($agustus['total'])->toBe(1);
});

it('R4 membatasi lingkup ke departemen yang boleh dilihat Pimpinan User', function (): void {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    Request::factory()->create(['department_id' => $deptA->id, 'request_date' => '2026-07-10']);
    Request::factory()->create(['department_id' => $deptB->id, 'request_date' => '2026-07-11']);

    // Hanya boleh melihat deptA.
    $result = app(RequestByPeriodQuery::class)->byMonth(reqYearFilters(2026), [$deptA->id]);

    $juli = collect($result->rows)->firstWhere('period', 'Juli');

    expect($juli['total'])->toBe(1);
});

it('R6 mengelompokkan per departemen dengan qty diminta vs diserahkan', function (): void {
    $dept = Department::factory()->create(['name' => 'SIT', 'account_code' => 'ACC-SIT']);

    $request = Request::factory()->create([
        'department_id' => $dept->id,
        'request_date' => '2026-07-10',
    ]);
    RequestItem::factory()->create([
        'request_id' => $request->id,
        'quantity_requested' => 10,
        'quantity_approved' => 10,   // wajib terisi sebelum quantity_actual (jebakan §6.4)
        'quantity_actual' => 8,
    ]);

    $result = app(RequestByAccountQuery::class)->handle(reqRangeFilters('2026-07-01', '2026-07-31'));

    $row = collect($result->rows)->firstWhere('department', 'SIT');

    expect($row)->toMatchArray([
        'account_code' => 'ACC-SIT',
        'request_count' => 1,
        'qty_requested' => 10,
        'qty_actual' => 8,
    ]);
});

it('R7 menjumlahkan qty diminta vs diserahkan per item dengan selisih', function (): void {
    $item = Item::factory()->create(['item_code' => 'PEN01', 'item_name' => 'PENA']);

    $r1 = Request::factory()->create(['request_date' => '2026-07-05']);
    $r2 = Request::factory()->create(['request_date' => '2026-07-06']);

    RequestItem::factory()->create(['request_id' => $r1->id, 'item_id' => $item->id, 'quantity_requested' => 10, 'quantity_approved' => 10, 'quantity_actual' => 7]);
    RequestItem::factory()->create(['request_id' => $r2->id, 'item_id' => $item->id, 'quantity_requested' => 5, 'quantity_actual' => null]);

    $result = app(RequestByItemQuery::class)->handle(reqRangeFilters('2026-07-01', '2026-07-31'));

    $row = collect($result->rows)->firstWhere('item_code', 'PEN01');

    expect($row)->toMatchArray([
        'qty_requested' => 15,   // 10 + 5
        'qty_actual' => 7,       // 7 + 0 (NULL dianggap 0)
        'shortfall' => 8,        // 15 - 7
    ]);
});

it('R7 hanya menghitung request dalam rentang tanggal', function (): void {
    $item = Item::factory()->create(['item_code' => 'PEN01']);

    $inRange = Request::factory()->create(['request_date' => '2026-07-15']);
    $outRange = Request::factory()->create(['request_date' => '2026-06-15']);

    RequestItem::factory()->create(['request_id' => $inRange->id, 'item_id' => $item->id, 'quantity_requested' => 3]);
    RequestItem::factory()->create(['request_id' => $outRange->id, 'item_id' => $item->id, 'quantity_requested' => 99]);

    $result = app(RequestByItemQuery::class)->handle(reqRangeFilters('2026-07-01', '2026-07-31'));

    $row = collect($result->rows)->firstWhere('item_code', 'PEN01');

    expect($row['qty_requested'])->toBe(3);
});
