<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Modules\Reporting\Support\ReportFilters;
use App\Modules\Reporting\Support\ReportResult;
use App\Modules\Requisition\Enums\RequestStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * R4 Request by Month & R5 Request by Year — pivot periode × status.
 *
 * Berbasis request_date (kolom date, bukan created_at — §10.1), sehingga tidak
 * terpengaruh zona waktu sesi database. Tiap sel berisi jumlah request pada
 * periode itu untuk status tertentu; kolom Total menutup baris.
 *
 * $departmentIds membatasi lingkup: Pimpinan User hanya melihat requestor
 * departemennya (matriks §5.1 ◐ "unit sendiri"); null = seluruh departemen.
 */
class RequestByPeriodQuery
{
    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** @param  list<int>|null  $departmentIds */
    public function byMonth(ReportFilters $filters, ?array $departmentIds = null): ReportResult
    {
        $counts = $this->baseCounts($departmentIds)
            ->whereRaw('YEAR(request_date) = ?', [$filters->year])
            ->selectRaw('MONTH(request_date) as bucket')
            ->addSelect('status')
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw('MONTH(request_date), status')
            ->get();

        // Baris rapat: seluruh 12 bulan tampil meski nol, agar terbaca sebagai kalender.
        $rows = [];
        foreach (self::MONTHS as $number => $name) {
            $rows[$number] = $this->emptyRow($name);
        }

        foreach ($counts as $row) {
            $this->fill($rows, (int) $row->bucket, (string) $row->status, (int) $row->total);
        }

        return new ReportResult(
            key: 'request-by-month',
            title: 'Laporan Request per Bulan',
            columns: $this->columns('Bulan'),
            rows: array_values($rows),
            filterSchema: ['period' => 'year', 'department' => true],
            filters: $filters->toArray(),
            meta: $this->grandTotal($rows),
            subtitle: sprintf('Tahun %04d', $filters->year),
        );
    }

    /** @param  list<int>|null  $departmentIds */
    public function byYear(ReportFilters $filters, ?array $departmentIds = null): ReportResult
    {
        $counts = $this->baseCounts($departmentIds)
            ->selectRaw('YEAR(request_date) as bucket')
            ->addSelect('status')
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw('YEAR(request_date), status')
            ->get();

        /** @var array<int, array<string, mixed>> $rows */
        $rows = [];
        foreach ($counts as $row) {
            $year = (int) $row->bucket;
            if (! isset($rows[$year])) {
                $rows[$year] = $this->emptyRow((string) $year);
            }
            $this->fill($rows, $year, (string) $row->status, (int) $row->total);
        }

        krsort($rows);   // tahun terbaru di atas

        return new ReportResult(
            key: 'request-by-year',
            title: 'Laporan Request per Tahun',
            columns: $this->columns('Tahun'),
            rows: array_values($rows),
            filterSchema: ['period' => null, 'department' => true],
            filters: $filters->toArray(),
            meta: $this->grandTotal($rows),
            subtitle: 'Seluruh tahun',
        );
    }

    /** @param  list<int>|null  $departmentIds */
    private function baseCounts(?array $departmentIds): Builder
    {
        return DB::table('requests')
            ->when($departmentIds !== null, fn (Builder $q): Builder => $q->whereIn('department_id', $departmentIds ?? []));
    }

    /**
     * Kolom pivot: periode + satu kolom per status + total.
     *
     * @return list<array{key: string, label: string, align?: string, numeric?: bool}>
     */
    private function columns(string $periodLabel): array
    {
        $columns = [['key' => 'period', 'label' => $periodLabel]];

        foreach (RequestStatus::cases() as $status) {
            $columns[] = ['key' => $status->value, 'label' => $status->label(), 'align' => 'right', 'numeric' => true];
        }

        $columns[] = ['key' => 'total', 'label' => 'Total', 'align' => 'right', 'numeric' => true];

        return $columns;
    }

    /** @return array<string, mixed> */
    private function emptyRow(string $period): array
    {
        $row = ['period' => $period, 'total' => 0];

        foreach (RequestStatus::cases() as $status) {
            $row[$status->value] = 0;
        }

        return $row;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function fill(array &$rows, int $bucket, string $status, int $count): void
    {
        if (! isset($rows[$bucket])) {
            return;
        }

        $rows[$bucket][$status] = $count;
        $rows[$bucket]['total'] = (int) $rows[$bucket]['total'] + $count;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{label: string, value: int}>
     */
    private function grandTotal(array $rows): array
    {
        $total = 0;
        foreach ($rows as $row) {
            $total += (int) $row['total'];
        }

        return [['label' => 'Total Request', 'value' => $total]];
    }
}
