<?php

namespace App\Http\Requests;

use App\Models\BillPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'form_token' => ['nullable', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'method' => ['required', Rule::in(array_keys(BillPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toDateTimeString()],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['paid_at' => 'payment date', 'method' => 'payment method'];
    }
}
