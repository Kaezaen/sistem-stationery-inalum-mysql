<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Services;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Models\User;
use App\Modules\Requisition\Enums\RequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Data dashboard monitoring — fitur 5 blueprint ("Pelaporan/Monitoring").
 *
 * Payload disesuaikan dengan kewenangan penglihat: dashboard adalah halaman depan
 * SEMUA pengguna (termasuk requester biasa), jadi ia tidak boleh membocorkan data
 * yang tidak boleh dilihat perannya. Requester hanya melihat ringkasan request
 * miliknya; statistik organisasi dan stok menyusul sesuai permission.
 */
class DashboardService
{
    private const SHORT_MONTHS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    public function __construct(private readonly ReportService $reports) {}

    /** @return array<string, mixed> */
    public function forUser(User $user): array
    {
        return [
            'myRequests' => $this->myRequests($user),
            'orgRequests' => $this->canViewOrgRequests($user) ? $this->orgRequests($user) : null,
            'stock' => $this->canViewStock($user) ? $this->stock() : null,
        ];
    }

    /**
     * Ringkasan request milik pengguna sendiri — selalu tampil.
     *
     * @return array{total: int, byStatus: list<array{label: string, tone: string, count: int}>}
     */
    private function myRequests(User $user): array
    {
        $counts = DB::table('requests')
            ->where('requester_id', $user->id)
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'total' => (int) $counts->sum(),
            'byStatus' => $this->breakdownFrom($counts),
        ];
    }

    /**
     * Statistik request seluruh organisasi (dibatasi lingkup Pimpinan User).
     *
     * @return array{byStatus: list<array{label: string, tone: string, count: int}>, trend: list<array{label: string, count: int}>, topItems: list<array{item_name: string, qty: int}>}
     */
    private function orgRequests(User $user): array
    {
        $departmentIds = $this->reports->visibleDepartmentIds($user);

        $scope = fn (): Builder => DB::table('requests')
            ->when($departmentIds !== null, fn (Builder $q): Builder => $q->whereIn('department_id', $departmentIds ?? []));

        $counts = $scope()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'byStatus' => $this->breakdownFrom($counts),
            'trend' => $this->trend($departmentIds),
            'topItems' => $this->topItems($departmentIds),
        ];
    }

    /**
     * Tren jumlah request 6 bulan terakhir (berbasis request_date).
     *
     * @param  list<int>|null  $departmentIds
     * @return list<array{label: string, count: int}>
     */
    private function trend(?array $departmentIds): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(5);

        $counts = DB::table('requests')
            ->when($departmentIds !== null, fn (Builder $q): Builder => $q->whereIn('department_id', $departmentIds ?? []))
            ->where('request_date', '>=', $start->toDateString())
            ->selectRaw("DATE_FORMAT(request_date, '%Y-%m') as ym")
            ->selectRaw('COUNT(*) as c')
            ->groupByRaw("DATE_FORMAT(request_date, '%Y-%m')")
            ->pluck('c', 'ym');

        $trend = [];
        for ($cursor = $start, $i = 0; $i < 6; $cursor = $cursor->addMonth(), $i++) {
            $key = $cursor->format('Y-m');
            $trend[] = [
                'label' => self::SHORT_MONTHS[$cursor->month].' '.$cursor->year,
                'count' => (int) ($counts[$key] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * Lima item paling banyak diminta dalam 90 hari terakhir.
     *
     * @param  list<int>|null  $departmentIds
     * @return list<array{item_name: string, qty: int}>
     */
    private function topItems(?array $departmentIds): array
    {
        $since = CarbonImmutable::now()->subDays(90)->toDateString();

        return DB::table('request_items as ri')
            ->join('requests as r', 'r.id', '=', 'ri.request_id')
            ->join('items as i', 'i.id', '=', 'ri.item_id')
            ->when($departmentIds !== null, fn (Builder $q): Builder => $q->whereIn('r.department_id', $departmentIds ?? []))
            ->where('r.request_date', '>=', $since)
            ->groupBy('i.id', 'i.item_name')
            ->orderByDesc(DB::raw('SUM(ri.quantity_requested)'))
            ->limit(5)
            ->get(['i.item_name', DB::raw('SUM(ri.quantity_requested) as qty')])
            ->map(fn (object $r): array => ['item_name' => (string) $r->item_name, 'qty' => (int) $r->qty])
            ->all();
    }

    /**
     * Posisi stok: jumlah item di bawah minimum + lima paling mendesak.
     *
     * @return array{underStockCount: int, needToBuy: list<array{item_name: string, stock: int, suggested: int}>}
     */
    private function stock(): array
    {
        $underStock = fn (): Builder => DB::table('items')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<', 'min_stock');

        $needToBuy = $underStock()
            ->orderByRaw('(min_stock - stock_quantity) DESC')
            ->limit(5)
            ->get(['item_name', 'stock_quantity', DB::raw('(max_stock - stock_quantity) as suggested')])
            ->map(fn (object $r): array => [
                'item_name' => (string) $r->item_name,
                'stock' => (int) $r->stock_quantity,
                'suggested' => (int) $r->suggested,
            ])->all();

        return [
            'underStockCount' => $underStock()->count(),
            'needToBuy' => $needToBuy,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $counts
     * @return list<array{label: string, tone: string, count: int}>
     */
    private function breakdownFrom($counts): array
    {
        $out = [];

        foreach (RequestStatus::cases() as $status) {
            $count = (int) ($counts[$status->value] ?? 0);

            if ($count > 0) {
                $out[] = ['label' => $status->label(), 'tone' => $status->tone(), 'count' => $count];
            }
        }

        return $out;
    }

    private function canViewOrgRequests(User $user): bool
    {
        return $user->can(Permission::ReportRequestView->value);
    }

    private function canViewStock(User $user): bool
    {
        return $user->can(Permission::InventoryView->value)
            || $user->can(Permission::ReportStockView->value)
            || $user->can(Permission::ReportNeedToBuyView->value);
    }
}
