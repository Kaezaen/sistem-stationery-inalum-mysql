<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Item;
use App\Modules\Inventory\Enums\ReservationStatus;
use Database\Factories\StockReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $item_id
 * @property int|null $request_item_id
 * @property int $quantity
 * @property ReservationStatus $status
 */
class StockReservation extends Model
{
    /** @use HasFactory<StockReservationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'item_id',
        'request_item_id',
        'quantity',
        'status',
        'expires_at',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'quantity' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @param Builder<StockReservation> $query */
    public function scopeHeld(Builder $query): void
    {
        $query->where('status', ReservationStatus::Held->value);
    }

    /** @param Builder<StockReservation> $query */
    public function scopeExpired(Builder $query): void
    {
        $query->held()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /** Model berada di dalam modul, sehingga factory ditunjuk manual. */
    protected static function newFactory(): StockReservationFactory
    {
        return StockReservationFactory::new();
    }
}
