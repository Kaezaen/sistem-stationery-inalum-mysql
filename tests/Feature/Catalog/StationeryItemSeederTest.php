<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use Database\Seeders\CategorySeeder;
use Database\Seeders\StationeryItemSeeder;
use Database\Seeders\UomSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Seed katalog Inalum (236 item dari Daftar Barang Stationaries).
|
| Menjaga agar CSV katalog tetap ter-resolve penuh (semua kategori/UoM dikenal),
| tidak mengisi stok, dan aman dijalankan ulang (idempoten).
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(CategorySeeder::class);
    $this->seed(UomSeeder::class);
});

it('menyemai seluruh 236 item dengan kategori & satuan yang ter-resolve', function (): void {
    $this->seed(StationeryItemSeeder::class);

    expect(Item::count())->toBe(236)
        // Setiap item wajib punya kategori & satuan yang valid (tidak ada yang null).
        ->and(Item::whereNull('category_id')->orWhereNull('uom_id')->count())->toBe(0)
        // Katalog tidak mengisi stok — saldo awal lewat ADJUSTMENT saat go-live.
        ->and(Item::where('stock_quantity', '!=', 0)->count())->toBe(0);

    $sample = Item::where('item_code', '1709000002')->firstOrFail();
    expect($sample->item_name)->toBe('BALL LINER, KENKO-SIZE 0,5-BLUE')
        ->and($sample->min_stock)->toBe(160)
        ->and($sample->max_stock)->toBe(2000); // formula "=160*..." dievaluasi
});

it('idempoten — menjalankan ulang tidak menggandakan item', function (): void {
    $this->seed(StationeryItemSeeder::class);
    $this->seed(StationeryItemSeeder::class);

    expect(Item::count())->toBe(236);
});
