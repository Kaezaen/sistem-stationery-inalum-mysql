<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Services;

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Requisition\Enums\RequestAction;
use App\Modules\Requisition\Enums\RequestItemStatus;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Events\RequestSubmitted;
use App\Modules\Requisition\Models\Request;
use App\Modules\Requisition\Workflows\RequestWorkflow;
use App\Shared\Exceptions\BusinessRuleException;
use App\Shared\Services\DocumentNumberGenerator;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Pembuatan, penyuntingan, dan pengajuan request.
 *
 * Keputusan approval ditangani RequestApprovalService — dipisahkan karena
 * keduanya punya aktor, aturan, dan siklus hidup yang berbeda.
 */
class RequestService
{
    public function __construct(
        private readonly RequestWorkflow $workflow,
        private readonly DocumentNumberGenerator $numbers,
    ) {}

    /**
     * @param  list<array{item_id: int, quantity: int}>  $lines
     */
    public function create(User $requester, array $lines, ?string $notes = null): Request
    {
        $this->guardLines($lines);

        return DB::transaction(function () use ($requester, $lines, $notes): Request {
            $request = Request::create([
                'request_number' => $this->numbers->next('REQUEST', 'REQ'),
                'requester_id' => $requester->id,
                // Di-snapshot: pegawai dapat berpindah seksi, laporan harus tetap
                // menghitung request pada seksi tempat ia diajukan.
                'department_id' => $requester->department_id,
                'request_date' => now()->toDateString(),
                'status' => RequestStatus::Draft,
                'current_approval_level' => 0,
                'notes' => $notes,
            ]);

            $this->syncLines($request, $lines);

            return $request->load('items');
        });
    }

    /**
     * @param  list<array{item_id: int, quantity: int}>  $lines
     */
    public function updateLines(Request $request, array $lines, ?string $notes = null): Request
    {
        $this->guardLines($lines);

        return DB::transaction(function () use ($request, $lines, $notes): Request {
            $request->items()->delete();
            $this->syncLines($request, $lines);

            if ($notes !== null) {
                $request->forceFill(['notes' => $notes])->save();
            }

            return $request->load('items');
        });
    }

    /** Mengajukan request pertama kali — masuk antrian Pimpinan (L1). */
    public function submit(Request $request): Request
    {
        // Alur approval terpadu: L1 (Pimpinan SIT) berbasis role global dan berlaku
        // untuk seluruh seksi, jadi requester TIDAK perlu punya atasan langsung.
        return $this->transition($request, RequestAction::Submit, function (Request $r): void {
            $r->loadMissing('items');

            if ($r->items->isEmpty()) {
                throw new BusinessRuleException('Request harus memuat minimal satu item.');
            }

            $r->forceFill(['submitted_at' => now()])->save();

            RequestSubmitted::dispatch($r);
        });
    }

    public function cancel(Request $request): Request
    {
        return $this->transition($request, RequestAction::Cancel, static function (): void {});
    }

    /**
     * Menjalankan satu transisi secara aman.
     *
     * Baris dokumen dikunci dan statusnya dibaca ulang. Tanpa itu, dua approver
     * yang menekan tombol nyaris bersamaan sama-sama melihat status lama dan
     * request dapat melompati satu level approval.
     */
    public function transition(Request $request, RequestAction $action, Closure $effect): Request
    {
        return DB::transaction(function () use ($request, $action, $effect): Request {
            $locked = Request::query()->lockForUpdate()->findOrFail($request->id);

            $target = $this->workflow->target($locked, $action);

            $effect($locked);

            $locked->forceFill([
                'status' => $target,
                'current_approval_level' => $target->pendingLevel(),
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * @param  list<array{item_id: int, quantity: int}>  $lines
     */
    private function syncLines(Request $request, array $lines): void
    {
        foreach ($lines as $line) {
            $request->items()->create([
                'item_id' => $line['item_id'],
                'quantity_requested' => $line['quantity'],
                'status' => RequestItemStatus::Requested,
            ]);
        }
    }

    /**
     * @param  list<array{item_id: int, quantity: int}>  $lines
     *
     * @throws BusinessRuleException
     */
    private function guardLines(array $lines): void
    {
        if ($lines === []) {
            throw new BusinessRuleException('Request harus memuat minimal satu item.');
        }

        $itemIds = array_column($lines, 'item_id');

        if (count($itemIds) !== count(array_unique($itemIds))) {
            throw new BusinessRuleException(
                'Terdapat item yang sama lebih dari satu baris. Gabungkan jumlahnya menjadi satu baris.',
            );
        }

        foreach ($lines as $line) {
            if ($line['quantity'] <= 0) {
                throw new BusinessRuleException('Jumlah setiap item harus lebih besar dari nol.');
            }
        }

        // Item non-aktif tidak boleh diminta — katalognya sudah ditarik.
        $inactive = Item::query()
            ->whereIn('id', $itemIds)
            ->where('is_active', false)
            ->pluck('item_code')
            ->all();

        if ($inactive !== []) {
            throw new BusinessRuleException(
                'Item berikut sudah tidak aktif dan tidak dapat diminta: '.implode(', ', $inactive),
            );
        }
    }
}
