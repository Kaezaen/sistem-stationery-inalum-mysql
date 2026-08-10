<?php

declare(strict_types=1);

namespace App\Modules\Notification\Notifications;

use App\Modules\Notification\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pengingat approval tertunda (N12) — SLA, kanal email.
 *
 * Dikirim command terjadwal approvals:remind untuk dokumen yang menunggu
 * persetujuan lebih lama dari ambang hari.
 */
class PendingApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $reference,
        private readonly string $url,
        private readonly int $days,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return NotificationType::ApprovalReminder->channels();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(NotificationType::ApprovalReminder->title().' — '.$this->reference)
            ->greeting('Halo,')
            ->line("{$this->reference} telah menunggu persetujuan Anda selama {$this->days} hari.")
            ->action('Tindak Lanjuti', url($this->url))
            ->line('Mohon segera ditindaklanjuti agar alur tidak tertahan.');
    }

    /**
     * Disediakan untuk kanal database bila kelak diaktifkan; N12 saat ini email saja.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'code' => NotificationType::ApprovalReminder->value,
            'title' => NotificationType::ApprovalReminder->title(),
            'message' => "{$this->reference} menunggu persetujuan Anda selama {$this->days} hari.",
            'reference' => $this->reference,
            'url' => $this->url,
        ];
    }
}
