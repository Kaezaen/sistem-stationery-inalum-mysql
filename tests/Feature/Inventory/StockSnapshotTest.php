<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\TransactionType;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\StockMonthlySnapshot;
use App\Modules\Inventory\Services\StockSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Snapshot saldo stok bulanan — fondasi laporan R1/R2.
|
| Snapshot dibangun ulang dari ledger, sehingga yang diuji di sini adalah:
| ketepatan opening/in/out/adjustment/closing per periode, kontinuitas antar
| bulan (opening bulan ini = closing bulan lalu), idempotensi regenerasi, dan
| bahwa backfill tidak menyentuh bulan yang belum selesai.
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actor = User::factory()->create();
    $this->snapshots = app(StockSnapshotService::class);
});

/** Menambahkan satu baris ledger dengan saldo before/after eksplisit. */
function ledgerRow(Item $item, User $actor, TransactionType $type, int $qty, string $date, int $before, int $after): void
{
    InventoryTransaction::factory()->create([
        'item_id' => $item->id,
        'transaction_type' => $type,
        'quantity' => $qty,
        'quantity_before' => $before,
        'quantity_after' => $after,
        'transaction_date' => $date,
        'performed_by' => $actor->id,
        'adjustment_reason' => $type === TransactionType::Adjustment ? 'Uji stock opname' : null,
    ]);
}

it('menghitung opening, masuk, keluar, dan closing satu periode', function (): void {
    $item = Item::factory()->create();

    // Juli: masuk 100, lalu keluar 30.
    ledgerRow($item, $this->actor, TransactionType::In, 100, '2026-07-05 10:00:00', 0, 100);
    ledgerRow($item, $this->actor, TransactionType::Out, 30, '2026-07-20 14:00:00', 100, 70);

    $this->snapshots->generateForPeriod(2026, 7);

    $snapshot = StockMonthlySnapshot::where('item_id', $item->id)
        ->where('period_year', 2026)->where('period_month', 7)->firstOrFail();

    expect($snapshot->opening_balance)->toBe(0)
        ->and($snapshot->total_in)->toBe(100)
        ->and($snapshot->total_out)->toBe(30)
        ->and($snapshot->total_adjustment)->toBe(0)
        ->and($snapshot->closing_balance)->toBe(70);
});

it('menjaga kontinuitas: opening bulan ini = closing bulan lalu', function (): void {
    $item = Item::factory()->create();

    ledgerRow($item, $this->actor, TransactionType::In, 100, '2026-07-05 10:00:00', 0, 100);
    ledgerRow($item, $this->actor, TransactionType::Out, 30, '2026-07-20 14:00:00', 100, 70);
    // Agustus: keluar 20, lalu penyesuaian turun ke 45 (net -5).
    ledgerRow($item, $this->actor, TransactionType::Out, 20, '2026-08-10 09:00:00', 70, 50);
    ledgerRow($item, $this->actor, TransactionType::Adjustment, 5, '2026-08-15 16:00:00', 50, 45);

    $this->snapshots->generateForPeriod(2026, 7);
    $this->snapshots->generateForPeriod(2026, 8);

    $july = StockMonthlySnapshot::where('item_id', $item->id)->where('period_month', 7)->firstOrFail();
    $august = StockMonthlySnapshot::where('item_id', $item->id)->where('period_month', 8)->firstOrFail();

    expect($august->opening_balance)->toBe($july->closing_balance)
        ->and($august->opening_balance)->toBe(70)
        ->and($august->total_out)->toBe(20)
        ->and($august->total_adjustment)->toBe(-5)   // penyesuaian turun tercatat negatif
        ->and($august->closing_balance)->toBe(45);
});

it('selalu memenuhi invariant closing = opening + in - out + adjustment', function (): void {
    $item = Item::factory()->create();

    ledgerRow($item, $this->actor, TransactionType::In, 100, '2026-08-05 10:00:00', 0, 100);
    ledgerRow($item, $this->actor, TransactionType::Out, 40, '2026-08-12 10:00:00', 100, 60);
    ledgerRow($item, $this->actor, TransactionType::Adjustment, 12, '2026-08-20 10:00:00', 60, 72);

    $this->snapshots->generateForPeriod(2026, 8);
    $s = StockMonthlySnapshot::where('item_id', $item->id)->firstOrFail();

    expect($s->closing_balance)->toBe(
        $s->opening_balance + $s->total_in - $s->total_out + $s->total_adjustment,
    );
});

