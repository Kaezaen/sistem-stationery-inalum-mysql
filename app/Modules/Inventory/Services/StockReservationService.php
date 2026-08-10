<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\StockReservation;
use App\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Mengunci stok untuk request yang sudah disetujui namun belum diserahkan (ADR-07).
 *
 * Reservasi TIDAK mengurangi stock_quantity — barangnya masih ada di gudang.
 * Yang bertambah adalah reserved_quantity, sehingga jumlah tersedia untuk request
 * baru berkurang. Stok fisik baru berkurang saat serah terima.
 */
class StockReservationService
{
    /** Reservasi yang menggantung dilepas otomatis setelah tenggat ini. */
    public const DEFAULT_TTL_DAYS = 30;

    /**
     * Menahan sejumlah stok untuk satu baris request.
     *
     * @throws InsufficientStockException
     */
    public function reserve(
        Item $item,
        int $quantity,
        User $createdBy,
        ?int $requestItemId = null,
        ?int $ttlDays = null,
    ): StockReservation {
        if ($quantity <= 0) {
            throw new BusinessRuleException('Jumlah reservasi harus lebih besar dari nol.');
        }

        return DB::transaction(function () use ($item, $quantity, $createdBy, $requestItemId, $ttlDays): StockReservation {
            $locked = Item::query()->lockForUpdate()->find($item->id);

            if ($locked === null) {
                throw new BusinessRuleException('Item tidak ditemukan.');
            }

            $available = max(0, $locked->stock_quantity - $locked->reserved_quantity);

            if ($quantity > $available) {
                throw InsufficientStockException::forItem($locked->item_code, $quantity, $available);
            }

            Item::query()->whereKey($locked->id)->update([
                'reserved_quantity' => $locked->reserved_quantity + $quantity,
                'updated_at' => now(),
            ]);

            return StockReservation::create([
                'item_id' => $locked->id,
                'request_item_id' => $requestItemId,
                'quantity' => $quantity,
                'status' => ReservationStatus::Held,
                'expires_at' => now()->addDays($ttlDays ?? self::DEFAULT_TTL_DAYS),
                'created_by' => $createdBy->id,
            ]);
        });
    }

    /**
     * Melepas reservasi tanpa penyerahan — request ditolak SGA atau dibatalkan.
     *
     * Idempoten: melepas reservasi yang sudah tidak aktif tidak melakukan apa-apa
     * dan tidak melempar exception, sehingga percobaan ulang tidak merusak saldo.
     */
    public function release(StockReservation $reservation): bool
    {
        return DB::transaction(function () use ($reservation): bool {
            $fresh = StockReservation::query()->lockForUpdate()->find($reservation->id);

            if ($fresh === null || ! $fresh->status->isActive()) {
                return false;
            }

            $item = Item::query()->lockForUpdate()->find($fresh->item_id);

            if ($item !== null) {
                Item::query()->whereKey($item->id)->update([
                    'reserved_quantity' => max(0, $item->reserved_quantity - $fresh->quantity),
                    'updated_at' => now(),
                ]);
            }

            $fresh->update(['status' => ReservationStatus::Released]);

            return true;
        });
    }

    /**
     * Menandai reservasi sebagai terpakai.
     *
     * Pengurangan stok fisiknya dilakukan StockService::decrease() dengan
     * $fromReservation = true. Method ini hanya mengubah status agar reservasi
     * tidak ikut dilepas oleh pembersih reservasi kedaluwarsa.
     */
    public function markConsumed(StockReservation $reservation): bool
    {
        return DB::transaction(function () use ($reservation): bool {
            $fresh = StockReservation::query()->lockForUpdate()->find($reservation->id);

            if ($fresh === null || ! $fresh->status->isActive()) {
                return false;
            }

            $fresh->update(['status' => ReservationStatus::Consumed]);

            return true;
        });
    }

    /**
     * Melepas sisa reservasi yang tidak jadi diserahkan.
     *
     * Dipakai pada penyerahan SEBAGIAN (keputusan D5). Urutannya:
     *   1. StockService::decrease($item, $actual, fromReservation: true)
     *      sudah mengurangi reserved_quantity sebanyak $actual.
     *   2. Method ini melepas selisihnya (dijanjikan - diserahkan).
     *
     * Tanpa langkah kedua, selisihnya akan menggantung sebagai stok terkunci
     * selamanya — barangnya ada di gudang tetapi tidak dapat dipakai siapa pun.
     *
     * @return int Jumlah yang dilepas.
     */
    public function settleRemainder(StockReservation $reservation, int $consumedQuantity): int
    {
        $remainder = $reservation->quantity - $consumedQuantity;

        if ($remainder <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($reservation, $remainder): int {
            $item = Item::query()->lockForUpdate()->find($reservation->item_id);

            if ($item === null) {
                return 0;
            }

            Item::query()->whereKey($item->id)->update([
                'reserved_quantity' => max(0, $item->reserved_quantity - $remainder),
                'updated_at' => now(),
            ]);

            return $remainder;
        });
    }

    /**
     * Melepas seluruh reservasi yang melewati tenggat.
     *
     * Tanpa pembersih ini, request yang tidak pernah diambil akan menahan stok
     * selamanya dan membuatnya tidak dapat dipakai siapa pun.
     *
     * @return int Jumlah reservasi yang dilepas.
     */
    public function releaseExpired(): int
    {
        $released = 0;

        StockReservation::query()
            ->expired()
            ->orderBy('id')
            ->chunkById(100, function ($reservations) use (&$released): void {
                foreach ($reservations as $reservation) {
                    if ($this->release($reservation)) {
                        $released++;
                    }
                }
            });

        return $released;
    }
}
