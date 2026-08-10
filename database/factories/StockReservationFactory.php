<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Item;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Enums\ReservationStatus;
use App\Modules\Inventory\Models\StockReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockReservation> */
class StockReservationFactory extends Factory
{
    protected $model = StockReservation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'request_item_id' => null,
            'quantity' => 1,
            'status' => ReservationStatus::Held,
            'expires_at' => now()->addDays(30),
            'created_by' => User::factory(),
        ];
    }
}
