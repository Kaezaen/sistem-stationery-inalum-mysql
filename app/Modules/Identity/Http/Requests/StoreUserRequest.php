<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Enums\UserPosition;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', 'max:30', Rule::unique('users', 'employee_id')],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'position' => ['nullable', Rule::enum(UserPosition::class)],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_active' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => [Rule::in(Role::values())],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'employee_id' => 'NIP',
            'username' => 'Username',
            'name' => 'Nama',
            'email' => 'Email',
            'password' => 'Kata sandi',
            'department_id' => 'Departemen',
            'position' => 'Jabatan',
            'manager_id' => 'Atasan langsung',
            'roles' => 'Role',
        ];
    }
}
