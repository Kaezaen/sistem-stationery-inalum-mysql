<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Console;

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\StockService;
use App\Shared\Exceptions\BusinessRuleException;
use Illuminate\Console\Command;

/**
 * Mengisi saldo awal seluruh item aktif ke max_stock lewat transaksi ADJUSTMENT.
 *
 * Saldo awal WAJIB masuk lewat ledger (StockService::adjustTo), bukan UPDATE
 * langsung ke items.stock_quantity — bila dilanggar, ledger tidak akan pernah
 * rekonsiliasi dan stock:reconcile selamanya melapor selisih (ADR-08, §9 roadmap).
 *
 * Idempoten: adjustTo memakai nilai TUJUAN absolut, sehingga item yang sudah
 * bersaldo max tidak menghasilkan baris ledger baru saat dijalankan ulang.
 */
class SeedInitialStockCommand extends Command
{
    protected $signature = 'stock:seed-initial
                            {--reason=Saldo Awal Migrasi : Alasan penyesuaian}
                            {--user= : Username pelaku; default administrator pertama}
                            {--only-empty : Hanya isi item yang stoknya masih 0}';

    protected $description = 'Mengisi saldo awal seluruh item aktif ke max_stock lewat transaksi ADJUSTMENT';

    public function handle(StockService $stock): int
    {
        $reason = trim((string) ($this->option('reason') ?: ''));

        if ($reason === '') {
            $this->error('Opsi --reason wajib diisi. Penyesuaian tanpa alasan tidak dapat diaudit.');

            return self::FAILURE;
        }

        $user = $this->resolveUser();

        if ($user === null) {
            $this->error('Pelaku penyesuaian tidak ditemukan.');

            return self::FAILURE;
        }

        // 236 item = kecil; dimuat sekaligus. Filter (is_active, max_stock>0) stabil
        // karena tidak diubah oleh penyesuaian.
        $items = Item::query()
            ->where('is_active', true)
            ->where('max_stock', '>', 0)
            ->when((bool) $this->option('only-empty'), fn ($q) => $q->where('stock_quantity', 0))
            ->orderBy('id')
            ->get();

        $adjusted = 0;
        $unchanged = 0;
        $errors = [];

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        foreach ($items as $item) {
            try {
                $transaction = $stock->adjustTo($item, $item->max_stock, $user, $reason);
                $transaction === null ? $unchanged++ : $adjusted++;
            } catch (BusinessRuleException $e) {
                $errors[] = $item->item_code.': '.$e->getMessage();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Saldo awal selesai: %d item diisi ke max_stock, %d sudah sesuai.',
            $adjusted,
            $unchanged,
        ));

        if ($errors !== []) {
            $this->warn(sprintf('%d item gagal:', count($errors)));
            foreach (array_slice($errors, 0, 20) as $line) {
                $this->line('  '.$line);
            }

            return self::FAILURE;
        }

        $this->line('Jalankan `php artisan stock:reconcile` dan `php artisan stock:snapshot --current`.');

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $username = $this->option('user');

        if ($username !== null) {
            return User::where('username', $username)->first();
        }

        return User::role('administrator')->orderBy('id')->first();
    }
}
