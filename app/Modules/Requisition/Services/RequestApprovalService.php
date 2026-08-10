<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Services;

use App\Modules\Approval\Enums\ApprovalDecision;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Services\StockReservationService;
use App\Modules\Requisition\Enums\RequestAction;
use App\Modules\Requisition\Enums\RequestItemStatus;
use App\Modules\Requisition\Events\RequestApproved;
use App\Modules\Requisition\Events\RequestRejected;
use App\Modules\Requisition\Events\RequestSubmitted;
use App\Modules\Requisition\Models\Request;
use App\Modules\Requisition\Models\RequestItem;
use App\Shared\Exceptions\BusinessRuleException;

/**
 * Keputusan approval tiga level.
 *
 * Level 2 berbeda sendiri: ia MENGUBAH DATA (kuantitas per baris) dan MENGUNCI
 * STOK, bukan sekadar memindahkan status. Itulah sebabnya ia punya method
 * tersendiri dengan parameter kuantitas, bukan approve() yang seragam.
 */
class RequestApprovalService
{
    public function __construct(
        private readonly RequestService $requests,
        private readonly ApprovalService $approvals,
        private readonly StockReservationService $reservations,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Level 1 — Pimpinan User
    |--------------------------------------------------------------------------
    */

    public function approveBySupervisor(Request $request, User $approver): Request
    {
        return $this->requests->transition($request, RequestAction::ApproveL1, function (Request $r) use ($approver): void {
            $this->approvals->record($r, $approver, ApprovalDecision::Approved, 1);
            RequestApproved::dispatch($r, 1);
        });
    }

    public function rejectBySupervisor(Request $request, User $approver, string $reason): Request
    {
        $this->guardReason($reason);

        return $this->requests->transition($request, RequestAction::RejectL1, function (Request $r) use ($approver, $reason): void {
            $this->approvals->record($r, $approver, ApprovalDecision::Rejected, 1, $reason);
            RequestRejected::dispatch($r, 1, $reason);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Level 2 — PIC Stationery (KUANTITATIF)
    |--------------------------------------------------------------------------
    */

    /**
     * Menetapkan kuantitas yang disetujui per baris, lalu mengunci stoknya.
     *
     * Wireframe 3.3.2 menampilkan kolom QUANTITY ACTUAL yang dapat di-adjust dan
     * REMARK per baris — approval di level ini bukan stempel, melainkan
     * penetapan berapa yang benar-benar akan diberikan.
     *
     * Reservasi dibuat di sini (ADR-07): sejak kuantitas ditetapkan, sistem
     * menjanjikan barang kepada requester meski penyerahannya masih menunggu SGA.
     *
     * @param  array<int, array{quantity: int, remark?: string|null}>  $decisions  request_item_id => keputusan
     *
     * @throws BusinessRuleException
     */
    public function approveByStationery(Request $request, User $approver, array $decisions): Request
    {
        return $this->requests->transition($request, RequestAction::ApproveL2, function (Request $r) use ($approver, $decisions): void {
            $r->loadMissing('items');

            $anyApproved = false;

            foreach ($r->items as $line) {
                if (! array_key_exists($line->id, $decisions)) {
                    throw new BusinessRuleException(
                        'Setiap baris item harus diberi keputusan kuantitas.',
                    );
                }

                $approved = (int) $decisions[$line->id]['quantity'];

                if ($approved < 0 || $approved > $line->quantity_requested) {
                    throw new BusinessRuleException(sprintf(
                        'Kuantitas disetujui untuk item %s harus antara 0 dan %d.',
                        $line->item->item_code ?? $line->item_id,
                        $line->quantity_requested,
                    ));
                }

                $line->forceFill([
                    'quantity_approved' => $approved,
                    'remark' => $decisions[$line->id]['remark'] ?? null,
                    'status' => RequestItemStatus::fromQuantities($line->quantity_requested, $approved),
                ])->save();

                if ($approved > 0) {
                    $anyApproved = true;
                    $this->reserveFor($line, $approver);
                }
            }

            // Menyetujui nol untuk SELURUH baris sama saja dengan menolak, namun
            // tanpa alasan yang tercatat. Dipaksa lewat tombol "Ditolak
            // Seluruhnya" agar penolakan selalu punya alasan.
            if (! $anyApproved) {
                throw new BusinessRuleException(
                    'Tidak ada satu pun item yang disetujui. Gunakan "Ditolak Seluruhnya" beserta alasannya.',
                );
            }

            $this->approvals->record($r, $approver, ApprovalDecision::Approved, 2);
            RequestApproved::dispatch($r, 2);
        });
    }

    public function rejectByStationery(Request $request, User $approver, string $reason): Request
    {
        $this->guardReason($reason);

        return $this->requests->transition($request, RequestAction::RejectAll, function (Request $r) use ($approver, $reason): void {
            $r->loadMissing('items');

            foreach ($r->items as $line) {
                $line->forceFill([
                    'quantity_approved' => 0,
                    'status' => RequestItemStatus::Rejected,
                ])->save();
            }

            $this->approvals->record($r, $approver, ApprovalDecision::Rejected, 2, $reason);
            RequestRejected::dispatch($r, 2, $reason);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Level 3 — Pimpinan SGA
    |--------------------------------------------------------------------------
    */

    public function approveBySga(Request $request, User $approver): Request
    {
        return $this->requests->transition($request, RequestAction::ApproveL3, function (Request $r) use ($approver): void {
            $this->approvals->record($r, $approver, ApprovalDecision::Approved, 3);
            RequestApproved::dispatch($r, 3);
        });
    }

    /**
     * Menolak di level SGA — reservasi DILEPAS.
     *
     * Stok yang tadinya dikunci harus kembali tersedia untuk request lain,
     * karena request ini belum tentu diajukan ulang.
     */
    public function rejectBySga(Request $request, User $approver, string $reason): Request
    {
        $this->guardReason($reason);

        return $this->requests->transition($request, RequestAction::RejectL3, function (Request $r) use ($approver, $reason): void {
            $this->releaseReservations($r);

            $this->approvals->record($r, $approver, ApprovalDecision::Rejected, 3, $reason);
            RequestRejected::dispatch($r, 3, $reason);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Revisi
    |--------------------------------------------------------------------------
    */

    /**
     * Mengajukan ulang setelah penolakan.
     *
     * Aktor perevisinya berbeda tergantung siapa yang menolak — Requester untuk
     * penolakan Pimpinan User, PIC Stationery untuk penolakan SGA. Yang
     * memastikan aktor tepat adalah RequestPolicy, bukan service ini.
     *
     * @param  array<int, array{quantity: int, remark?: string|null}>|null  $decisions
     *                                                                                  Diisi hanya pada revisi oleh PIC Stationery (kuantitas ditetapkan ulang).
     */
    public function revise(Request $request, User $actor, ?array $decisions = null): Request
    {
        return $this->requests->transition($request, RequestAction::Revise, function (Request $r) use ($actor, $decisions): void {
            $r->loadMissing('items');

            // Keputusan lama DIANULIR, bukan dihapus — riwayat penolakan harus
            // tetap terlihat saat audit.
            $this->approvals->supersedeAll($r);

            if ($decisions !== null) {
                // Revisi PIC Stationery: reservasi lama dilepas dulu, lalu
                // dibuat ulang mengikuti kuantitas yang baru.
                $this->releaseReservations($r);

                foreach ($r->items as $line) {
                    if (! array_key_exists($line->id, $decisions)) {
                        continue;
                    }

                    $approved = (int) $decisions[$line->id]['quantity'];

                    $line->forceFill([
                        'quantity_approved' => $approved,
                        'remark' => $decisions[$line->id]['remark'] ?? $line->remark,
                        'status' => RequestItemStatus::fromQuantities($line->quantity_requested, $approved),
                    ])->save();

                    if ($approved > 0) {
                        $this->reserveFor($line, $actor);
                    }
                }
            }

            $r->forceFill(['revision_count' => $r->revision_count + 1])->save();

            RequestSubmitted::dispatch($r);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Reservasi
    |--------------------------------------------------------------------------
    */

    private function reserveFor(RequestItem $line, User $actor): void
    {
        $item = Item::query()->findOrFail($line->item_id);

        $this->reservations->reserve(
            $item,
            $line->quantity_approved ?? 0,
            $actor,
            $line->id,
        );
    }

    /** Melepas seluruh reservasi aktif milik request ini. */
    private function releaseReservations(Request $request): void
    {
        $itemIds = $request->items->pluck('id')->all();

        if ($itemIds === []) {
            return;
        }

        StockReservation::query()
            ->whereIn('request_item_id', $itemIds)
            ->where('status', ReservationStatus::Held->value)
            ->get()
            ->each(fn (StockReservation $reservation) => $this->reservations->release($reservation));
    }

    private function guardReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException('Penolakan wajib disertai alasan.');
        }
    }
}
