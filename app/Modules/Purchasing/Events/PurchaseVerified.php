<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Events;

use App\Modules\Purchasing\Models\Purchase;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dipancarkan sejak Fase 4; listener notifikasinya menyusul di Fase 8.
 *
 * Event dipancarkan lebih dulu agar kode workflow yang sudah teruji tidak perlu
 * disentuh ulang saat notifikasi diimplementasikan.
 */
class PurchaseVerified
{
    use Dispatchable;

    public function __construct(public readonly Purchase $purchase) {}
}
