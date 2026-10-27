<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates the general information of a test definition. The parameter
 * schema itself is validated and normalized by TestSchemaValidator inside
 * TestDefinitionService.
 */
class TestDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
            'price' => $this->filled('price') ? $this->input('price') : null,
            'turnaround_hours' => $this->filled('turnaround_hours') ? $this->input('turnaround_hours') : null,
        ]);
    }

    public function rules(): array
    {
        $test = $this->route('test');

        return [
            'code' => [
                'required', 'string', 'max:32', 'regex:/^[A-Z0-9][A-Z0-9\-_]*$/',
                Rule::unique('test_definitions', 'code')->ignore($test?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'sample_type' => ['nullable', 'string', 'max:120'],
            'collection_instructions' => ['nullable', 'string', 'max:2000'],
            'preparation_instructions' => ['nullable', 'string', 'max:2000'],
            'method' => ['nullable', 'string', 'max:191'],
            'reporting_notes' => ['nullable', 'string', 'max:5000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'turnaround_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'is_active' => ['boolean'],
            'schema' => ['required', 'string', 'max:500000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, digits, dashes or underscores (e.g. CBC or GLU-FPP).',
            'code.unique' => 'Another test (possibly archived) already uses this code.',
            'schema.required' => 'Add at least one parameter.',
        ];
    }
}
