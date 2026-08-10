<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Route Modul: audit
|--------------------------------------------------------------------------
|
| Halaman Audit Log (§8.2) — akses Administrator saja. Dimuat oleh
| AuditServiceProvider dengan middleware ['web','auth'].
|
*/

use App\Modules\Audit\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
