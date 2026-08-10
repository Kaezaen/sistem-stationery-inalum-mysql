<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('verify', $this->route('purchase')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Blueprint 3.10: "tekan tombol ditolak dan masukkan alasan penolakan".
            'rejection_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rejection_notes.required' => 'Alasan penolakan wajib diisi.',
            'rejection_notes.min' => 'Alasan penolakan terlalu singkat untuk dapat dipahami.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['rejection_notes' => 'Alasan penolakan'];
    }
}
