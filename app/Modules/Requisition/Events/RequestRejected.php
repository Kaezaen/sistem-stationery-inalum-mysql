<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Events;

use App\Modules\Requisition\Models\Request;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Notifikasi N3/N5/N7.
 *
 * Penerimanya BERBEDA per level — tolak L1 & L2 ke requester, tolak L3 ke PIC
 * Stationery. Level ikut dibawa agar listener Fase 8 dapat mengarahkannya.
 */
class RequestRejected
{
    use Dispatchable;

    public function __construct(
        public readonly Request $request,
        public readonly int $level,
        public readonly string $reason,
    ) {}
}
