<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'exists:users,email',
            ],

            'amount_paise' => [
                'required',
                'integer',
                'min:1',
                'max:' . config('wallet.max_transfer_paise'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_paise.max' => 'Maximum transfer limit is ₹' . config('wallet.max_transfer_paise')/100 . '.',
            'amount_paise.min' => 'Transfer amount must be greater than zero.',
        ];
    }
}

