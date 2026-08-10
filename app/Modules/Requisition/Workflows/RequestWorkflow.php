<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Workflows;

use App\Modules\Approval\Exceptions\InvalidStateTransitionException;
use App\Modules\Requisition\Enums\RequestAction;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Models\Request;

/**
 * Mesin status request — tabel transisi §6.1 Architecture Blueprint.
 *
 * Ditulis deklaratif (ADR-05) sehingga seluruh alur tiga level approval dapat
 * dibaca sekali pandang. Tanpa ini, percabangan status akan tersebar di banyak
 * service dan satu cabang yang terlewat menjadi lubang yang sulit ditemukan.
 *
 * Perhatikan dua jalur revisi yang berbeda aktor — inti temuan blueprint:
 *   REJECTED_SUPERVISOR -> revise oleh REQUESTER      (Bab 3.6)
 *   REJECTED_SGA        -> revise oleh PIC STATIONERY (Bab 3.7)
 */
class RequestWorkflow
{
    /**
     * status sekarang => [aksi => status tujuan]
     *
     * @return array<string, array<string, RequestStatus>>
     */
    public function transitions(): array
    {
        return [
            RequestStatus::Draft->value => [
                RequestAction::Submit->value => RequestStatus::PendingSupervisor,
                RequestAction::Cancel->value => RequestStatus::Cancelled,
            ],

            RequestStatus::PendingSupervisor->value => [
                RequestAction::ApproveL1->value => RequestStatus::PendingStationery,
                RequestAction::RejectL1->value => RequestStatus::RejectedSupervisor,
                RequestAction::Cancel->value => RequestStatus::Cancelled,
            ],

            RequestStatus::RejectedSupervisor->value => [
                // Direvisi REQUESTER, lalu kembali ke antrian Pimpinan.
                RequestAction::Revise->value => RequestStatus::PendingSupervisor,
                RequestAction::Cancel->value => RequestStatus::Cancelled,
            ],

            RequestStatus::PendingStationery->value => [
                RequestAction::ApproveL2->value => RequestStatus::PendingSga,
                RequestAction::RejectAll->value => RequestStatus::RejectedStationery,
            ],

            // Keputusan D1: penolakan PIC Stationery bersifat FINAL. Requester
            // membuat request baru, bukan merevisi yang ini.
            RequestStatus::RejectedStationery->value => [],

            RequestStatus::PendingSga->value => [
                RequestAction::ApproveL3->value => RequestStatus::ReadyForHandover,
                RequestAction::RejectL3->value => RequestStatus::RejectedSga,
            ],

            RequestStatus::RejectedSga->value => [
                // Direvisi PIC STATIONERY — bukan requester — lalu kembali ke SGA.
                RequestAction::Revise->value => RequestStatus::PendingSga,
            ],

            RequestStatus::ReadyForHandover->value => [
                RequestAction::Handover->value => RequestStatus::Completed,
            ],

            RequestStatus::Completed->value => [],
            RequestStatus::Cancelled->value => [],
        ];
    }

    public function canTransition(RequestStatus $from, RequestAction $action): bool
    {
        return isset($this->transitions()[$from->value][$action->value]);
    }

    /**
     * @throws InvalidStateTransitionException
     */
    public function target(Request $request, RequestAction $action): RequestStatus
    {
        if (! $this->canTransition($request->status, $action)) {
            throw InvalidStateTransitionException::make(
                "Request {$request->request_number}",
                $request->status->label(),
                $action->label(),
            );
        }

        return $this->transitions()[$request->status->value][$action->value];
    }

    /**
     * @return list<RequestAction>
     */
    public function availableActions(RequestStatus $from): array
    {
        return array_values(array_map(
            static fn (string $a): RequestAction => RequestAction::from($a),
            array_keys($this->transitions()[$from->value] ?? []),
        ));
    }

    /**
     * Titik di mana stok DIRESERVASI (ADR-07).
     *
     * Reservasi dibuat saat PIC Stationery menetapkan kuantitas, karena sejak
     * saat itulah sistem menjanjikan barang kepada requester — meski
     * penyerahannya masih menunggu approval SGA.
     */
    public function reservesStock(RequestAction $action): bool
    {
        return $action === RequestAction::ApproveL2;
    }

    /** Titik di mana reservasi harus DILEPAS kembali. */
    public function releasesStock(RequestAction $action): bool
    {
        return in_array($action, [RequestAction::RejectL3, RequestAction::Cancel], true);
    }
}
