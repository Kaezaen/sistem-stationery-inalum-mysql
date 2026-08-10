<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Item;
use App\Modules\Purchasing\Models\Purchase;
use App\Modules\Purchasing\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseItem> */
class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'item_id' => Item::factory(),
            'quantity' => 10,
            'unit_price' => null,
            'total_price' => null,
        ];
    }
}
