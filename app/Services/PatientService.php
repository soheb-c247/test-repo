<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Arr;

class PatientService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Use the selected patient (updating their demographics with the values
     * entered on the report form) or register a new patient.
     *
     * @param  array{patient_id?:?int,name:string,age_value?:?int,age_unit?:?string,sex?:?string,phone?:?string,email?:?string,address?:?string}  $data
     */
    public function resolve(array $data, bool $isDemo = false): Patient
    {
        $attributes = $this->attributes($data);

        if (! empty($data['patient_id'])) {
            $patient = Patient::query()->findOrFail($data['patient_id']);
            $patient->fill($attributes)->save();

            return $patient;
        }

        return $this->create($attributes + ['is_demo' => $isDemo]);
    }

    public function create(array $attributes): Patient
    {
        $patient = Patient::create($this->attributes($attributes) + ['is_demo' => (bool) ($attributes['is_demo'] ?? false)]);
        $patient->forceFill([
            'patient_code' => config('lab.patient_prefix', 'PT').'-'.str_pad((string) $patient->id, 6, '0', STR_PAD_LEFT),
        ])->save();

        $this->audit->record('patient.created', $patient, ['code' => $patient->patient_code]);

        return $patient;
    }

    public function update(Patient $patient, array $attributes): Patient
    {
        $patient->fill($this->attributes($attributes))->save();
        $this->audit->record('patient.updated', $patient, ['code' => $patient->patient_code]);

        return $patient;
    }

    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, ['name', 'age_value', 'age_unit', 'date_of_birth', 'sex', 'phone', 'email', 'address']);

        if (array_key_exists('age_value', $attributes) && ($attributes['age_value'] === null || $attributes['age_value'] === '')) {
            $attributes['age_value'] = null;
            $attributes['age_unit'] = null;
        } elseif (isset($attributes['age_value'])) {
            $attributes['age_unit'] = $attributes['age_unit'] ?? 'years';
        }

        if (array_key_exists('sex', $attributes)) {
            $attributes['sex'] = $attributes['sex'] ?: 'unspecified';
        }

        return $attributes;
    }
}
