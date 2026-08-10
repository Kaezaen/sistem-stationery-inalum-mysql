<?php

declare(strict_types=1);

namespace App\Modules\Purchasing;

use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Policies\PurchasePolicy;
use App\Shared\Providers\ModuleServiceProvider;

class PurchasingServiceProvider extends ModuleServiceProvider
{
    protected function name(): string
    {
        return 'purchasing';
    }

    /** @return array<class-string, class-string> */
    protected function policies(): array
    {
        return [
            Purchase::class => PurchasePolicy::class,
        ];
    }
}
