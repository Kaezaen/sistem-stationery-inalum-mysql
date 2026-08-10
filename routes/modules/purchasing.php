<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Route Modul: purchasing
|--------------------------------------------------------------------------
|
| Dimuat oleh App\Modules\Purchasing\PurchasingServiceProvider dengan middleware
| ['web','auth'].
|
*/

use App\Modules\Purchasing\Http\Controllers\PurchaseController;
use App\Modules\Purchasing\Http\Controllers\PurchaseVerificationController;
use Illuminate\Support\Facades\Route;

// Didaftarkan SEBELUM resource agar tidak tertangkap 'purchases/{purchase}'.
Route::get('purchases/verify', [PurchaseVerificationController::class, 'index'])
    ->name('purchases.verify.index');
Route::get('purchases/verify/{purchase}', [PurchaseVerificationController::class, 'show'])
    ->name('purchases.verify.show');
Route::post('purchases/verify/{purchase}', [PurchaseVerificationController::class, 'verify'])
    ->name('purchases.verify.store');
Route::post('purchases/reject/{purchase}', [PurchaseVerificationController::class, 'reject'])
    ->name('purchases.verify.reject');

Route::post('purchases/{purchase}/submit', [PurchaseController::class, 'submit'])
    ->name('purchases.submit');

Route::resource('purchases', PurchaseController::class);
