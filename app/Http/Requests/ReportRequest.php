<?php

namespace App\Http\Requests;

use App\Models\Patient;
use App\Support\Age;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Patient, registration and test-selection fields of a report. Result values
 * are validated per parameter type by ReportResultValidationService.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'test_ids' => array_values(array_filter((array) $this->input('test_ids', []), fn ($id) => $id !== null && $id !== '')),
            'age_value' => $this->filled('age_value') ? $this->input('age_value') : null,
            'patient_id' => $this->filled('patient_id') ? $this->input('patient_id') : null,
        ]);
    }

    public function rules(): array
    {
        $updating = $this->isMethod('put');

        return [
            // The patient of an existing report cannot be swapped; demographics are edited in place.
            'patient_id' => $updating
                ? ['prohibited']
                : ['nullable', 'integer', Rule::exists('lab_patients', 'id')->whereNull('deleted_at')],
            'patient_name' => ['required', 'string', 'max:150'],
            'age_value' => ['nullable', 'integer', 'min:0', 'max:999'],
            'age_unit' => ['nullable', Rule::in(array_keys(Age::UNITS))],
            'sex' => ['required', Rule::in(array_keys(Patient::SEXES))],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]*$/'],
            'referring_doctor' => ['nullable', 'string', 'max:150'],
            'collected_by' => ['nullable', 'string', 'max:150'],
            'sample_id' => ['nullable', 'string', 'max:60'],
            'registered_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(5)->toDateTimeString()],
            'collected_at' => ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(5)->toDateTimeString()],
            'clinical_notes' => ['nullable', 'string', 'max:2000'],
            'test_ids' => ['required', 'array', 'min:1', 'max:40'],
            'test_ids.*' => ['integer', 'distinct', Rule::exists('test_definitions', 'id')],
            'form_token' => [$updating ? 'nullable' : 'required', 'uuid'],
            'lock_version' => [$updating ? 'required' : 'nullable', 'integer'],
            'results' => ['nullable', 'array'],
            'intent' => ['nullable', Rule::in(['save', 'review', 'continue'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $value = $this->input('age_value');
                $unit = $this->input('age_unit') ?: 'years';
                $limits = ['years' => 130, 'months' => 36, 'days' => 62];
                if ($value !== null && is_numeric($value) && (int) $value > ($limits[$unit] ?? 130)) {
                    $validator->errors()->add('age_value', "Age in {$unit} looks too large — check the unit.");
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'test_ids.required' => 'Select at least one test.',
            'test_ids.min' => 'Select at least one test.',
            'registered_at.before_or_equal' => 'Registration time cannot be in the future.',
            'collected_at.before_or_equal' => 'Collection time cannot be in the future.',
            'phone.regex' => 'Use digits, spaces, +, - or parentheses only.',
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_name' => 'patient name',
            'age_value' => 'age',
            'registered_at' => 'registration date',
            'collected_at' => 'collection date',
        ];
    }
}
