<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Events;

use App\Modules\Catalog\Models\Item;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dipancarkan saat saldo stok sebuah item MELINTASI batas minimum dari atas ke
 * bawah (before ≥ min, after < min) — pemicu notifikasi N11.
 *
 * Sengaja hanya pada saat MELINTASI, bukan setiap kali stok berada di bawah min,
 * agar tidak membanjiri PIC dengan peringatan berulang atas item yang sama.
 *
 * Dipancarkan dari dalam modul Inventory (StockService), konsisten dengan pola
 * event lain: modul lain cukup menambahkan listener tanpa menyentuh StockService.
 */
class StockFellBelowMinimum
{
    use Dispatchable;

    public function __construct(public readonly Item $item) {}
}
