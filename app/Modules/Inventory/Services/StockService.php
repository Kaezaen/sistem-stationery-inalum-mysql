<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\TransactionType;
use App\Modules\Inventory\Events\StockFellBelowMinimum;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Shared\Exceptions\BusinessRuleException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA penulis stok di seluruh aplikasi.
 *
 * Tidak ada kode di luar kelas ini yang boleh menulis items.stock_quantity atau
 * items.reserved_quantity (§8.1 Architecture Blueprint aturan 1 & 3). Aturan ini
 * ditegakkan lewat code review dan uji arsitektur.
 *
 * Tiga jaminan yang diberikan setiap method di sini:
 *
 *   1. Berjalan di dalam DB transaction.
 *   2. Mengunci baris item dengan SELECT ... FOR UPDATE sebelum membaca saldo.
 *      Tanpa ini, dua serah terima bersamaan atas item yang sama akan membaca
 *      saldo yang sama dan menuliskan hasil yang salah — kerusakan senyap yang
 *      baru terlihat saat stok fisik tidak cocok.
 *   3. Menghasilkan tepat satu baris ledger. Tidak ada mutasi senyap.
 */
class StockService
{
    /**
     * Menambah stok — dipakai verifikasi pembelian.
     *
     * @param  Model|null  $reference  Dokumen sumber (mis. Purchase)
     */
    public function increase(
        Item $item,
        int $quantity,
        User $performedBy,
        ?Model $reference = null,
    ): InventoryTransaction {
        $this->guardPositive($quantity);

        return DB::transaction(function () use ($item, $quantity, $performedBy, $reference): InventoryTransaction {
            $locked = $this->lock($item);

            $before = $locked->stock_quantity;
            $after = $before + $quantity;

            $this->applyBalance($locked, $after, $locked->reserved_quantity);

            return $this->writeLedger(
                $locked, TransactionType::In, $quantity, $before, $after, $performedBy, $reference,
            );
        });
    }

    /**
     * Mengurangi stok — dipakai serah terima barang.
     *
     * @throws InsufficientStockException
     */
    public function decrease(
        Item $item,
        int $quantity,
        User $performedBy,
        ?Model $reference = null,
        bool $fromReservation = false,
    ): InventoryTransaction {
        $this->guardPositive($quantity);

        return DB::transaction(function () use ($item, $quantity, $performedBy, $reference, $fromReservation): InventoryTransaction {
            $locked = $this->lock($item);

            $before = $locked->stock_quantity;
            $reserved = $locked->reserved_quantity;

            /*
             * Stok yang sudah direservasi tidak boleh dipakai request lain.
             *
             * Saat penyerahan atas reservasi sendiri ($fromReservation = true),
             * kuantitasnya justru berasal dari jatah yang sudah dikunci, sehingga
             * yang diperiksa adalah stok fisik — bukan stok tersedia.
             */
            $usable = $fromReservation ? $before : max(0, $before - $reserved);

            if ($quantity > $usable) {
                throw InsufficientStockException::forItem($locked->item_code, $quantity, $usable);
            }

            $after = $before - $quantity;
            $newReserved = $fromReservation ? max(0, $reserved - $quantity) : $reserved;

            $this->applyBalance($locked, $after, $newReserved);

            $transaction = $this->writeLedger(
                $locked, TransactionType::Out, $quantity, $before, $after, $performedBy, $reference,
            );

            $this->notifyIfCrossedMinimum($locked, $before, $after);

            return $transaction;
        });
    }

