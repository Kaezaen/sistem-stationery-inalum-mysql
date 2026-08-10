<?php

declare(strict_types=1);

namespace App\Modules\Notification;

use App\Modules\Notification\Console\SendApprovalRemindersCommand;
use App\Modules\Notification\Listeners\PurchaseNotificationSubscriber;
use App\Modules\Notification\Listeners\RequestNotificationSubscriber;
use App\Modules\Notification\Listeners\StockNotificationSubscriber;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class NotificationServiceProvider extends ModuleServiceProvider
{
    protected function name(): string
    {
        return 'notification';
    }

    public function boot(): void
    {
        parent::boot();

        // Subscriber menerjemahkan event alur bisnis (dipancarkan sejak Fase 4–5)
        // menjadi notifikasi, tanpa menyentuh kode workflow yang sudah teruji.
        Event::subscribe(RequestNotificationSubscriber::class);
        Event::subscribe(PurchaseNotificationSubscriber::class);
        Event::subscribe(StockNotificationSubscriber::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SendApprovalRemindersCommand::class]);
        }
    }
}
