<?php

declare(strict_types=1);

namespace App\Modules\Notification\Notifications;

use App\Modules\Notification\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi stok mencapai batas minimum (N11) → PIC Stationery + PIC Gudang.
 *
 * Turunan dari laporan Need to Buy: item ini perlu segera dibeli. Payload primitif
 * + ShouldQueue + afterCommit.
 */
class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $itemCode,
        private readonly string $itemName,
        private readonly int $stock,
        private readonly int $minStock,
        private readonly int $itemId,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return NotificationType::StockLow->channels();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'code' => NotificationType::StockLow->value,
            'title' => NotificationType::StockLow->title(),
            'message' => $this->message(),
            'reference' => $this->itemCode,
            'url' => "/inventory/{$this->itemId}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(NotificationType::StockLow->title().' — '.$this->itemCode)
            ->greeting('Halo,')
            ->line($this->message())
            ->action('Lihat Inventory', url("/inventory/{$this->itemId}"))
            ->line('Pertimbangkan untuk mengajukan pembelian.');
    }

    private function message(): string
    {
        return "Stok {$this->itemName} ({$this->itemCode}) mencapai batas minimum: "
            ."{$this->stock} dari minimum {$this->minStock}. Perlu pembelian.";
    }
}
