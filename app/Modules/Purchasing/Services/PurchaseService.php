<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Services;

use App\Modules\Approval\Enums\ApprovalDecision;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchasing\Enums\PurchaseAction;
use App\Modules\Purchasing\Enums\PurchaseStatus;
use App\Modules\Purchasing\Events\PurchaseRejected;
use App\Modules\Purchasing\Events\PurchaseSubmitted;
use App\Modules\Purchasing\Events\PurchaseVerified;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Workflows\PurchaseWorkflow;
use App\Shared\Exceptions\BusinessRuleException;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi dokumen pembelian.
 *
 * Titik terpenting ada di verify(): di situlah — dan HANYA di situ — stok
 * bertambah. Seluruh langkahnya berjalan dalam satu DB transaction, sehingga
 * dokumen tidak akan pernah berstatus VERIFIED tanpa ledger yang menyertainya,
 * maupun sebaliknya.
 */
class PurchaseService
{
    public function __construct(
        private readonly PurchaseWorkflow $workflow,
        private readonly StockService $stock,
        private readonly ApprovalService $approvals,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{item_id: int, quantity: int}>  $lines
     */
    public function create(array $data, array $lines, User $creator): Purchase
    {
        $this->guardLines($lines);

        return DB::transaction(function () use ($data, $lines, $creator): Purchase {
            $purchase = Purchase::create([
                'purchase_number' => $data['purchase_number'],
                'purchase_date' => $data['purchase_date'],
                'supplier_name' => $data['supplier_name'],
                'created_by' => $creator->id,
                'status' => PurchaseStatus::Draft,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncLines($purchase, $lines);

            return $purchase->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{item_id: int, quantity: int}>  $lines
     */
    public function update(Purchase $purchase, array $data, array $lines): Purchase
    {
        if (! $purchase->status->isEditable()) {
            throw new BusinessRuleException(sprintf(
                'Pembelian berstatus %s tidak dapat disunting.',
                $purchase->status->label(),
            ));
        }

        $this->guardLines($lines);

        return DB::transaction(function () use ($purchase, $data, $lines): Purchase {
            $purchase->update([
                'purchase_number' => $data['purchase_number'],
                'purchase_date' => $data['purchase_date'],
                'supplier_name' => $data['supplier_name'],
                'notes' => $data['notes'] ?? null,
            ]);

            $purchase->items()->delete();
            $this->syncLines($purchase, $lines);

            return $purchase->load('items');
        });
    }

    /** Mengajukan dokumen untuk diverifikasi PIC Stationery. */
    public function submit(Purchase $purchase): Purchase
    {
        return $this->transition($purchase, PurchaseAction::Submit, function (Purchase $p): void {
            PurchaseSubmitted::dispatch($p);
        });
    }

    /**
     * Mengajukan ulang setelah perbaikan.
     *
     * Keputusan penolakan sebelumnya ditandai dianulir — tidak dihapus, agar
     * riwayat bahwa dokumen ini pernah ditolak tetap terlihat saat audit.
     */
    public function revise(Purchase $purchase): Purchase
    {
        return $this->transition($purchase, PurchaseAction::Revise, function (Purchase $p): void {
            $this->approvals->supersedeAll($p);

            $p->forceFill([
                'revision_count' => $p->revision_count + 1,
                'rejection_notes' => null,
            ])->save();

            PurchaseSubmitted::dispatch($p);
        });
    }

    /**
     * Memverifikasi pembelian — SATU-SATUNYA titik stok bertambah.
     *
     * Stok sengaja tidak dinaikkan saat input: bila dinaikkan lebih awal,
     * penolakan verifikasi akan memaksa koreksi negatif dan merusak integritas
     * ledger (§7 Architecture Blueprint).
     */
    public function verify(Purchase $purchase, User $verifier): Purchase
    {
        return $this->transition($purchase, PurchaseAction::Verify, function (Purchase $p) use ($verifier): void {
            $p->loadMissing('items');

            if ($p->items->isEmpty()) {
                throw new BusinessRuleException('Pembelian tanpa item tidak dapat diverifikasi.');
            }

            $p->forceFill([
                'verified_by' => $verifier->id,
                'verification_date' => now(),
            ])->save();

            foreach ($p->items as $line) {
                $item = Item::query()->findOrFail($line->item_id);

                $this->stock->increase($item, $line->quantity, $verifier, $p);
            }

            $this->approvals->record($p, $verifier, ApprovalDecision::Approved, 1);

            PurchaseVerified::dispatch($p);
        });
    }

    public function reject(Purchase $purchase, User $verifier, string $reason): Purchase
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('Penolakan wajib disertai alasan.');
        }

        return $this->transition($purchase, PurchaseAction::Reject, function (Purchase $p) use ($verifier, $reason): void {
            $p->forceFill([
                'verified_by' => $verifier->id,
                'verification_date' => now(),
                'rejection_notes' => $reason,
            ])->save();

            $this->approvals->record($p, $verifier, ApprovalDecision::Rejected, 1, $reason);

            PurchaseRejected::dispatch($p, $reason);
        });
    }

    /**
     * Menjalankan satu transisi secara aman.
     *
     * Baris dokumen dikunci lebih dulu dan statusnya dibaca ulang dari database.
     * Tanpa itu, dua verifikator yang menekan tombol nyaris bersamaan sama-sama
     * melihat status PENDING dan stok akan naik dua kali.
     */
    private function transition(Purchase $purchase, PurchaseAction $action, Closure $effect): Purchase
    {
        return DB::transaction(function () use ($purchase, $action, $effect): Purchase {
            $locked = Purchase::query()->lockForUpdate()->findOrFail($purchase->id);

            $target = $this->workflow->target($locked, $action);

            $effect($locked);

            $locked->forceFill(['status' => $target])->save();

            return $locked->refresh();
        });
    }

    /**
     * @param  list<array{item_id: int, quantity: int}>  $lines
     */
    private function syncLines(Purchase $purchase, array $lines): void
    {
        foreach ($lines as $line) {
            $purchase->items()->create([
                'item_id' => $line['item_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'] ?? null,
                'total_price' => isset($line['unit_price'])
                    ? (string) ((float) $line['unit_price'] * $line['quantity'])
                    : null,
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
            throw new BusinessRuleException('Pembelian harus memuat minimal satu item.');
        }

        $itemIds = array_column($lines, 'item_id');

        // Item ganda akan ditolak unique constraint di tengah transaksi dengan
        // pesan teknis; ditangkap lebih awal agar pesannya dapat dimengerti.
        if (count($itemIds) !== count(array_unique($itemIds))) {
            throw new BusinessRuleException(
                'Terdapat item yang sama lebih dari satu baris. Gabungkan jumlahnya menjadi satu baris.',
            );
        }

        foreach ($lines as $line) {
            if ($line['quantity'] <= 0) {
                throw new BusinessRuleException('Jumlah pembelian setiap item harus lebih besar dari nol.');
            }
        }
    }
}
