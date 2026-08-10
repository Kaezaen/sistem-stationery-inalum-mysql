<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Policies\CategoryPolicy;
use App\Modules\Catalog\Policies\ItemPolicy;
use App\Shared\Providers\ModuleServiceProvider;

class CatalogServiceProvider extends ModuleServiceProvider
{
    protected function name(): string
    {
        return 'catalog';
    }

    /** @return array<class-string, class-string> */
    protected function policies(): array
    {
        return [
            Item::class => ItemPolicy::class,
            Category::class => CategoryPolicy::class,
        ];
    }
}
