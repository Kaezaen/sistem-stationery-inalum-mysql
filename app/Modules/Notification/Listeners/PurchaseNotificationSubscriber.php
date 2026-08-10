<?php

declare(strict_types=1);

namespace App\Modules\Notification\Listeners;

use App\Modules\Identity\Enums\Role;
use App\Modules\Notification\Enums\NotificationType;
use App\Modules\Notification\Notifications\PurchaseStatusNotification;
use App\Modules\Notification\Support\RecipientResolver;
use App\Modules\Purchasing\Events\PurchaseRejected;
use App\Modules\Purchasing\Events\PurchaseSubmitted;
use App\Modules\Purchasing\Events\PurchaseVerified;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Menerjemahkan event alur pembelian menjadi notifikasi (N9–N10).
 *
 * N9: pembelian diinput → PIC Stationery. N10: hasil verifikasi/penolakan →
 * kembali ke PIC Gudang pembuatnya.
 */
class PurchaseNotificationSubscriber
{
    public function __construct(private readonly RecipientResolver $resolver) {}

    public function handleSubmitted(PurchaseSubmitted $event): void
    {
        $purchase = $event->purchase;

        $this->send($this->resolver->withRole(Role::PicStationery), new PurchaseStatusNotification(
            NotificationType::PurchaseSubmitted,
            $purchase->purchase_number,
            $purchase->id,
        ));
    }

    public function handleVerified(PurchaseVerified $event): void
    {
        $purchase = $event->purchase;

        $this->send($this->resolver->only($purchase->creator), new PurchaseStatusNotification(
            NotificationType::PurchaseDecided,
            $purchase->purchase_number,
            $purchase->id,
            verified: true,
        ));
    }

    public function handleRejected(PurchaseRejected $event): void
    {
        $purchase = $event->purchase;

        $this->send($this->resolver->only($purchase->creator), new PurchaseStatusNotification(
            NotificationType::PurchaseDecided,
            $purchase->purchase_number,
            $purchase->id,
            verified: false,
            reason: $event->reason,
        ));
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            PurchaseSubmitted::class => 'handleSubmitted',
            PurchaseVerified::class => 'handleVerified',
            PurchaseRejected::class => 'handleRejected',
        ];
    }

    /**
     * @param  Collection<int, \App\Modules\Identity\Models\User>  $recipients
     */
    private function send(Collection $recipients, PurchaseStatusNotification $notification): void
    {
        $recipients = $recipients->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }
}
