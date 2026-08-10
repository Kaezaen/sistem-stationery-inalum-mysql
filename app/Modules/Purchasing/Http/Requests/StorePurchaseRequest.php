<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests;

use App\Modules\Purchasing\Models\Purchase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Purchase::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Keunikan menjaga agar satu faktur pemasok tidak terinput dua kali
            // dan menaikkan stok berganda.
            'purchase_number' => ['required', 'string', 'max:30', Rule::unique('purchases', 'purchase_number')],
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
            'items.min' => 'Pembelian harus memuat minimal satu item.',
            'purchase_number.unique' => 'Nomor pembelian ini sudah pernah diinput.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'purchase_number' => 'Nomor Pembelian',
            'purchase_date' => 'Tanggal Pembelian',
            'supplier_name' => 'Nama Supplier',
        ];
    }
}
