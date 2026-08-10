<?php

declare(strict_types=1);

namespace App\Modules\Requisition\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('request');
        $user = $this->user();

        if ($user === null || $request === null) {
            return false;
        }

        // Penolakan boleh dilakukan siapa pun yang berwenang pada level yang
        // sedang menunggu — level ditentukan status dokumen, bukan input user.
        return $user->can('approveL1', $request)
            || $user->can('approveL2', $request)
            || $user->can('approveL3', $request);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Blueprint 3.2/3.4: "tekan tombol ditolak dan masukkan alasan penolakan".
            'rejection_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rejection_notes.required' => 'Alasan penolakan wajib diisi.',
            'rejection_notes.min' => 'Alasan penolakan terlalu singkat untuk dapat ditindaklanjuti.',
        ];
    }
}
