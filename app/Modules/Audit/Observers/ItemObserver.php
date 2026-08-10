<?php

declare(strict_types=1);

namespace App\Modules\Audit\Observers;

use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Catalog\Models\Item;

/**
 * Mengaudit perubahan master item (§8.2), terutama min_stock/max_stock yang
 * memengaruhi laporan Need to Buy.
 *
 * Kolom saldo (stock_quantity/reserved_quantity) SENGAJA dikecualikan: mutasinya
 * sudah tercatat lengkap di ledger inventory_transactions (§8.2), sehingga
 * mengauditnya lagi di sini hanya menduplikasi.
 */
class ItemObserver
{
    /** Kolom yang tidak diaudit di sini. */
    private const IGNORED = ['stock_quantity', 'reserved_quantity', 'created_at', 'updated_at'];

    public function __construct(private readonly AuditLogger $logger) {}

    public function created(Item $item): void
    {
        $this->logger->record('created', $item, null, $this->auditable($item->getAttributes()));
    }

    public function updated(Item $item): void
    {
        $changes = $this->auditable($item->getChanges());

        if ($changes === []) {
            return; // hanya kolom yang dikecualikan yang berubah
        }

        $old = [];
        foreach (array_keys($changes) as $key) {
            $old[$key] = $item->getOriginal($key);
        }

        $this->logger->record('updated', $item, $old, $changes);
    }

    public function deleted(Item $item): void
    {
        $this->logger->record('deleted', $item, ['item_code' => $item->item_code], null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function auditable(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::IGNORED));
    }
}
