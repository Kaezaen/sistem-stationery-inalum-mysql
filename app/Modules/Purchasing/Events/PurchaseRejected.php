<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Events;

use App\Modules\Purchasing\Models\Purchase;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dipancarkan sejak Fase 4; listener notifikasinya menyusul di Fase 8 (N10).
 */
class PurchaseRejected
{
    use Dispatchable;

    public function __construct(
        public readonly Purchase $purchase,
        public readonly string $reason,
    ) {}
}
