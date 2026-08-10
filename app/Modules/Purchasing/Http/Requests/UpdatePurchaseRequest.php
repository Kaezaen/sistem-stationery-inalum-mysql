<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('purchase')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('purchase')?->id;

        return [
            'purchase_number' => [
                'required', 'string', 'max:30',
                Rule::unique('purchases', 'purchase_number')->ignore($id),
            ],
            'purchase_date' => ['required', 'date'],
            'supplier_name' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required' => 'Pembelian harus memuat minimal satu item.',
            'purchase_number.unique' => 'Nomor pembelian ini sudah pernah diinput.',
        ];
    }
}
