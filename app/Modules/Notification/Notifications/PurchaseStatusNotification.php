<?php

declare(strict_types=1);

namespace App\Modules\Notification\Notifications;

use App\Modules\Notification\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi status pembelian (N9 diinput, N10 diverifikasi/ditolak).
 *
 * Payload primitif + ShouldQueue + afterCommit — sama seperti
 * RequestStatusNotification.
 */
class PurchaseStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NotificationType $type,
        private readonly string $purchaseNumber,
        private readonly int $purchaseId,
        private readonly ?bool $verified = null,
        private readonly ?string $reason = null,
    ) {
        // Dikirim hanya SETELAH transaksi pemanggil commit (ADR-12).
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->type->channels();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'code' => $this->type->value,
            'title' => $this->type->title(),
            'message' => $this->message(),
            'reference' => $this->purchaseNumber,
            'url' => "/purchases/{$this->purchaseId}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->type->title().' — '.$this->purchaseNumber)
            ->greeting('Halo,')
            ->line($this->message())
            ->action('Buka Pembelian', url("/purchases/{$this->purchaseId}"))
            ->line('Terima kasih.');
    }

    private function message(): string
    {
        $num = $this->purchaseNumber;

        if ($this->type === NotificationType::PurchaseSubmitted) {
            return "Pembelian {$num} menunggu verifikasi Anda.";
        }

        if ($this->verified === true) {
            return "Pembelian {$num} telah diverifikasi. Stok telah bertambah.";
        }

        return "Pembelian {$num} ditolak. Alasan: ".($this->reason ?? '-').' — silakan revisi.';
    }
}
