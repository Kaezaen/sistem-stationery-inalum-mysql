<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Notification\Notifications\LowStockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

/*
| N11 — stok melintasi batas minimum → PIC Stationery + PIC Gudang.
|
| Dipicu StockService saat MELINTASI (bukan tiap kali di bawah min), agar tidak
| membanjiri PIC dengan peringatan berulang.
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolePermissionSeeder::class);

    $this->pic = User::factory()->create();
    $this->pic->assignRole(Role::PicStationery->value);

    $this->gudang = User::factory()->create();
    $this->gudang->assignRole(Role::WarehouseOfficer->value);

    $this->actor = User::factory()->create();
    $this->stock = app(StockService::class);
});

it('memicu N11 ke PIC Stationery dan Gudang saat stok melintasi ke bawah minimum', function (): void {
    $item = Item::factory()->withStock(6)->create(['min_stock' => 5, 'max_stock' => 10]);

    Notification::fake();

    $this->stock->decrease($item, 2, $this->actor); // 6 → 4, melintasi min 5

    Notification::assertSentTo($this->pic, LowStockNotification::class);
    Notification::assertSentTo($this->gudang, LowStockNotification::class);
});

it('tidak memicu ulang bila stok sudah di bawah minimum sejak awal', function (): void {
    $item = Item::factory()->withStock(4)->create(['min_stock' => 5, 'max_stock' => 10]);

    Notification::fake();

    $this->stock->decrease($item, 1, $this->actor); // 4 → 3, tidak melintasi (sudah di bawah)

    Notification::assertNothingSent();
});

it('tidak memicu untuk item dengan min_stock nol', function (): void {
    $item = Item::factory()->withStock(3)->create(['min_stock' => 0, 'max_stock' => 10]);

    Notification::fake();

    $this->stock->decrease($item, 2, $this->actor); // 3 → 1, min 0 → tak pernah di bawah

    Notification::assertNothingSent();
});
