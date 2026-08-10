<?php

declare(strict_types=1);

namespace App\Modules\Audit\Observers;

use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Requisition\Models\RequestItem;

/**
 * Mengaudit perubahan quantity_actual (§8.2) — "titik paling rawan sengketa":
 * jumlah yang benar-benar diserahkan bisa berbeda dari yang disetujui.
 */
class RequestItemObserver
{
    public function __construct(private readonly AuditLogger $logger) {}

    public function updated(RequestItem $item): void
    {
        if (! $item->wasChanged('quantity_actual')) {
            return;
        }

        $this->logger->record(
            'quantity_actual_changed',
            $item,
            ['quantity_actual' => $item->getOriginal('quantity_actual')],
            ['quantity_actual' => $item->quantity_actual],
        );
    }
}
