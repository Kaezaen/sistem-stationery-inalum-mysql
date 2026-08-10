<?php

declare(strict_types=1);

namespace App\Modules\Fulfillment;

use App\Modules\Fulfillment\Console\ReleaseExpiredReservationsCommand;
use App\Shared\Providers\ModuleServiceProvider;

class FulfillmentServiceProvider extends ModuleServiceProvider
{
    protected function name(): string
    {
        return 'fulfillment';
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([ReleaseExpiredReservationsCommand::class]);
        }
    }
}
