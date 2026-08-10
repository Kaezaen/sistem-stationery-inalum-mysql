<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Queries;

use App\Modules\Catalog\Models\Item;
use App\Modules\Inventory\Enums\TransactionType;
use App\Modules\Inventory\Models\InventoryTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Query kartu stok — riwayat pergerakan satu item.
 *
 * Diisolasi sebagai Query Object sesuai ADR-04: hanya query yang benar-benar
 * kompleks yang dipisahkan dari Service, sisanya memakai Eloquent langsung.
 */
class StockLedgerQuery
{
    /** @return LengthAwarePaginator<int, InventoryTransaction> */
    public function forItem(Item $item, int $perPage = 30): LengthAwarePaginator
    {
        return InventoryTransaction::query()
            ->with('performer:id,name')
            ->where('item_id', $item->id)
            // Diurutkan menurun agar pergerakan terbaru muncul lebih dulu;
            // id ikut diurutkan sebagai pemecah seri karena beberapa transaksi
            // dapat berbagi timestamp yang sama persis.
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Ringkasan pergerakan satu item dalam satu periode.
     *
     * @return array{opening: int, total_in: int, total_out: int, total_adjustment: int, closing: int}
     */
    public function summary(Item $item, string $from, string $until): array
    {
        $opening = InventoryTransaction::query()
            ->where('item_id', $item->id)
            ->where('transaction_date', '<', $from)
            ->orderByDesc('id')
            ->value('quantity_after') ?? 0;

        $inPeriod = fn (): \Illuminate\Database\Eloquent\Builder => InventoryTransaction::query()
            ->where('item_id', $item->id)
            ->whereBetween('transaction_date', [$from, $until]);

        $totalIn = (int) $inPeriod()->ofType(TransactionType::In)->sum('quantity');
        $totalOut = (int) $inPeriod()->ofType(TransactionType::Out)->sum('quantity');

        // Penyesuaian dijumlahkan menurut perubahan BERSIH, bukan besaran, karena
        // arahnya bisa menambah maupun mengurangi saldo.
        $totalAdjustment = (int) $inPeriod()
            ->ofType(TransactionType::Adjustment)
            ->sum(DB::raw('quantity_after - quantity_before'));

        $closing = InventoryTransaction::query()
            ->where('item_id', $item->id)
            ->where('transaction_date', '<=', $until)
            ->orderByDesc('id')
            ->value('quantity_after') ?? $opening;

        return [
            'opening' => (int) $opening,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'total_adjustment' => $totalAdjustment,
            'closing' => (int) $closing,
        ];
    }
}
