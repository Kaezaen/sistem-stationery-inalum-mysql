<?php

declare(strict_types=1);

namespace App\Modules\Approval\Models;

use App\Modules\Approval\Enums\ApprovalDecision;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Satu keputusan approval.
 *
 * IMMUTABLE setelah dibuat. Revisi dokumen menandai keputusan lama
 * is_superseded, bukan menimpanya — riwayat lengkap harus tetap dapat ditelusuri.
 *
 * @property int $id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property int $approver_id
 * @property int $approval_level
 * @property string $approver_role
 * @property ApprovalDecision $status
 * @property string|null $rejection_notes
 * @property array<string, mixed>|null $snapshot
 * @property bool $is_superseded
 * @property \Illuminate\Support\Carbon|null $approval_date
 */
class Approval extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'approver_id',
        'approval_level',
        'approver_role',
        'status',
        'approval_date',
        'rejection_notes',
        'snapshot',
        'is_superseded',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ApprovalDecision::class,
            'approval_date' => 'datetime',
            'snapshot' => 'array',
            'is_superseded' => 'boolean',
            'approval_level' => 'integer',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** Keputusan yang masih berlaku — belum dianulir oleh revisi. */
    /** @param Builder<Approval> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_superseded', false);
    }
}
