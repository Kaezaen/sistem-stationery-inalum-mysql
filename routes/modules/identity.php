<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Route Modul: identity
|--------------------------------------------------------------------------
|
| Dimuat oleh App\Modules\Identity\IdentityServiceProvider dengan middleware
| ['web','auth']. Route autentikasi (tamu) ada di routes/auth.php karena tidak
| boleh berada di balik middleware 'auth'.
|
*/

use App\Modules\Identity\Http\Controllers\DepartmentController;
use App\Modules\Identity\Http\Controllers\RoleController;
use App\Modules\Identity\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    // Didaftarkan SEBELUM resource agar 'users/hierarchy' tidak tertangkap
    // oleh route parameter 'users/{user}'.
    Route::get('users/hierarchy', [UserController::class, 'hierarchy'])
        ->name('users.hierarchy');

    Route::resource('users', UserController::class)->except(['show']);
    Route::resource('departments', DepartmentController::class)->except(['show']);

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
});
