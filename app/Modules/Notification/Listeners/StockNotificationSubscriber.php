<?php

declare(strict_types=1);

namespace App\Modules\Notification\Listeners;

use App\Modules\Identity\Enums\Role;
use App\Modules\Inventory\Events\StockFellBelowMinimum;
use App\Modules\Notification\Notifications\LowStockNotification;
use App\Modules\Notification\Support\RecipientResolver;
use Illuminate\Support\Facades\Notification;

/**
 * N11 — stok melintasi batas minimum → PIC Stationery + PIC Gudang.
 *
 * Mereka yang mengelola pengadaan; keduanya perlu tahu agar pembelian dapat
 * segera diajukan.
 */
class StockNotificationSubscriber
{
    public function __construct(private readonly RecipientResolver $resolver) {}

    public function handleBelowMinimum(StockFellBelowMinimum $event): void
    {
        $item = $event->item;

        $recipients = $this->resolver->withRole(Role::PicStationery)
            ->merge($this->resolver->withRole(Role::WarehouseOfficer))
            ->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new LowStockNotification(
            $item->item_code,
            $item->item_name,
            $item->stock_quantity,
            $item->min_stock,
            $item->id,
        ));
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            StockFellBelowMinimum::class => 'handleBelowMinimum',
        ];
    }
}
