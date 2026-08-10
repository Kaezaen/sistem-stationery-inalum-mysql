<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Services;

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Inventory\Models\StockReservation;
use App\Modules\Inventory\Services\StockReservationService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Requisition\Enums\RequestAction;
use App\Modules\Requisition\Enums\RequestItemStatus;
use App\Modules\Requisition\Events\RequestCompleted;
use App\Modules\Requisition\Models\Request;
use App\Modules\Requisition\Models\RequestItem;
use App\Modules\Requisition\Services\RequestService;
use App\Shared\Exceptions\BusinessRuleException;

/**
 * Serah terima barang oleh PIC Gudang — wireframe 3.5.2.
 *
 * Titik SATU-SATUNYA di mana stok fisik berkurang (§8.1 Architecture Blueprint).
 * Seluruh langkahnya berjalan dalam satu transisi ber-lock, sehingga request
 * tidak akan pernah berstatus COMPLETED tanpa ledger yang menyertainya.
 *
 * Penyerahan SEBAGIAN diizinkan (keputusan D5): stok fisik bisa saja kurang dari
 * yang dijanjikan saat approval. Sisa yang tidak jadi diserahkan dianggap hangus
 * — request tetap selesai, tidak menyisakan dokumen terbuka.
 */
class HandoverService
{
    public function __construct(
        private readonly RequestService $requests,
        private readonly StockService $stock,
        private readonly StockReservationService $reservations,
    ) {}

    /**
     * Menyerahkan barang.
     *
     * @param  array<int, int>|null  $quantities  request_item_id => jumlah diserahkan.
     *                                            null berarti seluruh baris diserahkan penuh sesuai yang disetujui.
     *
     * @throws BusinessRuleException
     */
    public function handover(Request $request, User $officer, ?array $quantities = null): Request
    {
        return $this->requests->transition($request, RequestAction::Handover, function (Request $r) use ($officer, $quantities): void {
            $r->loadMissing('items');

            if ($r->items->isEmpty()) {
                throw new BusinessRuleException('Request tanpa item tidak dapat diserahkan.');
            }

            $anyIssued = false;

            foreach ($r->items as $line) {
                $approved = $line->quantity_approved ?? 0;

                // Baris yang ditolak PIC Stationery tidak punya apa pun untuk
                // diserahkan — dilewati tanpa menyentuh stok.
                if ($approved <= 0) {
                    $line->forceFill(['quantity_actual' => 0])->save();

                    continue;
                }

                $actual = $quantities === null
                    ? $approved
                    : (int) ($quantities[$line->id] ?? $approved);

                $this->guardQuantity($line, $actual, $approved);

                $this->issueLine($line, $actual, $approved, $officer, $r);

                if ($actual > 0) {
                    $anyIssued = true;
                }
            }

            if (! $anyIssued) {
                throw new BusinessRuleException(
                    'Tidak ada satu pun item yang diserahkan. Periksa kembali jumlah yang diinput.',
                );
            }

            $r->forceFill(['completed_at' => now()])->save();

            RequestCompleted::dispatch($r);
        });
    }

    /**
     * Memproses satu baris: catat jumlah, kurangi stok, selesaikan reservasi.
     */
    private function issueLine(
        RequestItem $line,
        int $actual,
        int $approved,
        User $officer,
        Request $request,
    ): void {
        $reservation = $this->activeReservationFor($line);

        if ($actual > 0) {
            $item = Item::query()->findOrFail($line->item_id);

            /*
             * fromReservation: true — jumlah ini berasal dari jatah yang sudah
             * dikunci, sehingga yang diperiksa adalah stok FISIK, bukan stok
             * tersedia (yang sudah dipotong reservasi ini sendiri).
             *
             * Bila stok fisik ternyata kurang dari yang dijanjikan, exception
             * dari StockService akan membatalkan seluruh transisi.
             */
            $this->stock->decrease($item, $actual, $officer, $request, fromReservation: true);
        }

        if ($reservation !== null) {
            // Melepas selisih yang tidak jadi diserahkan agar tidak menggantung
            // sebagai stok terkunci selamanya.
            $this->reservations->settleRemainder($reservation, $actual);
            $this->reservations->markConsumed($reservation);
        }

        $line->forceFill([
            'quantity_actual' => $actual,
            'status' => $actual > 0 ? RequestItemStatus::Issued : RequestItemStatus::Rejected,
            'remark' => $actual < $approved
                ? trim(($line->remark ?? '').sprintf(' [Diserahkan %d dari %d]', $actual, $approved))
                : $line->remark,
        ])->save();
    }

    private function activeReservationFor(RequestItem $line): ?StockReservation
    {
        return StockReservation::query()
            ->where('request_item_id', $line->id)
            ->where('status', ReservationStatus::Held->value)
            ->first();
    }

    /** @throws BusinessRuleException */
    private function guardQuantity(RequestItem $line, int $actual, int $approved): void
    {
        if ($actual < 0) {
            throw new BusinessRuleException('Jumlah yang diserahkan tidak boleh negatif.');
        }

        // Menyerahkan lebih dari yang disetujui akan melampaui kewenangan
        // approval — juga ditolak constraint database sebagai jaring pengaman.
        if ($actual > $approved) {
            throw new BusinessRuleException(sprintf(
                'Jumlah yang diserahkan untuk item %s (%d) melebihi yang disetujui (%d).',
                $line->item->item_code ?? $line->item_id,
                $actual,
                $approved,
            ));
        }
    }
}
