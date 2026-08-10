<?php

declare(strict_types=1);

namespace App\Modules\Requisition;

use App\Modules\Requisition\Models\Request;
use App\Modules\Requisition\Policies\RequestPolicy;
use App\Shared\Providers\ModuleServiceProvider;

class RequisitionServiceProvider extends ModuleServiceProvider
{
    protected function name(): string
    {
        return 'requisition';
    }

    /** @return array<class-string, class-string> */
    protected function policies(): array
    {
        return [
            Request::class => RequestPolicy::class,
        ];
    }
}
