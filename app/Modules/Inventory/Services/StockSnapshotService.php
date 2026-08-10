<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Item;
use App\Modules\Inventory\Enums\TransactionType;
use App\Modules\Inventory\Models\StockMonthlySnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Membangun snapshot saldo stok bulanan dari ledger.
 *
 * Diletakkan di modul Inventory dengan sengaja: ia MEMBACA inventory_transactions,
 * dan uji arsitektur menegakkan bahwa ledger hanya boleh disentuh dari dalam modul
 * ini. Modul Reporting kemudian hanya membaca tabel snapshot yang dihasilkan —
 * tidak pernah ledger secara langsung.
 *
 * Nilai yang dihitung per item per periode:
 *
 *   opening_balance  = saldo tepat sebelum awal bulan
 *   total_in         = jumlah kuantitas transaksi IN dalam bulan
 *   total_out        = jumlah kuantitas transaksi OUT dalam bulan
 *   total_adjustment = perubahan BERSIH transaksi ADJUSTMENT (bisa negatif)
 *   closing_balance  = saldo tepat sebelum awal bulan berikutnya
 *
 * Invariant yang selalu dipenuhi:
 *   closing_balance = opening_balance + total_in - total_out + total_adjustment
 *
 * Regenerasi bersifat idempoten (upsert atas uq_sms_item_period), sehingga
 * menjalankan ulang bulan yang sama hanya memperbarui angkanya.
 *
 * Agregat dibaca lewat DB::table, bukan model Eloquent: query set-based dengan
 * kolom alias (total_in, dst) dan DISTINCT ON adalah bentuk yang paling tepat
 * dikerjakan query builder, dan menghindari properti model tak terdefinisi.
 */
class StockSnapshotService
{
    private const LEDGER = 'inventory_transactions';

    /**
     * Membuat / memperbarui snapshot seluruh item untuk satu periode bulan.
     *
     * @return int jumlah baris snapshot yang ditulis
     */
    public function generateForPeriod(int $year, int $month): int
    {
        [$start, $next] = $this->periodBounds($year, $month);

        $opening = $this->balancesAsOf($start);   // saldo sebelum awal bulan
        $closing = $this->balancesAsOf($next);    // saldo sebelum awal bulan berikutnya
        $movements = $this->movementsWithin($start, $next);

        $generatedAt = now();
        $written = 0;

        // Snapshot bersifat rapat: satu baris per item per bulan, termasuk item
        // tanpa pergerakan (opening = closing). Ini membuat laporan R1 berupa grid
        // item x bulan mudah dirender tanpa lubang. withTrashed() menyertakan item
        // yang kelak dinonaktifkan agar saldo historisnya tetap terekonsiliasi.
        Item::withTrashed()->orderBy('id')->chunkById(1000, function ($items) use (
            $opening, $closing, $movements, $year, $month, $generatedAt, &$written
        ): void {
            $rows = [];

            foreach ($items as $item) {
                $open = $opening[$item->id] ?? 0;
                $close = $closing[$item->id] ?? $open;
                $mv = $movements[$item->id] ?? ['in' => 0, 'out' => 0, 'adjustment' => 0];

                $rows[] = [
                    'item_id' => $item->id,
                    'period_year' => $year,
                    'period_month' => $month,
                    'opening_balance' => $open,
                    'total_in' => $mv['in'],
                    'total_out' => $mv['out'],
                    'total_adjustment' => $mv['adjustment'],
                    'closing_balance' => $close,
                    'generated_at' => $generatedAt,
                ];
            }

            if ($rows !== []) {
                StockMonthlySnapshot::upsert(
                    $rows,
                    ['item_id', 'period_year', 'period_month'],
                    ['opening_balance', 'total_in', 'total_out', 'total_adjustment', 'closing_balance', 'generated_at'],
                );
                $written += count($rows);
            }
        });

        return $written;
    }

