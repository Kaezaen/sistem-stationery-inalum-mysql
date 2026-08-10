<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\Item;
use Database\Factories\PurchaseItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_id
 * @property int $item_id
 * @property int $quantity
 * @property string|null $unit_price
 * @property string|null $total_price
 */
class PurchaseItem extends Model
{
    /** @use HasFactory<PurchaseItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['purchase_id', 'item_id', 'quantity', 'unit_price', 'total_price'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            // decimal:2, bukan float — pembulatan biner tidak boleh menyentuh uang.
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Purchase, $this> */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Model berada di dalam modul, sehingga factory ditunjuk manual. */
    protected static function newFactory(): PurchaseItemFactory
    {
        return PurchaseItemFactory::new();
    }
}
