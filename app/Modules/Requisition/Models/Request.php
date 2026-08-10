<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Models;

use App\Modules\Approval\Contracts\Approvable;
use App\Modules\Approval\Models\Approval;
use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Requisition\Enums\RequestStatus;
use Database\Factories\RequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Dokumen permintaan ATK.
 *
 * CATATAN PENAMAAN: kelas ini bernama Request, sama dengan Illuminate\Http\Request.
 * Nama domain sengaja dipertahankan agar sesuai ERD dan bahasa yang dipakai
 * pengguna. Di Controller, request HTTP diimpor dengan alias HttpRequest.
 *
 * @property int $id
 * @property string $request_number
 * @property int $requester_id
 * @property int $department_id
 * @property RequestStatus $status
 * @property int $current_approval_level
 * @property int $revision_count
 * @property \Illuminate\Support\Carbon|null $request_date
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class Request extends Model implements Approvable
{
    /** @use HasFactory<RequestFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'request_number',
        'requester_id',
        'department_id',
        'request_date',
        'status',
        'current_approval_level',
        'notes',
        'revision_count',
        'submitted_at',
        'completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'request_date' => 'date',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'current_approval_level' => 'integer',
            'revision_count' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relasi
    |--------------------------------------------------------------------------
    */

    /** @return HasMany<RequestItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return MorphMany<Approval, $this> */
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    /*
    |--------------------------------------------------------------------------
    | Kontrak Approvable
    |--------------------------------------------------------------------------
    */

    public function currentApprovalLevel(): int
    {
        return $this->status->pendingLevel();
    }

    /**
     * Bukti kuantitas per baris saat keputusan diambil.
     *
     * Krusial untuk request: approval L2 MENGUBAH kuantitas, sehingga tanpa
     * snapshot tidak ada cara membuktikan berapa yang sebenarnya disetujui
     * Pimpinan SGA bila dokumennya kemudian direvisi.
     *
     * @return array<string, mixed>|null
     */
    public function approvalSnapshot(): ?array
    {
        return [
            'request_number' => $this->request_number,
            'items' => $this->items->map(static fn (RequestItem $line): array => [
                'item_id' => $line->item_id,
                'quantity_requested' => $line->quantity_requested,
                'quantity_approved' => $line->quantity_approved,
            ])->all(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Query scope
    |--------------------------------------------------------------------------
    */

    /** @param Builder<Request> $query */
    public function scopeWithStatus(Builder $query, RequestStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /**
     * Request yang menunggu keputusan pada level tertentu.
     *
     * @param  Builder<Request>  $query
     */
    public function scopeAwaitingLevel(Builder $query, int $level): void
    {
        $status = match ($level) {
            1 => RequestStatus::PendingSupervisor,
            2 => RequestStatus::PendingStationery,
            3 => RequestStatus::PendingSga,
            default => null,
        };

        if ($status === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('status', $status->value);
    }

    /** Model berada di dalam modul, sehingga factory ditunjuk manual. */
    protected static function newFactory(): RequestFactory
    {
        return RequestFactory::new();
    }
}
