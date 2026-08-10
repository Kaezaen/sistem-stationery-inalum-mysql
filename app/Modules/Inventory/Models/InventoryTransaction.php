<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\TransactionType;
use Database\Factories\InventoryTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris ledger stok.
 *
 * APPEND ONLY — jangan pernah menambahkan method yang meng-update atau menghapus
 * baris di sini. Koreksi dilakukan dengan membuat transaksi ADJUSTMENT baru.
 *
 * @property int $id
 * @property int $item_id
 * @property TransactionType $transaction_type
 * @property int $quantity
 * @property int $quantity_before
 * @property int $quantity_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property \Illuminate\Support\Carbon|null $transaction_date
 * @property int $performed_by
 * @property string|null $adjustment_reason
 */
class InventoryTransaction extends Model
{
    /** @use HasFactory<InventoryTransactionFactory> */
    use HasFactory;

    /** Ledger tidak punya updated_at — baris tidak pernah berubah setelah dibuat. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'item_id',
        'transaction_type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reference_type',
        'reference_id',
        'transaction_date',
        'performed_by',
        'adjustment_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'quantity' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'transaction_date' => 'datetime',
        ];
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** @return BelongsTo<User, $this> */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /** Perubahan bersih terhadap saldo — turunan, bukan kolom tersimpan. */
    public function netChange(): int
    {
        return $this->quantity_after - $this->quantity_before;
    }

    /** @param Builder<InventoryTransaction> $query */
    public function scopeOfType(Builder $query, TransactionType $type): void
    {
        $query->where('transaction_type', $type->value);
    }

    /** @param Builder<InventoryTransaction> $query */
    public function scopeBetween(Builder $query, string $from, string $until): void
    {
        $query->whereBetween('transaction_date', [$from, $until]);
    }

    /** Model berada di dalam modul, sehingga factory ditunjuk manual. */
    protected static function newFactory(): InventoryTransactionFactory
    {
        return InventoryTransactionFactory::new();
    }
}
