<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Models\Department;
use App\Modules\Identity\Models\User;
use App\Modules\Requisition\Enums\RequestStatus;
use App\Modules\Requisition\Models\Request;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Request> */
class RequestFactory extends Factory
{
    protected $model = Request::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_number' => 'REQ'.fake()->unique()->numerify('###'),
            'requester_id' => User::factory(),
            'department_id' => Department::factory(),
            'request_date' => now()->toDateString(),
            'status' => RequestStatus::Draft,
            'current_approval_level' => 0,
            'revision_count' => 0,
        ];
    }

    public function withStatus(RequestStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'current_approval_level' => $status->pendingLevel(),
            'submitted_at' => now(),
        ]);
    }
}
