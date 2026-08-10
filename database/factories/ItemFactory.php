<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\Uom;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Item> */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'item_code' => fake()->unique()->numerify('17090#####'),
            'item_name' => strtoupper(fake()->words(3, true)),
            'description' => null,
            'category_id' => Category::factory(),
            'uom_id' => Uom::factory(),
            'stock_quantity' => 0,
            'reserved_quantity' => 0,
            'min_stock' => 5,
            'max_stock' => 10,
            'remark' => null,
            'is_active' => true,
        ];
    }

    /**
     * Menyetel saldo stok langsung.
     *
     * HANYA untuk pengujian. Di jalur aplikasi, stok tidak pernah ditulis
     * selain lewat StockService (ADR-08).
     */
    public function withStock(int $quantity, int $reserved = 0): static
    {
        return $this->state(fn (array $attributes): array => [
            'stock_quantity' => $quantity,
            'reserved_quantity' => $reserved,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
