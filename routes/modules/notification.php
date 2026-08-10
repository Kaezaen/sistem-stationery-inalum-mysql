<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Route Modul: notification
|--------------------------------------------------------------------------
|
| Inbox notifikasi in-app (fitur 6). Dimuat oleh NotificationServiceProvider
| dengan middleware ['web','auth'] — setiap pengguna hanya melihat miliknya.
|
*/

use App\Modules\Notification\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
