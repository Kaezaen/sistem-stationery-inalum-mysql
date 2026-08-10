<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Events;

use App\Modules\Requisition\Models\Request;
use Illuminate\Foundation\Events\Dispatchable;

/** Notifikasi N2/N4/N6 — penerimanya ditentukan level. Listener menyusul Fase 8. */
class RequestApproved
{
    use Dispatchable;

    public function __construct(
        public readonly Request $request,
        public readonly int $level,
    ) {}
}
