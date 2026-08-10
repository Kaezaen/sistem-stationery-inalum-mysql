<?php

declare(strict_types=1);

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Requisition\Models\RequestItem;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
| Jejak audit teknis (§8.2).
|
| Yang dikunci: perubahan master item terekam; mutasi stok TIDAK diaudit ganda di
| sini (sudah di ledger); login gagal tidak pernah menyimpan password.
*/

uses(RefreshDatabase::class);

it('mencatat pembuatan dan perubahan master item beserta pelakunya', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $item = Item::factory()->create(['min_stock' => 5, 'max_stock' => 10]);
    $item->update(['min_stock' => 8]);

    $created = AuditLog::where('event', 'created')->where('auditable_id', $item->id)->first();
    $updated = AuditLog::where('event', 'updated')->where('auditable_id', $item->id)->latest('id')->first();

    expect($created)->not->toBeNull()
        ->and($updated)->not->toBeNull()
        ->and($updated->old_values['min_stock'])->toBe(5)
        ->and($updated->new_values['min_stock'])->toBe(8)
        ->and($updated->user_id)->toBe($user->id);
});

it('tidak mengaudit ganda mutasi stok (sudah tercatat di ledger)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $item = Item::factory()->create();
    app(StockService::class)->increase($item, 10, $user);

    // Perubahan stock_quantity dikecualikan observer → tidak ada audit 'updated'.
    $updates = AuditLog::where('event', 'updated')->where('auditable_id', $item->id)->count();

    expect($updates)->toBe(0);
});

it('mencatat login berhasil dan gagal, tanpa pernah menyimpan password', function (): void {
    $user = User::factory()->create();

    event(new Login('web', $user, false));
    event(new Failed('web', null, ['username' => 'penyusup', 'password' => 'rahasia123']));

    $login = AuditLog::where('event', 'login')->first();
    $failed = AuditLog::where('event', 'login_failed')->first();

    expect($login)->not->toBeNull()
        ->and($login->user_id)->toBe($user->id)
        ->and($failed)->not->toBeNull()
        ->and($failed->new_values['credentials'])->toHaveKey('username')
        ->and($failed->new_values['credentials'])->not->toHaveKey('password');
});

it('mencatat perubahan quantity_actual request item', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $item = RequestItem::factory()->create([
        'quantity_requested' => 10,
        'quantity_approved' => 10,
    ]);

    $item->update(['quantity_actual' => 7]);

    $log = AuditLog::where('event', 'quantity_actual_changed')->where('auditable_id', $item->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values['quantity_actual'])->toBe(7);
});
