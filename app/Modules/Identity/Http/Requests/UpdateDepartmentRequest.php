<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('department')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('department')?->id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'account_code' => ['nullable', 'string', 'max:30'],
            'parent_id' => ['nullable', 'integer', Rule::exists('departments', 'id'), Rule::notIn([$id])],
            'head_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'parent_id.not_in' => 'Departemen tidak boleh menjadi induk dirinya sendiri.',
        ];
    }
}
