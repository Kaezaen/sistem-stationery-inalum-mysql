<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Item;
use App\Modules\Inventory\Models\StockMonthlySnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockMonthlySnapshot> */
class StockMonthlySnapshotFactory extends Factory
{
    protected $model = StockMonthlySnapshot::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'period_year' => 2026,
            'period_month' => 1,
            'opening_balance' => 0,
            'total_in' => 0,
            'total_out' => 0,
            'total_adjustment' => 0,
            'closing_balance' => 0,
            'generated_at' => now(),
        ];
    }
}