it('menulis baris nol untuk item tanpa pergerakan (snapshot rapat)', function (): void {
    $active = Item::factory()->create();
    ledgerRow($active, $this->actor, TransactionType::In, 10, '2026-08-05 10:00:00', 0, 10);

    // Item ini tidak punya transaksi apa pun pada/menjelang Agustus.
    $dormant = Item::factory()->create();

    $this->snapshots->generateForPeriod(2026, 8);

    $snapshot = StockMonthlySnapshot::where('item_id', $dormant->id)->firstOrFail();

    expect($snapshot->opening_balance)->toBe(0)
        ->and($snapshot->total_in)->toBe(0)
        ->and($snapshot->closing_balance)->toBe(0);
});

it('idempoten: regenerasi periode yang sama tidak menggandakan baris', function (): void {
    $item = Item::factory()->create();
    ledgerRow($item, $this->actor, TransactionType::In, 100, '2026-08-05 10:00:00', 0, 100);

    $this->snapshots->generateForPeriod(2026, 8);

    // Transaksi tambahan muncul, snapshot dibangun ulang.
    ledgerRow($item, $this->actor, TransactionType::Out, 25, '2026-08-18 10:00:00', 100, 75);
    $this->snapshots->generateForPeriod(2026, 8);

    $rows = StockMonthlySnapshot::where('item_id', $item->id)->where('period_month', 8)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->total_out)->toBe(25)
        ->and($rows->first()->closing_balance)->toBe(75);
});

it('backfill mengisi bulan lengkap dan melewati bulan berjalan', function (): void {
    $item = Item::factory()->create();

    // Satu bulan yang pasti sudah lengkap (dua bulan lalu) dan satu di bulan berjalan.
    $pastMonth = now()->subMonthsNoOverflow(2);
    $current = now();

    ledgerRow($item, $this->actor, TransactionType::In, 50, $pastMonth->copy()->day(10)->toDateTimeString(), 0, 50);
    ledgerRow($item, $this->actor, TransactionType::In, 7, $current->copy()->startOfMonth()->addDays(2)->toDateTimeString(), 50, 57);

    $periods = $this->snapshots->backfill();

    $generatedMonths = collect($periods)->map(fn ($p) => sprintf('%04d-%02d', $p['year'], $p['month']))->all();
    $currentKey = sprintf('%04d-%02d', $current->year, $current->month);

    expect($periods)->not->toBeEmpty()
        ->and($generatedMonths)->not->toContain($currentKey);

    // Bulan lengkap ter-snapshot; bulan berjalan tidak (belum selesai).
    $pastSnapshot = StockMonthlySnapshot::where('item_id', $item->id)
        ->where('period_year', $pastMonth->year)->where('period_month', $pastMonth->month)->first();
    $currentSnapshot = StockMonthlySnapshot::where('item_id', $item->id)
        ->where('period_year', $current->year)->where('period_month', $current->month)->first();

    expect($pastSnapshot)->not->toBeNull()
        ->and($pastSnapshot->total_in)->toBe(50)
        ->and($currentSnapshot)->toBeNull();
});

it('command stock:snapshot menulis snapshot periode yang diminta', function (): void {
    $item = Item::factory()->create();
    ledgerRow($item, $this->actor, TransactionType::In, 100, '2026-07-05 10:00:00', 0, 100);

    $this->artisan('stock:snapshot', ['--period' => '2026-07'])->assertSuccessful();

    expect(StockMonthlySnapshot::where('item_id', $item->id)->where('period_month', 7)->exists())->toBeTrue();
});

it('command stock:snapshot menolak format periode yang salah', function (): void {
    $this->artisan('stock:snapshot', ['--period' => '2026/07'])->assertFailed();
});
