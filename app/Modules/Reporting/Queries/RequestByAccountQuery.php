<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Queries;

use App\Modules\Reporting\Support\ReportFilters;
use App\Modules\Reporting\Support\ReportResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * R6 Request by Account — "Account" = Departemen/Seksi (keputusan D3).
 *
 * Mengelompokkan request menurut departemen requestor beserta kode akunnya, lalu
 * menjumlahkan kuantitas diminta vs diserahkan. Kolom account_code memang
 * disiapkan di departments sejak awal justru untuk laporan ini (§K7 roadmap).
 *
 * Berbasis request_date. $departmentIds membatasi lingkup untuk Pimpinan User.
 */
class RequestByAccountQuery
{
    /** @param  list<int>|null  $departmentIds */
    public function handle(ReportFilters $filters, ?array $departmentIds = null): ReportResult
    {
        $rows = DB::table('requests as r')
            ->join('departments as d', 'd.id', '=', 'r.department_id')
            ->leftJoin('request_items as ri', 'ri.request_id', '=', 'r.id')
            ->whereBetween('r.request_date', [$filters->from, $filters->until])
            ->when(
                $departmentIds !== null,
                fn (Builder $q): Builder => $q->whereIn('r.department_id', $departmentIds ?? []),
            )
            ->when(
                $filters->departmentId,
                fn (Builder $q): Builder => $q->where('r.department_id', $filters->departmentId),
            )
            ->groupBy('d.id', 'd.name', 'd.account_code')
            ->orderByDesc('request_count')
            ->orderBy('d.name')
            ->get([
                'd.name as department',
                'd.account_code',
                DB::raw('COUNT(DISTINCT r.id) as request_count'),
                DB::raw('COALESCE(SUM(ri.quantity_requested), 0) as qty_requested'),
                DB::raw('COALESCE(SUM(ri.quantity_actual), 0) as qty_actual'),
            ]);

        $mapped = $rows->map(fn (object $r): array => [
            'department' => $r->department,
            'account_code' => $r->account_code,
            'request_count' => (int) $r->request_count,
            'qty_requested' => (int) $r->qty_requested,
            'qty_actual' => (int) $r->qty_actual,
        ])->all();

        return new ReportResult(
            key: 'request-by-account',
            title: 'Laporan Request per Departemen',
            columns: [
                ['key' => 'department', 'label' => 'Departemen/Seksi'],
                ['key' => 'account_code', 'label' => 'Kode Akun'],
                ['key' => 'request_count', 'label' => 'Jumlah Request', 'align' => 'right', 'numeric' => true],
                ['key' => 'qty_requested', 'label' => 'Qty Diminta', 'align' => 'right', 'numeric' => true],
                ['key' => 'qty_actual', 'label' => 'Qty Diserahkan', 'align' => 'right', 'numeric' => true],
            ],
            rows: $mapped,
            filterSchema: ['period' => 'range', 'department' => true],
            filters: $filters->toArray(),
            meta: [
                ['label' => 'Jumlah Departemen', 'value' => count($mapped)],
                ['label' => 'Total Request', 'value' => (int) array_sum(array_column($mapped, 'request_count'))],
            ],
            subtitle: sprintf('%s s/d %s', $filters->from, $filters->until),
        );
    }
}
