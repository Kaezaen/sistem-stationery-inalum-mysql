<?php

declare(strict_types=1);

namespace App\Modules\Approval;

use App\Shared\Providers\ModuleServiceProvider;

class ApprovalServiceProvider extends ModuleServiceProvider
{
    protected function name(): string
    {
        return 'approval';
    }
}
