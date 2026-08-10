<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Department::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'account_code' => ['nullable', 'string', 'max:30'],
            'parent_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'head_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'Kode',
            'name' => 'Nama',
            'account_code' => 'Kode akun',
            'parent_id' => 'Induk',
            'head_user_id' => 'Kepala',
        ];
    }
}