    /**
     * Koreksi stok ke nilai absolut — stock opname, saldo awal, pembetulan.
     *
     * Memakai nilai TUJUAN, bukan selisih, karena itulah yang diketahui petugas
     * saat menghitung fisik. Selisihnya dihitung sistem agar tidak salah tanda.
     *
     * @throws BusinessRuleException
     */
    public function adjustTo(
        Item $item,
        int $targetQuantity,
        User $performedBy,
        string $reason,
    ): ?InventoryTransaction {
        if (trim($reason) === '') {
            throw new BusinessRuleException('Penyesuaian stok wajib disertai alasan.');
        }

        if ($targetQuantity < 0) {
            throw new BusinessRuleException('Stok hasil penyesuaian tidak boleh negatif.');
        }

        return DB::transaction(function () use ($item, $targetQuantity, $performedBy, $reason): ?InventoryTransaction {
            $locked = $this->lock($item);

            $before = $locked->stock_quantity;

            // Tidak ada perubahan berarti tidak ada yang perlu dicatat — ledger
            // tidak boleh dipenuhi baris tanpa makna.
            if ($before === $targetQuantity) {
                return null;
            }

            if ($targetQuantity < $locked->reserved_quantity) {
                throw new BusinessRuleException(sprintf(
                    'Stok hasil penyesuaian (%d) lebih kecil daripada jumlah yang sedang direservasi (%d) untuk item %s.',
                    $targetQuantity,
                    $locked->reserved_quantity,
                    $locked->item_code,
                ));
            }

            $this->applyBalance($locked, $targetQuantity, $locked->reserved_quantity);

            $transaction = $this->writeLedger(
                $locked,
                TransactionType::Adjustment,
                abs($targetQuantity - $before),
                $before,
                $targetQuantity,
                $performedBy,
                null,
                $reason,
            );

            $this->notifyIfCrossedMinimum($locked, $before, $targetQuantity);

            return $transaction;
        });
    }

    /**
     * Memancarkan StockFellBelowMinimum bila saldo melintasi batas minimum dari
     * atas ke bawah. Hanya saat MELINTASI — bukan tiap kali di bawah min — agar
     * peringatan N11 tidak berulang untuk item yang sama.
     *
     * min_stock = 0 tidak pernah memicu: stok tak bisa negatif sehingga tak ada
     * "di bawah nol".
     */
    private function notifyIfCrossedMinimum(Item $item, int $before, int $after): void
    {
        if ($item->min_stock > 0 && $before >= $item->min_stock && $after < $item->min_stock) {
            StockFellBelowMinimum::dispatch($item);
        }
    }

    /**
     * Menghitung saldo menurut ledger.
     *
     * Dipakai perintah stock:reconcile untuk membandingkan terhadap saldo
     * ter-cache. Selisih menandakan ada mutasi yang tidak melewati service ini.
     */
    public function ledgerBalance(Item $item): int
    {
        $balance = InventoryTransaction::query()
            ->where('item_id', $item->id)
            ->orderByDesc('id')
            ->value('quantity_after');

        // Item tanpa satu pun transaksi bersaldo nol menurut ledger.
        return $balance === null ? 0 : (int) $balance;
    }

    /**
     * Mengunci baris item untuk transaksi berjalan.
     *
     * SELECT ... FOR UPDATE menahan transaksi lain yang menyentuh baris yang sama
     * sampai transaksi ini selesai. Inilah satu-satunya hal yang mencegah dua
     * pengurangan bersamaan sama-sama membaca saldo lama.
     */
    private function lock(Item $item): Item
    {
        $locked = Item::query()->lockForUpdate()->find($item->id);

        if ($locked === null) {
            throw new BusinessRuleException('Item tidak ditemukan atau sudah dihapus.');
        }

        return $locked;
    }

    /**
     * Menulis saldo baru.
     *
     * Memakai query builder langsung, bukan $model->save(), agar tidak ada
     * atribut lain yang ikut tertulis tanpa sengaja — kolom saldo adalah satu-
     * satunya yang boleh berubah di sini.
     */
    private function applyBalance(Item $item, int $stock, int $reserved): void
    {
        Item::query()->whereKey($item->id)->update([
            'stock_quantity' => $stock,
            'reserved_quantity' => $reserved,
            'updated_at' => now(),
        ]);

        $item->stock_quantity = $stock;
        $item->reserved_quantity = $reserved;
    }

    private function writeLedger(
        Item $item,
        TransactionType $type,
        int $quantity,
        int $before,
        int $after,
        User $performedBy,
        ?Model $reference = null,
        ?string $reason = null,
    ): InventoryTransaction {
        return InventoryTransaction::create([
            'item_id' => $item->id,
            'transaction_type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reference_type' => $reference === null ? null : $reference::class,
            'reference_id' => $reference?->getKey(),
            'transaction_date' => now(),
            'performed_by' => $performedBy->id,
            'adjustment_reason' => $reason,
        ]);
    }

    private function guardPositive(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new BusinessRuleException('Jumlah pergerakan stok harus lebih besar dari nol.');
        }
    }
}
