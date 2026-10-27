<?php

namespace App\Services;

use App\Models\Report;
use App\Models\TestDefinition;

/**
 * Builds the immutable copies that keep historical reports stable:
 *  - a report item copies the full test definition (schema, units, reference
 *    intervals, notes) at the version in force when the test was added;
 *  - a report snapshot captures a released report for its revision history.
 */
class ReportSnapshotService
{
    public function itemAttributes(TestDefinition $definition, int $position): array
    {
        return [
            'test_definition_id' => $definition->id,
            'definition_version' => $definition->version,
            'test_code' => $definition->code,
            'test_name' => $definition->name,
            'category' => $definition->category,
            'sample_type' => $definition->sample_type,
            'method' => $definition->method,
            'reporting_notes' => $definition->reporting_notes,
            'price' => $definition->price,
            'schema_snapshot' => $definition->schema,
            'results' => $this->defaultResults($definition->schema),
            'flags' => [],
            'abnormal_count' => 0,
            'position' => $position,
        ];
    }

    /**
     * Pre-fill configured default values (e.g. "Nil" for urine microscopy).
     */
    public function defaultResults(array $schema): array
    {
        $results = [];
        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['parameters'] ?? [] as $parameter) {
                $results[$parameter['key']] = $parameter['default'] ?? null;
            }
        }

        return $results;
    }

    public function reportSnapshot(Report $report): array
    {
        $report->loadMissing(['items', 'patient']);

        return [
            'report' => [
                'id' => $report->id,
                'report_number' => $report->report_number,
                'revision' => $report->revision,
                'status' => $report->status,
                'patient_id' => $report->patient_id,
                'patient_code' => $report->patient?->patient_code,
                'patient_name' => $report->patient_name,
                'patient_age_value' => $report->patient_age_value,
                'patient_age_unit' => $report->patient_age_unit,
                'patient_sex' => $report->patient_sex,
                'referring_doctor' => $report->referring_doctor,
                'collected_by' => $report->collected_by,
                'sample_id' => $report->sample_id,
                'registered_at' => $report->registered_at?->toIso8601String(),
                'collected_at' => $report->collected_at?->toIso8601String(),
                'reported_at' => $report->reported_at?->toIso8601String(),
                'finalized_at' => $report->finalized_at?->toIso8601String(),
                'finalized_by' => $report->finalized_by,
                'clinical_notes' => $report->clinical_notes,
                'correction_reason' => $report->correction_reason,
                'abnormal_count' => $report->abnormal_count,
            ],
            'items' => $report->items->map(fn ($item) => [
                'test_definition_id' => $item->test_definition_id,
                'definition_version' => $item->definition_version,
                'test_code' => $item->test_code,
                'test_name' => $item->test_name,
                'category' => $item->category,
                'sample_type' => $item->sample_type,
                'method' => $item->method,
                'reporting_notes' => $item->reporting_notes,
                'price' => $item->price,
                'schema_snapshot' => $item->schema_snapshot,
                'results' => $item->results,
                'flags' => $item->flags,
                'abnormal_count' => $item->abnormal_count,
                'position' => $item->position,
            ])->values()->all(),
        ];
    }
}
