<?php

namespace App\Http\Requests;

use App\Models\Bill;
use App\Models\BillPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discount_value' => $this->filled('discount_value') ? $this->input('discount_value') : 0,
            'payment_amount' => $this->filled('payment_amount') ? $this->input('payment_amount') : null,
        ]);
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'form_token' => $creating ? ['nullable', 'uuid'] : ['prohibited'],
            'report_id' => $creating ? ['required', 'integer', 'exists:reports,id'] : ['prohibited'],
            'lock_version' => $creating ? ['prohibited'] : ['required', 'integer'],
            'issued_at' => ['required', 'date'],
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'discount_type' => ['required', Rule::in(array_keys(Bill::DISCOUNT_TYPES))],
            'discount_value' => ['numeric', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_amount' => $creating ? ['nullable', 'numeric', 'min:0', 'max:9999999'] : ['prohibited'],
            'payment_method' => $creating ? ['required_with:payment_amount', 'nullable', Rule::in(array_keys(BillPayment::METHODS))] : ['prohibited'],
            'payment_reference' => $creating ? ['nullable', 'string', 'max:100'] : ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [
            'items.*' => 'test price',
            'discount_value' => 'discount',
            'payment_amount' => 'amount received',
            'issued_at' => 'bill date',
        ];
    }
}
