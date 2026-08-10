<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Enam kategori dari filter pada wireframe 3.1.2.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'STATIONERIES', 'name' => 'Stationeries'],
            ['code' => 'DRINK_SUGAR', 'name' => 'Drink & Sugar'],
            ['code' => 'DISINFECTANT', 'name' => 'Disinfectant'],
            ['code' => 'DAILY_NECESSITIES', 'name' => 'Daily Necessities'],
            ['code' => 'OFFICE_TOOL', 'name' => 'Office Tool'],
            ['code' => 'PRINT_EXPENSE', 'name' => 'Print Expense'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['code' => $category['code']],
                ['name' => $category['name'], 'is_active' => true],
            );
        }
    }
}
