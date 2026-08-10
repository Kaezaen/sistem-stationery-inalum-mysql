<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Purchasing\Enums\PurchaseStatus;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\PurchaseItem;
use App\Modules\Reporting\Queries\PurchasingReportQuery;
use App\Modules\Reporting\Support\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| R3 Purchasing — hanya pembelian berstatus VERIFIED, berbasis purchase_date.
|
| Hanya di titik VERIFIED stok benar-benar bertambah, sehingga pembelian yang
| masih menunggu atau ditolak tidak boleh masuk laporan realisasi pengadaan.
*/

uses(RefreshDatabase::class);

function verifiedPurchase(string $date, Item $item, int $qty): Purchase
{
    $verifier = User::factory()->create();

    $purchase = Purchase::factory()->create([
        'status' => PurchaseStatus::Verified,
        'purchase_date' => $date,
        'verified_by' => $verifier->id,
        'verification_date' => now(),
    ]);

    PurchaseItem::factory()->create([
        'purchase_id' => $purchase->id,
        'item_id' => $item->id,
        'quantity' => $qty,
    ]);

    return $purchase;
}

function rangeFilters(string $from, string $until): ReportFilters
{
    return new ReportFilters(2026, 7, $from, $until, null, null, '');
}

it('hanya menampilkan pembelian VERIFIED dalam rentang tanggal', function (): void {
    $item = Item::factory()->create();

    verifiedPurchase('2026-07-10', $item, 20);

    // Pending dalam rentang — tidak boleh muncul.
    $pending = Purchase::factory()->pending()->create(['purchase_date' => '2026-07-12']);
    PurchaseItem::factory()->create(['purchase_id' => $pending->id, 'item_id' => $item->id, 'quantity' => 99]);

    // Verified tapi di luar rentang — tidak boleh muncul.
    verifiedPurchase('2026-06-30', $item, 77);

    $result = app(PurchasingReportQuery::class)->handle(rangeFilters('2026-07-01', '2026-07-31'));

    expect($result->rows)->toHaveCount(1);
    expect($result->rows[0])->toMatchArray(['quantity' => 20]);
});

it('menjumlahkan total qty pada meta', function (): void {
    $item = Item::factory()->create();
    verifiedPurchase('2026-07-05', $item, 10);
    verifiedPurchase('2026-07-06', $item, 15);

    $result = app(PurchasingReportQuery::class)->handle(rangeFilters('2026-07-01', '2026-07-31'));

    $totalQty = collect($result->meta)->firstWhere('label', 'Total Qty');

    expect($result->rows)->toHaveCount(2)
        ->and($totalQty['value'])->toBe(25);
});
