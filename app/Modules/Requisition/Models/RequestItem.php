<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Models;

use App\Modules\Catalog\Models\Item;
use App\Modules\Requisition\Enums\RequestItemStatus;
use Database\Factories\RequestItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $request_id
 * @property int $item_id
 * @property int $quantity_requested
 * @property int|null $quantity_approved
 * @property int|null $quantity_actual
 * @property string|null $remark
 * @property RequestItemStatus $status
 */
class RequestItem extends Model
{
    /** @use HasFactory<RequestItemFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'request_id',
        'item_id',
        'quantity_requested',
        'quantity_approved',
        'quantity_actual',
        'remark',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RequestItemStatus::class,
            'quantity_requested' => 'integer',
            'quantity_approved' => 'integer',
            'quantity_actual' => 'integer',
        ];
    }

    /** @return BelongsTo<Request, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Jumlah yang berlaku saat ini — disetujui bila sudah ada, selain itu diminta. */
    public function effectiveQuantity(): int
    {
        return $this->quantity_approved ?? $this->quantity_requested;
    }

    /** Model berada di dalam modul, sehingga factory ditunjuk manual. */
    protected static function newFactory(): RequestItemFactory
    {
        return RequestItemFactory::new();
    }
}
