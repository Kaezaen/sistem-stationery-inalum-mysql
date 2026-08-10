<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Route Modul: fulfillment
|--------------------------------------------------------------------------
|
| Dimuat oleh App\Modules\Fulfillment\FulfillmentServiceProvider dengan
| middleware ['web','auth'].
|
*/

use App\Modules\Fulfillment\Http\Controllers\HandoverController;
use Illuminate\Support\Facades\Route;

Route::get('handover', [HandoverController::class, 'index'])->name('handover.index');
Route::get('handover/{request}', [HandoverController::class, 'show'])->name('handover.show');
Route::post('handover/{request}', [HandoverController::class, 'store'])->name('handover.store');
Route::get('handover/{request}/receipt', [HandoverController::class, 'receipt'])->name('handover.receipt');
