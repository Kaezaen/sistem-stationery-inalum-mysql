<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Workflows;

use App\Modules\Approval\Exceptions\InvalidStateTransitionException;
use App\Modules\Purchasing\Enums\PurchaseAction;
use App\Modules\Purchasing\Enums\PurchaseStatus;
use App\Modules\Purchasing\Models\Purchase;

/**
 * Mesin status dokumen pembelian.
 *
 * Ditulis sebagai TABEL TRANSISI deklaratif, bukan if/else yang tersebar
 * (ADR-05). Seluruh alur dapat dibaca sekali pandang dan dipetakan langsung ke
 * diagram §7 Architecture Blueprint. Menambah status di masa depan berarti
 * mengubah satu tabel di berkas ini, bukan memburu percabangan di banyak tempat.
 */
class PurchaseWorkflow
{
    /**
     * status sekarang => [aksi => status tujuan]
     *
     * @return array<string, array<string, PurchaseStatus>>
     */
    public function transitions(): array
    {
        return [
            PurchaseStatus::Draft->value => [
                PurchaseAction::Submit->value => PurchaseStatus::PendingVerification,
            ],
            PurchaseStatus::PendingVerification->value => [
                PurchaseAction::Verify->value => PurchaseStatus::Verified,
                PurchaseAction::Reject->value => PurchaseStatus::Rejected,
            ],
            PurchaseStatus::Rejected->value => [
                // PIC Gudang memperbaiki dokumen lalu mengajukannya kembali.
                PurchaseAction::Revise->value => PurchaseStatus::PendingVerification,
            ],
            // VERIFIED bersifat terminal: stok sudah bertambah, dan membatalkannya
            // berarti mengurangi stok yang mungkin sudah terlanjur dipakai.
            // Koreksi dilakukan lewat transaksi ADJUSTMENT, bukan dengan
            // memundurkan status dokumen.
            PurchaseStatus::Verified->value => [],
        ];
    }

    public function canTransition(PurchaseStatus $from, PurchaseAction $action): bool
    {
        return isset($this->transitions()[$from->value][$action->value]);
    }

    /**
     * Status tujuan dari sebuah aksi.
     *
     * @throws InvalidStateTransitionException
     */
    public function target(Purchase $purchase, PurchaseAction $action): PurchaseStatus
    {
        $from = $purchase->status;

        if (! $this->canTransition($from, $action)) {
            throw InvalidStateTransitionException::make(
                "Pembelian {$purchase->purchase_number}",
                $from->label(),
                $action->label(),
            );
        }

        return $this->transitions()[$from->value][$action->value];
    }

    /**
     * Aksi yang tersedia dari status tertentu — dipakai UI untuk menentukan
     * tombol mana yang ditampilkan.
     *
     * @return list<PurchaseAction>
     */
    public function availableActions(PurchaseStatus $from): array
    {
        return array_values(array_map(
            static fn (string $action): PurchaseAction => PurchaseAction::from($action),
            array_keys($this->transitions()[$from->value] ?? []),
        ));
    }
}
