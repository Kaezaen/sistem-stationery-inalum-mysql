<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Services\StockReservationService;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Reservasi stok — ADR-07.
|
| Menutup celah alokasi ganda: dua request tidak boleh sama-sama disetujui atas
| stok fisik yang sama, lalu salah satunya gagal di gudang setelah terlanjur
| melewati seluruh approval.
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actor = User::factory()->create();
    $this->reservations = app(StockReservationService::class);
    $this->stock = app(StockService::class);
});

it('menahan stok tanpa mengurangi stok fisik', function (): void {
    // Barangnya masih ada di gudang; yang berkurang hanya jumlah yang boleh
    // dijanjikan ke request baru.
    $item = Item::factory()->withStock(10)->create();

    $this->reservations->reserve($item, 4, $this->actor);

    $item->refresh();

    expect($item->stock_quantity)->toBe(10)
        ->and($item->reserved_quantity)->toBe(4)
        ->and($item->availableQuantity())->toBe(6);
});

it('mencegah dua reservasi melebihi stok yang ada', function (): void {
    // Inti ADR-07. Tanpa ini, dua request akan sama-sama disetujui atas 10 unit
    // yang sama dan PIC Gudang hanya bisa memenuhi satu.
    $item = Item::factory()->withStock(10)->create();

    $this->reservations->reserve($item, 8, $this->actor);

    expect(fn () => $this->reservations->reserve($item->refresh(), 5, $this->actor))
        ->toThrow(InsufficientStockException::class);

    expect($item->refresh()->reserved_quantity)->toBe(8);
});

it('melepas reservasi dan memulihkan jumlah tersedia', function (): void {
    $item = Item::factory()->withStock(10)->create();
    $reservation = $this->reservations->reserve($item, 4, $this->actor);

    $this->reservations->release($reservation);

    $item->refresh();

    expect($item->reserved_quantity)->toBe(0)
        ->and($item->availableQuantity())->toBe(10)
        ->and($reservation->refresh()->status)->toBe(ReservationStatus::Released);
});

it('bersifat idempoten saat pelepasan diulang', function (): void {
    // Percobaan ulang tidak boleh mengurangi reserved_quantity dua kali —
    // itu akan membuat saldo reservasi kacau tanpa error yang terlihat.
    $item = Item::factory()->withStock(10)->create();
    $reservation = $this->reservations->reserve($item, 4, $this->actor);

    expect($this->reservations->release($reservation))->toBeTrue()
        ->and($this->reservations->release($reservation))->toBeFalse();

    expect($item->refresh()->reserved_quantity)->toBe(0);
});

it('mengurangi stok fisik dan reservasi sekaligus saat penyerahan', function (): void {
    $item = Item::factory()->withStock(10)->create();
    $reservation = $this->reservations->reserve($item, 4, $this->actor);

    $this->reservations->markConsumed($reservation);
    $this->stock->decrease($item->refresh(), 4, $this->actor, fromReservation: true);

    $item->refresh();

    expect($item->stock_quantity)->toBe(6)
        ->and($item->reserved_quantity)->toBe(0)
        ->and($reservation->refresh()->status)->toBe(ReservationStatus::Consumed)
        ->and(InventoryTransaction::where('item_id', $item->id)->count())->toBe(1);
});

it('melindungi stok yang direservasi dari pengurangan request lain', function (): void {
    // Stok 10, 8 sudah dikunci. Request lain hanya boleh mengambil 2.
    $item = Item::factory()->withStock(10)->create();
    $this->reservations->reserve($item, 8, $this->actor);

    expect(fn () => $this->stock->decrease($item->refresh(), 5, $this->actor))
        ->toThrow(InsufficientStockException::class);

    expect($item->refresh()->stock_quantity)->toBe(10);
});

it('menolak penyesuaian yang membuat stok lebih kecil daripada reservasi', function (): void {
    // Menurunkan stok di bawah jumlah yang sudah dijanjikan akan membuat
    // reserved_quantity > stock_quantity — dilarang constraint database.
    $item = Item::factory()->withStock(10)->create();
    $this->reservations->reserve($item, 8, $this->actor);

    expect(fn () => $this->stock->adjustTo($item->refresh(), 5, $this->actor, 'stock opname'))
        ->toThrow(App\Shared\Exceptions\BusinessRuleException::class);
});

it('melepas reservasi yang melewati tenggat', function (): void {
    // Tanpa pembersih ini, request yang tidak pernah diambil menahan stok
    // selamanya dan membuatnya tidak dapat dipakai siapa pun.
    $item = Item::factory()->withStock(10)->create();
    $reservation = $this->reservations->reserve($item, 4, $this->actor);

    $reservation->update(['expires_at' => now()->subDay()]);

    expect($this->reservations->releaseExpired())->toBe(1)
        ->and($item->refresh()->reserved_quantity)->toBe(0);
});

it('tidak melepas reservasi yang belum kedaluwarsa', function (): void {
    $item = Item::factory()->withStock(10)->create();
    $this->reservations->reserve($item, 4, $this->actor);

    expect($this->reservations->releaseExpired())->toBe(0)
        ->and($item->refresh()->reserved_quantity)->toBe(4);
});

it('menolak reservasi melebihi stok yang tersedia', function (): void {
    $item = Item::factory()->withStock(3)->create();

    expect(fn () => $this->reservations->reserve($item, 5, $this->actor))
        ->toThrow(InsufficientStockException::class);
});
