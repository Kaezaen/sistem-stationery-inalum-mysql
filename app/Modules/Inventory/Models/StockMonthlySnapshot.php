<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Item;
use Database\Factories\StockMonthlySnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saldo stok satu item pada satu periode bulan.
 *
 * Tabel turunan: setiap baris dapat dibangun ulang dari ledger. Diisi oleh
 * StockSnapshotService (di dalam modul Inventory, satu-satunya yang boleh membaca
 * ledger) dan hanya DIBACA oleh modul Reporting.
 *
 * @property int $id
 * @property int $item_id
 * @property int $period_year
 * @property int $period_month
 * @property int $opening_balance
 * @property int $total_in
 * @property int $total_out
 * @property int $total_adjustment
 * @property int $closing_balance
 * @property \Illuminate\Support\Carbon|null $generated_at
 */
class StockMonthlySnapshot extends Model
{
    /** @use HasFactory<StockMonthlySnapshotFactory> */
    use HasFactory;

    /** Tabel turunan hanya menyimpan generated_at, bukan created_at/updated_at. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'item_id',
        'period_year',
        'period_month',
        'opening_balance',
        'total_in',
        'total_out',
        'total_adjustment',
        'closing_balance',
        'generated_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'period_month' => 'integer',
            'opening_balance' => 'integer',
            'total_in' => 'integer',
            'total_out' => 'integer',
            'total_adjustment' => 'integer',
            'closing_balance' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Model berada di dalam modul, sehingga factory ditunjuk manual (jebakan §6.2). */
    protected static function newFactory(): StockMonthlySnapshotFactory
    {
        return StockMonthlySnapshotFactory::new();
    }
}
