<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Console;

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\StockService;
use App\Shared\Exceptions\BusinessRuleException;
use Illuminate\Console\Command;

/**
 * Penyesuaian stok lewat CLI — stock opname, saldo awal migrasi, koreksi.
 *
 * Sengaja disediakan sebagai perintah, bukan hanya lewat UI, karena pengisian
 * saldo awal saat go-live melibatkan ribuan item dan harus dapat diskrip.
 */
class AdjustStockCommand extends Command
{
    protected $signature = 'stock:adjust
                            {item : Item code}
                            {quantity : Saldo stok setelah penyesuaian}
                            {--reason= : Alasan penyesuaian (wajib)}
                            {--user= : Username pelaku; default administrator pertama}';

    protected $description = 'Menyesuaikan saldo stok sebuah item ke nilai tertentu';

    public function handle(StockService $stock): int
    {
        $item = Item::where('item_code', $this->argument('item'))->first();

        if ($item === null) {
            $this->error(sprintf('Item dengan kode %s tidak ditemukan.', $this->argument('item')));

            return self::FAILURE;
        }

        $reason = (string) ($this->option('reason') ?? '');

        if (trim($reason) === '') {
            $this->error('Opsi --reason wajib diisi. Penyesuaian tanpa alasan tidak dapat diaudit.');

            return self::FAILURE;
        }

        $user = $this->resolveUser();

        if ($user === null) {
            $this->error('Pelaku penyesuaian tidak ditemukan.');

            return self::FAILURE;
        }

        try {
            $transaction = $stock->adjustTo($item, (int) $this->argument('quantity'), $user, $reason);
        } catch (BusinessRuleException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($transaction === null) {
            $this->info('Saldo sudah sesuai; tidak ada perubahan yang dicatat.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Stok %s disesuaikan dari %d menjadi %d.',
            $item->item_code,
            $transaction->quantity_before,
            $transaction->quantity_after,
        ));

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
