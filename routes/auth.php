<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Route Autentikasi
|--------------------------------------------------------------------------
|
| Terpisah dari routes/modules/identity.php karena route ini harus dapat diakses
| TANPA login. Dimuat oleh bootstrap/app.php di dalam grup middleware 'web'.
|
| Menggunakan Laravel Built-in Authentication sesuai ketentuan tech stack —
| bukan paket starter kit.
|
*/

use App\Modules\Identity\Http\Controllers\AuthenticatedSessionController;
use App\Modules\Identity\Http\Controllers\NewPasswordController;
use App\Modules\Identity\Http\Controllers\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
