<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Route Modul: reporting
|--------------------------------------------------------------------------
|
| Dimuat oleh App\Modules\Reporting\ReportingServiceProvider.
| Delapan laporan (R1–R8) menyusul pada Fase 7.
|
*/

use App\Modules\Reporting\Http\Controllers\DashboardController;
use App\Modules\Reporting\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

/*
| Delapan laporan (R1–R8). Otorisasi ditegakkan per action di ReportController;
| laporan bersifat baca saja sehingga cukup di tingkat permission.
*/
Route::prefix('reports')->name('reports.')->group(function (): void {
    // Sprint 11 — stok & pembelian
    Route::get('stock-by-month', [ReportController::class, 'stockByMonth'])->name('stock-by-month');
    Route::get('stock-by-year', [ReportController::class, 'stockByYear'])->name('stock-by-year');
    Route::get('purchasing', [ReportController::class, 'purchasing'])->name('purchasing');
    Route::get('need-to-buy', [ReportController::class, 'needToBuy'])->name('need-to-buy');

    // Sprint 12 — request
    Route::get('request-by-month', [ReportController::class, 'requestByMonth'])->name('request-by-month');
    Route::get('request-by-year', [ReportController::class, 'requestByYear'])->name('request-by-year');
    Route::get('request-by-account', [ReportController::class, 'requestByAccount'])->name('request-by-account');
    Route::get('request-by-item', [ReportController::class, 'requestByItem'])->name('request-by-item');
});
