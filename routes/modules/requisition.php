<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Route Modul: requisition
|--------------------------------------------------------------------------
|
| Dimuat oleh App\Modules\Requisition\RequisitionServiceProvider dengan
| middleware ['web','auth'].
|
*/

use App\Modules\Requisition\Http\Controllers\RequestApprovalController;
use App\Modules\Requisition\Http\Controllers\RequestController;
use Illuminate\Support\Facades\Route;

// Antrian verifikasi didaftarkan SEBELUM resource agar tidak tertangkap
// oleh route parameter 'requests/{request}'.
Route::get('requests/verify', [RequestApprovalController::class, 'index'])
    ->name('requests.verify.index');
Route::get('requests/verify/{request}', [RequestApprovalController::class, 'show'])
    ->name('requests.verify.show');
Route::post('requests/verify/{request}/approve', [RequestApprovalController::class, 'approve'])
    ->name('requests.verify.approve');
Route::post('requests/verify/{request}/reject', [RequestApprovalController::class, 'reject'])
    ->name('requests.verify.reject');

Route::get('requests/{request}/revise', [RequestController::class, 'revise'])
    ->name('requests.revise');
Route::post('requests/{request}/revise', [RequestController::class, 'storeRevision'])
    ->name('requests.revise.store');
Route::post('requests/{request}/submit', [RequestController::class, 'submit'])
    ->name('requests.submit');
Route::post('requests/{request}/cancel', [RequestController::class, 'cancel'])
    ->name('requests.cancel');

Route::resource('requests', RequestController::class)->except(['edit', 'update', 'destroy']);
