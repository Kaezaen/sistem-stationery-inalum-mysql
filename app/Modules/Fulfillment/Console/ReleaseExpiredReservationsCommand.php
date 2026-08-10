<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment\Console;

use App\Modules\Inventory\Services\StockReservationService;
use Illuminate\Console\Command;

/**
 * Melepas reservasi yang melewati tenggat.
 *
 * Konsekuensi wajar dari ADR-07: request yang disetujui namun tidak pernah
 * diambil akan menahan stok selamanya. Tanpa pembersih ini, stok terlihat habis
 * padahal barangnya masih ada di gudang.
 *
 * Dijadwalkan harian lewat routes/console.php.
 */
class ReleaseExpiredReservationsCommand extends Command
{
    protected $signature = 'stock:release-expired-reservations';

    protected $description = 'Melepas reservasi stok yang sudah melewati tenggat pengambilan';

    public function handle(StockReservationService $reservations): int
    {
        $released = $reservations->releaseExpired();

        if ($released === 0) {
            $this->info('Tidak ada reservasi kedaluwarsa.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%d reservasi kedaluwarsa dilepas.', $released));

        return self::SUCCESS;
    }
}
