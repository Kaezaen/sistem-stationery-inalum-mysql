<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Models\Approval;
use App\Modules\Identity\Models\User;
use App\Modules\Purchasing\Enums\PurchaseStatus;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property string $purchase_number
 * @property string $supplier_name
 * @property PurchaseStatus $status
 * @property int $created_by
 * @property int|null $verified_by
 * @property int $revision_count
 * @property \Illuminate\Support\Carbon|null $purchase_date
 * @property \Illuminate\Support\Carbon|null $verification_date
 */
class Purchase extends Model implements Approvable
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'purchase_number',
        'purchase_date',
        'supplier_name',
        'created_by',
        'verified_by',
        'verification_date',
        'status',
        'notes',
        'rejection_notes',
        'revision_count',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'purchase_date' => 'date',
            'verification_date' => 'datetime',
            'revision_count' => 'integer',
        ];
    }

    /** @return HasMany<PurchaseItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return MorphMany<Approval, $this> */
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    /** Verifikasi pembelian hanya satu level. */
    public function currentApprovalLevel(): int
    {
        return 1;
    }

    /**
     * Bukti isi dokumen saat keputusan diambil.
     *
     * Disimpan agar auditor tetap dapat melihat apa yang sebenarnya diverifikasi,
     * meski dokumennya kemudian direvisi dan isinya berubah.
     *
     * @return array<string, mixed>|null
     */
    public function approvalSnapshot(): ?array
    {
        return [
            'purchase_number' => $this->purchase_number,
            'supplier_name' => $this->supplier_name,
            'items' => $this->items->map(static fn (PurchaseItem $line): array => [
                'item_id' => $line->item_id,
                'quantity' => $line->quantity,
            ])->all(),
        ];
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /** @param Builder<Purchase> $query */
    public function scopeWithStatus(Builder $query, PurchaseStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /** Model berada di dalam modul, sehingga factory ditunjuk manual. */
    protected static function newFactory(): PurchaseFactory
    {
        return PurchaseFactory::new();
    }
}
