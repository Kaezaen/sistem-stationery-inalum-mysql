<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Reporting\Queries\NeedToBuyQuery;
use App\Modules\Reporting\Queries\PurchasingReportQuery;
use App\Modules\Reporting\Queries\RequestByAccountQuery;
use App\Modules\Reporting\Queries\RequestByItemQuery;
use App\Modules\Reporting\Queries\RequestByPeriodQuery;
use App\Modules\Reporting\Queries\StockByPeriodQuery;
use App\Modules\Reporting\Services\ReportExportService;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Reporting\Support\ReportFilters;
use App\Modules\Reporting\Support\ReportResult;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Kedelapan laporan (R1–R8) memakai satu halaman React generik (Reports/Show):
 * tiap action hanya menegakkan permission, menyusun filter, memanggil Query
 * Object-nya, lalu merender hasil tabular yang seragam.
 *
 * Setiap action WAJIB authorize lebih dulu — pemeriksaan permission di React hanya
 * menyembunyikan menu (§5.2). Laporan bersifat baca saja, jadi otorisasinya cukup
 * di tingkat permission; tidak ada Policy per-dokumen di sini.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ReportExportService $exporter,
    ) {}

    /** R1 — Stock by Month. */
    public function stockByMonth(Request $request, StockByPeriodQuery $query): Response|BinaryFileResponse
    {
        $this->authorizePermission($request, Permission::ReportStockView->value);

        return $this->respond($request, $query->byMonth(ReportFilters::fromRequest($request)));
    }

    /** R2 — Stock by Year. */
    public function stockByYear(Request $request, StockByPeriodQuery $query): Response|BinaryFileResponse
    {
        $this->authorizePermission($request, Permission::ReportStockView->value);

        return $this->respond($request, $query->byYear(ReportFilters::fromRequest($request)));
    }

    /** R3 — Purchasing (hanya VERIFIED). */
    public function purchasing(Request $request, PurchasingReportQuery $query): Response|BinaryFileResponse
    {
        $this->authorizePermission($request, Permission::ReportPurchasingView->value);

        return $this->respond($request, $query->handle(ReportFilters::fromRequest($request)));
    }

    /** R8 — Need to Buy. */
    public function needToBuy(Request $request, NeedToBuyQuery $query): Response|BinaryFileResponse
    {
        $this->authorizePermission($request, Permission::ReportNeedToBuyView->value);

        return $this->respond($request, $query->handle(ReportFilters::fromRequest($request)));
    }

    /** R4 — Request by Month. */
    public function requestByMonth(Request $request, RequestByPeriodQuery $query): Response|BinaryFileResponse
    {
        $this->authorizePermission($request, Permission::ReportRequestView->value);

        return $this->respond($request, $query->byMonth(
            ReportFilters::fromRequest($request),
            $this->requestScope($request),
        ));
    }

    /** R5 — Request by Year. */
    public function requestByYear(Request $request, RequestByPeriodQuery $query): Response|BinaryFileResponse
    {
        $this->authorizePermission($request, Permission::ReportRequestView->value);

        return $this->respond($request, $query->byYear(
            ReportFilters::fromRequest($request),
            $this->requestScope($request),
        ));
    }

    /** R6 — Request by Account (Departemen, keputusan D3). */
    public function requestByAccount(Request $request, RequestByAccountQuery $query): Response|BinaryFileResponse
    {
        $this->authorizePermission($request, Permission::ReportRequestView->value);

        return $this->respond($request, $query->handle(
            ReportFilters::fromRequest($request),
            $this->requestScope($request),
        ));
    }

    /** R7 — Request by Item. */
    public function requestByItem(Request $request, RequestByItemQuery $query): Response|BinaryFileResponse
    {
        $this->authorizePermission($request, Permission::ReportRequestView->value);

        return $this->respond($request, $query->handle(
            ReportFilters::fromRequest($request),
            $this->requestScope($request),
        ));
    }

    /**
     * Departemen yang boleh dilihat penglihat pada laporan request, atau null = semua.
     *
     * Menegakkan ◐ "unit sendiri" pada matriks §5.1: Pimpinan User hanya melihat
     * requestor departemennya. Diperoleh dari ReportService agar aturannya satu tempat.
     *
     * @return list<int>|null
     */
    private function requestScope(Request $request): ?array
    {
        $user = $request->user();

        return $user !== null ? $this->reports->visibleDepartmentIds($user) : [];
    }

    /**
     * Mengirim hasil laporan: unduhan .xlsx bila diminta & berwenang, selain itu
     * halaman generik. Export digerbangi permission report.export — parameter
     * ?export tidak bisa dipakai melangkahi kewenangan.
     */
    private function respond(Request $request, ReportResult $result): Response|BinaryFileResponse
    {
        if ($request->query('export') === 'xlsx'
            && (bool) $request->user()?->can(Permission::ReportExport->value)) {
            return $this->exporter->xlsx($result);
        }

        return Inertia::render('Reports/Show', [
            'report' => $result->toArray(),
            'options' => [
                'categories' => $this->reports->categoryOptions(),
                'departments' => $this->reports->departmentOptions(),
            ],
            'can' => [
                'export' => (bool) $request->user()?->can(Permission::ReportExport->value),
            ],
        ]);
    }
}
