<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak audit teknis (§8.2) — append-only, hanya dibaca Administrator.
 *
 * Sengaja tanpa trait factory: baris dibuat lewat AuditLogger (atau observer),
 * bukan di-seed. Hanya created_at (UPDATED_AT dimatikan) — baris tak pernah diubah.
 *
 * @property int $id
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property int|null $user_id
 * @property string $event
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class AuditLog extends Model
{
    /** Ledger audit tidak punya updated_at — baris tidak pernah berubah. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'user_id',
        'event',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
