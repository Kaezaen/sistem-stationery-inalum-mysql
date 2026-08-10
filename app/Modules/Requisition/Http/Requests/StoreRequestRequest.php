<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Http\Requests;

use App\Modules\Requisition\Models\Request;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $existing = $this->route('request');

        // Dipakai dua jalur: pembuatan baru dan revisi. Keduanya punya Policy
        // berbeda, dan yang menentukan adalah ada tidaknya dokumen di route.
        return $existing instanceof Request
            ? ($this->user()?->can('revise', $existing) ?? false)
            : ($this->user()?->can('create', Request::class) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'required', 'integer',
                Rule::exists('items', 'id')->whereNull('deleted_at')->where('is_active', true),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal satu item sebelum mengajukan request.',
            'items.min' => 'Pilih minimal satu item sebelum mengajukan request.',
            'items.*.item_id.exists' => 'Salah satu item sudah tidak tersedia di katalog.',
        ];
    }
}
