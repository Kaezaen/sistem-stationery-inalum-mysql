<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Console\AdjustStockCommand;
use App\Modules\Inventory\Console\GenerateMonthlyStockSnapshotCommand;
use App\Modules\Inventory\Console\ReconcileStockCommand;
use App\Modules\Inventory\Console\SeedInitialStockCommand;
use App\Shared\Providers\ModuleServiceProvider;

class InventoryServiceProvider extends ModuleServiceProvider
{
    protected function name(): string
    {
        return 'inventory';
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReconcileStockCommand::class,
                AdjustStockCommand::class,
                GenerateMonthlyStockSnapshotCommand::class,
                SeedInitialStockCommand::class,
            ]);
        }
    }
}
