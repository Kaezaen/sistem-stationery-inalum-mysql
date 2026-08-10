<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Reporting\Queries\NeedToBuyQuery;
use App\Modules\Reporting\Support\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| R8 Need to Buy — item dengan stock_quantity < min_stock.
|
| Usulan beli = max_stock - stock_quantity (mengembalikan stok ke batas maksimum).
| Predikatnya sengaja dicocokkan dengan indeks parsial idx_items_need_to_buy.
*/

uses(RefreshDatabase::class);

function noFilter(): ReportFilters
{
    return new ReportFilters(2026, 7, '2026-07-01', '2026-07-31', null, null, '');
}

it('hanya menampilkan item di bawah minimum dengan usulan beli yang benar', function (): void {
    // min 5, max 10 (default factory). Stok 2 < 5 → butuh beli, usulan 10-2 = 8.
    $low = Item::factory()->withStock(2)->create(['item_code' => 'LOW', 'item_name' => 'STOK RENDAH']);
    // Stok 8 ≥ 5 → tidak butuh beli.
    Item::factory()->withStock(8)->create(['item_code' => 'OK', 'item_name' => 'STOK AMAN']);

    $result = app(NeedToBuyQuery::class)->handle(noFilter());

    expect($result->rows)->toHaveCount(1);
    expect($result->rows[0])->toMatchArray([
        'item_code' => 'LOW',
        'stock' => 2,
        'min_stock' => 5,
        'max_stock' => 10,
        'suggested' => 8,
    ]);
});

it('mengabaikan item non-aktif dan yang sudah dihapus', function (): void {
    Item::factory()->withStock(1)->create();                 // aktif, di bawah min → muncul
    Item::factory()->withStock(1)->inactive()->create();     // non-aktif → tidak muncul
    $deleted = Item::factory()->withStock(1)->create();
    $deleted->delete();                                      // soft delete → tidak muncul

    $result = app(NeedToBuyQuery::class)->handle(noFilter());

    expect($result->rows)->toHaveCount(1);
});

it('mengurutkan kekurangan terbesar lebih dulu', function (): void {
    Item::factory()->withStock(4)->create(['item_name' => 'KURANG SEDIKIT']); // kurang 1
    Item::factory()->withStock(0)->create(['item_name' => 'KURANG BANYAK']);  // kurang 5

    $result = app(NeedToBuyQuery::class)->handle(noFilter());

    expect($result->rows[0]['item_name'])->toBe('KURANG BANYAK');
});
