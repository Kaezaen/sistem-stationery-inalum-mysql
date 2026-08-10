<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Route Modul: inventory
|--------------------------------------------------------------------------
|
| Dimuat oleh App\Modules\Inventory\InventoryServiceProvider dengan middleware
| ['web','auth'].
|
*/

use App\Modules\Inventory\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::get('inventory/{item}', [InventoryController::class, 'show'])->name('inventory.show');
