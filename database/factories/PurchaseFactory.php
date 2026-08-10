<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Purchasing\Enums\PurchaseStatus;
use App\Modules\Purchasing\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Purchase> */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'purchase_number' => fake()->unique()->numerify('1112345678##'),
            'purchase_date' => now()->toDateString(),
            'supplier_name' => 'TOKO '.strtoupper(fake()->word()),
            'created_by' => User::factory(),
            'status' => PurchaseStatus::Draft,
            'notes' => null,
            'revision_count' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PurchaseStatus::PendingVerification,
        ]);
    }

    public function rejected(string $reason = 'Jumlah tidak sesuai fisik'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PurchaseStatus::Rejected,
            'rejection_notes' => $reason,
        ]);
    }
}
