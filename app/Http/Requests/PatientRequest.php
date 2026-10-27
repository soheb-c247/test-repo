<?php

namespace App\Http\Requests;

use App\Models\Patient;
use App\Support\Age;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['age_value' => $this->filled('age_value') ? $this->input('age_value') : null]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'age_value' => ['nullable', 'integer', 'min:0', 'max:130'],
            'age_unit' => ['nullable', Rule::in(array_keys(Age::UNITS))],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(array_keys(Patient::SEXES))],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]*$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
