<?php

declare(strict_types=1);

namespace App\Modules\Notification\Listeners;

use App\Modules\Identity\Enums\Role;
use App\Modules\Notification\Enums\NotificationType;
use App\Modules\Notification\Notifications\RequestStatusNotification;
use App\Modules\Notification\Support\RecipientResolver;
use App\Modules\Requisition\Events\RequestApproved;
use App\Modules\Requisition\Events\RequestCompleted;
use App\Modules\Requisition\Events\RequestRejected;
use App\Modules\Requisition\Events\RequestSubmitted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Menerjemahkan event alur request menjadi notifikasi (N1–N8, matriks §9.1).
 *
 * Subscriber berjalan sinkron saat event dipancarkan (di dalam transaksi), tetapi
 * hanya me-resolve penerima lalu memanggil notify(); pengiriman sesungguhnya
 * di-queue dan ditunda hingga commit oleh notifikasinya ($afterCommit).
 *
 * Titik balik penolakan yang berbeda per level (temuan blueprint) tercermin di
 * handleRejected: L1/L2 ke requester, L3 ke PIC Stationery.
 */
class RequestNotificationSubscriber
{
    public function __construct(private readonly RecipientResolver $resolver) {}

    public function handleSubmitted(RequestSubmitted $event): void
    {
        $request = $event->request;
        $requester = $request->requester;

        $recipients = $requester !== null ? $this->resolver->supervisorOf($requester) : collect();

        $this->send($recipients, new RequestStatusNotification(
            NotificationType::RequestSubmitted,
            $request->request_number,
            $request->id,
        ));
    }

    public function handleApproved(RequestApproved $event): void
    {
        $request = $event->request;

        [$type, $recipients] = match ($event->level) {
            1 => [NotificationType::RequestApprovedL1, $this->resolver->withRole(Role::PicStationery)],
            2 => [NotificationType::RequestApprovedL2, $this->resolver->withRole(Role::SgaManager)],
            3 => [
                NotificationType::RequestApprovedL3,
                $this->resolver->withRole(Role::WarehouseOfficer)->merge($this->resolver->only($request->requester)),
            ],
            default => [null, collect()],
        };

        if ($type === null) {
            return;
        }

        $this->send($recipients, new RequestStatusNotification(
            $type,
            $request->request_number,
            $request->id,
        ));
    }

    public function handleRejected(RequestRejected $event): void
    {
        $request = $event->request;

        [$type, $recipients] = match ($event->level) {
            1 => [NotificationType::RequestRejectedL1, $this->resolver->only($request->requester)],
            2 => [NotificationType::RequestRejectedL2, $this->resolver->only($request->requester)],
            3 => [NotificationType::RequestRejectedL3, $this->resolver->withRole(Role::PicStationery)],
            default => [null, collect()],
        };

        if ($type === null) {
            return;
        }

        $this->send($recipients, new RequestStatusNotification(
            $type,
            $request->request_number,
            $request->id,
            $event->reason,
        ));
    }

    public function handleCompleted(RequestCompleted $event): void
    {
        $request = $event->request;

        $this->send($this->resolver->only($request->requester), new RequestStatusNotification(
            NotificationType::RequestCompleted,
            $request->request_number,
            $request->id,
        ));
    }

    /** @return array<class-string, string> */
    public function subscribe(): array
    {
        return [
            RequestSubmitted::class => 'handleSubmitted',
            RequestApproved::class => 'handleApproved',
            RequestRejected::class => 'handleRejected',
            RequestCompleted::class => 'handleCompleted',
        ];
    }

    /**
     * @param  Collection<int, \App\Modules\Identity\Models\User>  $recipients
     */
    private function send(Collection $recipients, RequestStatusNotification $notification): void
    {
        $recipients = $recipients->unique('id');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, $notification);
    }
}
