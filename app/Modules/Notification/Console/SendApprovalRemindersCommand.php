<?php

declare(strict_types=1);

namespace App\Modules\Notification\Console;

use App\Modules\Identity\Enums\Role;
use App\Modules\Notification\Notifications\PendingApprovalNotification;
use App\Modules\Notification\Support\RecipientResolver;
use App\Modules\Purchasing\Enums\PurchaseStatus;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Models\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * N12 — pengingat approval tertunda (SLA), dijalankan scheduler.
 *
 * Dokumen yang menunggu di satu tahap lebih lama dari ambang hari dikirimi
 * pengingat ke approver yang berlaku pada tahap itu. updated_at dipakai sebagai
 * "sejak kapan menunggu di tahap ini" — ia berubah setiap transisi status.
 */
class SendApprovalRemindersCommand extends Command
{
    protected $signature = 'approvals:remind {--days=2 : Ambang jumlah hari tertunda}';

    protected $description = 'Mengirim pengingat approval yang tertunda melebihi ambang hari (N12)';

    public function handle(RecipientResolver $resolver): int
    {
        $days = max(1, (int) $this->option('days'));
        $threshold = now()->subDays($days);
        $sent = 0;

        Request::query()
            ->whereIn('status', [
                RequestStatus::PendingSupervisor->value,
                RequestStatus::PendingStationery->value,
                RequestStatus::PendingSga->value,
            ])
            ->where('updated_at', '<', $threshold)
            ->with('requester')
            ->chunkById(200, function ($requests) use ($resolver, &$sent): void {
                foreach ($requests as $request) {
                    $recipients = match ($request->status) {
                        RequestStatus::PendingSupervisor => $request->requester !== null
                            ? $resolver->supervisorOf($request->requester)
                            : collect(),
                        RequestStatus::PendingStationery => $resolver->withRole(Role::PicStationery),
                        RequestStatus::PendingSga => $resolver->withRole(Role::SgaManager),
                        default => collect(),
                    };

                    $sent += $this->remind(
                        $recipients,
                        $request->request_number,
                        "/requests/{$request->id}",
                        $this->waitedDays($request->updated_at),
                    );
                }
            });

        Purchase::query()
            ->where('status', PurchaseStatus::PendingVerification->value)
            ->where('updated_at', '<', $threshold)
            ->chunkById(200, function ($purchases) use ($resolver, &$sent): void {
                foreach ($purchases as $purchase) {
                    $sent += $this->remind(
                        $resolver->withRole(Role::PicStationery),
                        $purchase->purchase_number,
                        "/purchases/{$purchase->id}",
                        $this->waitedDays($purchase->updated_at),
                    );
                }
            });

        $this->info(sprintf('Pengingat approval terkirim untuk %d dokumen tertunda (> %d hari).', $sent, $days));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, \App\Modules\Identity\Models\User>  $recipients
     * @return int 1 bila terkirim, 0 bila tidak ada penerima
     */
    private function remind(Collection $recipients, string $reference, string $url, int $days): int
    {
        $recipients = $recipients->unique('id');

        if ($recipients->isEmpty()) {
            return 0;
        }

        Notification::send($recipients, new PendingApprovalNotification($reference, $url, $days));

        return 1;
    }

    private function waitedDays(?\Carbon\CarbonInterface $since): int
    {
        return $since !== null ? (int) $since->diffInDays(now()) : 0;
    }
}
