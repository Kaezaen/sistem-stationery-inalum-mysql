<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Console;

use App\Modules\Inventory\Services\StockSnapshotService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Membangun snapshot saldo stok bulanan (R1 Stock by Month / R2 Stock by Year).
 *
 * Perilaku default (tanpa opsi) menghasilkan snapshot bulan LENGKAP terakhir —
 * cocok dijalankan scheduler pada tanggal 1 tiap bulan. Opsi lain:
 *
 *   --current            perbarui bulan berjalan (agar laporan/dashboard tetap segar)
 *   --period=YYYY-MM     periode tertentu
 *   --backfill           seluruh bulan dari transaksi pertama s/d bulan lengkap terakhir
 *                        (dipakai saat deploy agar R1/R2 tak perlu menunggu satu siklus)
 *
 * Aman dijalankan ulang: snapshot bersifat idempoten (upsert atas periode-item).
 */
class GenerateMonthlyStockSnapshotCommand extends Command
{
    protected $signature = 'stock:snapshot
                            {--period= : Periode YYYY-MM tertentu}
                            {--current : Perbarui bulan berjalan, bukan bulan lengkap terakhir}
                            {--backfill : Isi seluruh riwayat dari ledger}';

    protected $description = 'Membangun snapshot saldo stok bulanan untuk laporan Stock by Month/Year';

    public function handle(StockSnapshotService $snapshots): int
    {
        if ($this->option('backfill')) {
            return $this->runBackfill($snapshots);
        }

        $period = $this->resolvePeriod();

        if ($period === null) {
            $this->error('Format --period tidak sah. Gunakan YYYY-MM, mis. 2026-07.');

            return self::FAILURE;
        }

        [$year, $month] = $period;

        $rows = $snapshots->generateForPeriod($year, $month);

        $this->info(sprintf('Snapshot %04d-%02d selesai: %d item.', $year, $month, $rows));

        return self::SUCCESS;
    }

    private function runBackfill(StockSnapshotService $snapshots): int
    {
        $periods = $snapshots->backfill();

        if ($periods === []) {
            $this->warn('Tidak ada bulan lengkap untuk di-backfill (belum ada transaksi, atau seluruhnya di bulan berjalan). Pakai --current untuk bulan berjalan.');

            return self::SUCCESS;
        }

        foreach ($periods as $p) {
            $this->line(sprintf('  %04d-%02d: %d item', $p['year'], $p['month'], $p['rows']));
        }

        $this->info(sprintf('Backfill selesai: %d periode.', count($periods)));

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}|null [year, month]
     */
    private function resolvePeriod(): ?array
    {
        $raw = $this->option('period');

        if (is_string($raw) && $raw !== '') {
            if (preg_match('/^(\d{4})-(\d{2})$/', $raw, $m) !== 1) {
                return null;
            }

            $month = (int) $m[2];

            if ($month < 1 || $month > 12) {
                return null;
            }

            return [(int) $m[1], $month];
        }

        // Tanpa periode eksplisit: bulan berjalan bila --current, selain itu bulan
        // lengkap terakhir (bulan lalu) — sasaran normal job bulanan.
        $target = $this->option('current')
            ? CarbonImmutable::now()
            : CarbonImmutable::now()->subMonthNoOverflow();

        return [$target->year, $target->month];
    }
}
