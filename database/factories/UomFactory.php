<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\Uom;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Uom> */
class UomFactory extends Factory
{
    protected $model = Uom::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('U??')),
            'name' => fake()->word(),
        ];
    }
}
