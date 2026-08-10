<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('???'),
            'name' => fake()->company(),
            'account_code' => null,
            'parent_id' => null,
            'head_user_id' => null,
            'is_active' => true,
        ];
    }
}
