<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\TransactionType;
use App\Modules\Inventory\Models\InventoryTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryTransaction> */
class InventoryTransactionFactory extends Factory
{
    protected $model = InventoryTransaction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'transaction_type' => TransactionType::In,
            'quantity' => 10,
            'quantity_before' => 0,
            'quantity_after' => 10,
            'reference_type' => null,
            'reference_id' => null,
            'transaction_date' => now(),
            'performed_by' => User::factory(),
            'adjustment_reason' => null,
        ];
    }
}
