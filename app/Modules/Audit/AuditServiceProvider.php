<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Modules\Audit\Listeners\AuthAuditSubscriber;
use App\Modules\Audit\Observers\ItemObserver;
use App\Modules\Audit\Observers\RequestItemObserver;
use App\Modules\Catalog\Models\Item;
use App\Modules\Requisition\Models\RequestItem;
use App\Shared\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class AuditServiceProvider extends ModuleServiceProvider
{
    protected function name(): string
    {
        return 'audit';
    }

    public function boot(): void
    {
        parent::boot();

        // Observer entitas sensitif (§8.2). Observer di-resolve container sehingga
        // AuditLogger ter-inject otomatis.
        Item::observe(ItemObserver::class);
        RequestItem::observe(RequestItemObserver::class);

        // Login berhasil/gagal/logout untuk keamanan.
        Event::subscribe(AuthAuditSubscriber::class);
    }
}