    /**
     * Mengisi snapshot dari bulan transaksi ledger paling awal hingga bulan LENGKAP
     * terakhir (bulan berjalan tidak diikutkan karena belum selesai).
     *
     * @return list<array{year: int, month: int, rows: int}>
     */
    public function backfill(): array
    {
        $earliest = DB::table(self::LEDGER)->min('transaction_date');

        if ($earliest === null) {
            return [];
        }

        // min() mengembalikan timestamptz dalam zona sesi PostgreSQL (bisa bukan
        // UTC). Dinormalkan ke zona aplikasi lebih dulu agar bulan yang dihitung
        // sama dengan batas bulan pada generateForPeriod (yang memakai zona app).
        $appTz = config('app.timezone') ?: 'UTC';
        \assert(is_string($appTz));

        $cursor = CarbonImmutable::parse((string) $earliest)->setTimezone($appTz)->startOfMonth();
        $currentMonth = CarbonImmutable::now($appTz)->startOfMonth();

        // Dibandingkan sebagai indeks bulan kalender (year*12+month), bukan sebagai
        // instant. Perbandingan instant sempat memasukkan bulan berjalan karena
        // selisih zona waktu menggeser awal bulan beberapa jam.
        $currentIndex = $currentMonth->year * 12 + $currentMonth->month;

        $result = [];

        while ($cursor->year * 12 + $cursor->month < $currentIndex) {
            $rows = $this->generateForPeriod($cursor->year, $cursor->month);
            $result[] = ['year' => $cursor->year, 'month' => $cursor->month, 'rows' => $rows];
            $cursor = $cursor->addMonth();
        }

        return $result;
    }

    /**
     * Batas periode sebagai interval setengah terbuka [awal bulan, awal bulan berikutnya).
     *
     * Interval setengah terbuka dipilih ketimbang [awal, akhir] agar transaksi pada
     * mikrodetik terakhir bulan (23:59:59.xxxxxx) tidak lolos dari perbandingan —
     * jebakan yang tidak akan terlihat sampai ada transaksi tepat di batas bulan.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodBounds(int $year, int $month): array
    {
        /** @var CarbonImmutable $start */
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0);

        return [$start, $start->addMonth()];
    }

    /**
     * Saldo (quantity_after transaksi terakhir) tiap item TEPAT SEBELUM $moment.
     *
     * DISTINCT ON (PostgreSQL) mengambil satu baris terbaru per item dalam sekali
     * query, apa pun jumlah itemnya — jauh lebih murah daripada satu query per item.
     * transaction_date lalu id sebagai pengurut memastikan baris "terakhir" tepat
     * meski beberapa transaksi berbagi timestamp yang sama.
     *
     * @return array<int, int> item_id => saldo
     */
    private function balancesAsOf(CarbonImmutable $moment): array
    {
        $rows = DB::table(self::LEDGER)
            ->select('item_id', 'quantity_after')
            ->where('transaction_date', '<', $moment)
            ->orderBy('item_id')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->distinct('item_id')
            ->get();

        /** @var array<int, int> $balances */
        $balances = [];

        foreach ($rows as $row) {
            $balances[(int) $row->item_id] = (int) $row->quantity_after;
        }

        return $balances;
    }

    /**
     * Total pergerakan tiap item dalam [$start, $next).
     *
     * @return array<int, array{in: int, out: int, adjustment: int}>
     */
    private function movementsWithin(CarbonImmutable $start, CarbonImmutable $next): array
    {
        $rows = DB::table(self::LEDGER)
            ->select('item_id')
            ->selectRaw('COALESCE(SUM(quantity) FILTER (WHERE transaction_type = ?), 0) AS total_in', [TransactionType::In->value])
            ->selectRaw('COALESCE(SUM(quantity) FILTER (WHERE transaction_type = ?), 0) AS total_out', [TransactionType::Out->value])
            ->selectRaw('COALESCE(SUM(quantity_after - quantity_before) FILTER (WHERE transaction_type = ?), 0) AS total_adjustment', [TransactionType::Adjustment->value])
            ->where('transaction_date', '>=', $start)
            ->where('transaction_date', '<', $next)
            ->groupBy('item_id')
            ->get();

        /** @var array<int, array{in: int, out: int, adjustment: int}> $movements */
        $movements = [];

        foreach ($rows as $row) {
            $movements[(int) $row->item_id] = [
                'in' => (int) $row->total_in,
                'out' => (int) $row->total_out,
                'adjustment' => (int) $row->total_adjustment,
            ];
        }

        return $movements;
    }
}
