<?php

declare(strict_types=1);

namespace App\Modules\Notification\Notifications;

use App\Modules\Notification\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi status request (N1–N8).
 *
 * Membawa data PRIMITIF (bukan model Eloquent) dengan sengaja: notifikasi
 * di-queue, sehingga payload skalar aman diserialisasi tanpa re-fetch, dan modul
 * Notification tetap bebas dari import model modul bisnis.
 *
 * ShouldQueue + $afterCommit: dikirim SETELAH transaksi commit (ADR-12). Bila
 * transaksi approval gagal, pengguna tidak menerima kabar yang tak jadi tersimpan.
 */
class RequestStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly NotificationType $type,
        private readonly string $requestNumber,
        private readonly int $requestId,
        private readonly ?string $reason = null,
    ) {
        // Dikirim hanya SETELAH transaksi pemanggil commit (ADR-12). Memakai method
        // afterCommit() dari trait Queueable, bukan meredeklarasi propertinya.
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
            'reference' => $this->requestNumber,
            'url' => "/requests/{$this->requestId}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->type->title().' — '.$this->requestNumber)
            ->greeting('Halo,')
            ->line($this->message())
            ->action('Buka Request', url("/requests/{$this->requestId}"))
            ->line('Terima kasih.');
    }

    private function message(): string
    {
        $num = $this->requestNumber;
        $reason = $this->reason ?? '-';

        return match ($this->type) {
            NotificationType::RequestSubmitted => "Request {$num} menunggu persetujuan Anda sebagai Pimpinan SIT.",
            NotificationType::RequestApprovedL1 => "Request {$num} telah disetujui Pimpinan dan menunggu verifikasi Anda.",
            NotificationType::RequestRejectedL1 => "Request {$num} ditolak Pimpinan. Alasan: {$reason}",
            NotificationType::RequestApprovedL2 => "Request {$num} menunggu persetujuan Anda sebagai Pimpinan SGA.",
            NotificationType::RequestRejectedL2 => "Request {$num} ditolak PIC Stationery. Alasan: {$reason}",
            NotificationType::RequestApprovedL3 => "Request {$num} telah disetujui penuh dan siap diserahkan.",
            NotificationType::RequestRejectedL3 => "Request {$num} ditolak Pimpinan SGA. Alasan: {$reason} — silakan revisi.",
            NotificationType::RequestCompleted => "Barang untuk request {$num} telah diserahkan.",
            default => "Status request {$num} diperbarui.",
        };
    }
}
