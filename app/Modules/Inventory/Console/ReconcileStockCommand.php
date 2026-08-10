<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Console;

use App\Modules\Catalog\Models\Item;
use App\Modules\Inventory\Models\InventoryTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membandingkan saldo ter-cache terhadap ledger.
 *
 * ADR-08 menerima saldo ter-cache demi kecepatan, dengan syarat ada pemeriksaan
 * berkala. Selisih berarti ada mutasi yang TIDAK melewati StockService — bug
 * yang harus segera ditelusuri sebelum data transaksi menumpuk di atasnya.
 */
class ReconcileStockCommand extends Command
{
    protected $signature = 'stock:reconcile
                            {--fix : Perbaiki saldo agar mengikuti ledger}';

    protected $description = 'Memeriksa keselarasan items.stock_quantity terhadap ledger inventory_transactions';

    public function handle(): int
    {
        $drifts = [];

        Item::withTrashed()->orderBy('id')->chunkById(200, function ($items) use (&$drifts): void {
            foreach ($items as $item) {
                $ledgerBalance = InventoryTransaction::query()
                    ->where('item_id', $item->id)
                    ->orderByDesc('id')
                    ->value('quantity_after');

                // Item tanpa satu pun transaksi harus bersaldo nol. Bila tidak,
                // stoknya masuk lewat jalur yang tidak meninggalkan jejak.
                $expected = $ledgerBalance === null ? 0 : (int) $ledgerBalance;

                if ($item->stock_quantity !== $expected) {
                    $drifts[] = [
                        'id' => $item->id,
                        'code' => $item->item_code,
                        'cached' => $item->stock_quantity,
                        'ledger' => $expected,
                        'selisih' => $item->stock_quantity - $expected,
                    ];
                }
            }
        });

        if ($drifts === []) {
            $this->info('Seluruh saldo stok selaras dengan ledger.');

            return self::SUCCESS;
        }

        $this->error(sprintf('Ditemukan %d item dengan saldo tidak selaras:', count($drifts)));
        $this->table(['ID', 'Item Code', 'Saldo Tersimpan', 'Saldo Ledger', 'Selisih'], $drifts);

        if (! $this->option('fix')) {
            $this->warn('Jalankan ulang dengan --fix untuk menyelaraskan saldo mengikuti ledger.');
            $this->warn('Telusuri dulu penyebabnya: selisih berarti ada mutasi di luar StockService.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($drifts): void {
            foreach ($drifts as $drift) {
                Item::withTrashed()->whereKey($drift['id'])->update([
                    'stock_quantity' => $drift['ledger'],
                    'updated_at' => now(),
                ]);
            }
        });

        $this->info(sprintf('%d saldo diselaraskan mengikuti ledger.', count($drifts)));

        return self::SUCCESS;
    }
}
