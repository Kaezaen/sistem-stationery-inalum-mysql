<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Events;

use App\Modules\Requisition\Models\Request;
use Illuminate\Foundation\Events\Dispatchable;

/** Notifikasi N8 — barang diserahkan. Listener menyusul Fase 8. */
class RequestCompleted
{
    use Dispatchable;

    public function __construct(public readonly Request $request) {}
}
