<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Item;
use App\Modules\Requisition\Enums\RequestItemStatus;
use App\Modules\Requisition\Models\Request;
use App\Modules\Requisition\Models\RequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RequestItem> */
class RequestItemFactory extends Factory
{
    protected $model = RequestItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_id' => Request::factory(),
            'item_id' => Item::factory(),
            'quantity_requested' => 10,
            'quantity_approved' => null,
            'quantity_actual' => null,
            'status' => RequestItemStatus::Requested,
        ];
    }
}
