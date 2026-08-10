<?php

declare(strict_types=1);

namespace App\Modules\Reporting;

use App\Shared\Providers\ModuleServiceProvider;

class ReportingServiceProvider extends ModuleServiceProvider
{
    protected function name(): string
    {
        return 'reporting';
    }
}
