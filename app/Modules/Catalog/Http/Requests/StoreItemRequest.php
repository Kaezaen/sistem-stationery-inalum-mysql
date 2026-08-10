<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Models\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Item::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'item_code' => ['required', 'string', 'max:30', Rule::unique('items', 'item_code')->whereNull('deleted_at')],
            'item_name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'uom_id' => ['required', 'integer', Rule::exists('uoms', 'id')],
            'min_stock' => ['required', 'integer', 'min:0'],
            'max_stock' => ['required', 'integer', 'min:0', 'gte:min_stock'],
            'remark' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'max_stock.gte' => 'Max Stock harus lebih besar atau sama dengan Min Stock.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'item_code' => 'Item Code',
            'item_name' => 'Item Name',
            'category_id' => 'Category',
            'uom_id' => 'UoM',
            'min_stock' => 'Min Stock',
            'max_stock' => 'Max Stock',
            'remark' => 'Remark',
        ];
    }
}
