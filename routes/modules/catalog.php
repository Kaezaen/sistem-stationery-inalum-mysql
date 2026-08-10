<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Route Modul: catalog
|--------------------------------------------------------------------------
|
| Dimuat oleh App\Modules\Catalog\CatalogServiceProvider dengan middleware
| ['web','auth'].
|
*/

use App\Modules\Catalog\Http\Controllers\ItemController;
use App\Modules\Catalog\Http\Controllers\ItemImportController;
use App\Modules\Catalog\Http\Controllers\ItemSearchController;
use Illuminate\Support\Facades\Route;

// Didaftarkan SEBELUM resource agar 'items/import' tidak tertangkap oleh
// route parameter 'items/{item}'.
Route::get('items/import/template', [ItemImportController::class, 'template'])->name('items.import.template');
Route::get('items/import', [ItemImportController::class, 'create'])->name('items.import.create');
Route::post('items/import', [ItemImportController::class, 'store'])->name('items.import.store');

// Dipakai pemilih item pada form pembelian dan (Fase 5) form request.
Route::get('items/search', ItemSearchController::class)->name('items.search');

Route::resource('items', ItemController::class)->except(['show']);
