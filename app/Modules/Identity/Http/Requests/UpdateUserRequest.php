<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Enums\UserPosition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $this->user()?->can('update', $target) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'employee_id' => ['required', 'string', 'max:30', Rule::unique('users', 'employee_id')->ignore($userId)],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($userId)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'position' => ['nullable', Rule::enum(UserPosition::class)],
            // Tidak boleh menunjuk dirinya sendiri sebagai atasan; siklus yang
            // lebih panjang diperiksa UserService::guardAgainstManagerCycle.
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::notIn([$userId])],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => [Rule::in(Role::values())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'manager_id.not_in' => 'User tidak boleh menjadi atasan dirinya sendiri.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'employee_id' => 'NIP',
            'name' => 'Nama',
            'department_id' => 'Departemen',
            'manager_id' => 'Atasan langsung',
        ];
    }
}
