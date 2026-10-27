<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ReportItem;
use App\Models\ReportRevision;
use Illuminate\Support\Carbon;

/**
 * Preserves every released version of a report. A revision row holds the
 * complete report exactly as it was released, so corrections never erase
 * what was previously issued.
 */
class ReportRevisionService
{
    public function __construct(private readonly ReportSnapshotService $snapshots) {}

    public function record(Report $report, ?string $reason, ?int $userId): ReportRevision
    {
        return ReportRevision::create([
            'report_id' => $report->id,
            'revision' => $report->revision,
            'snapshot' => $this->snapshots->reportSnapshot($report),
            'reason' => $reason,
            'created_by' => $userId,
        ]);
    }

    /**
     * Rebuild an unsaved Report (with items) from a revision snapshot so the
     * standard report document template can render it.
     */
    public function hydrate(ReportRevision $revision): Report
    {
        $data = $revision->snapshot['report'] ?? [];

        $report = new Report;
        $report->forceFill([
            'id' => $data['id'] ?? $revision->report_id,
            'report_number' => $data['report_number'] ?? null,
            'revision' => $data['revision'] ?? $revision->revision,
            'status' => $data['status'] ?? Report::STATUS_FINAL,
            'patient_id' => $data['patient_id'] ?? null,
            'patient_name' => $data['patient_name'] ?? '',
            'patient_age_value' => $data['patient_age_value'] ?? null,
            'patient_age_unit' => $data['patient_age_unit'] ?? null,
            'patient_sex' => $data['patient_sex'] ?? 'unspecified',
            'referring_doctor' => $data['referring_doctor'] ?? null,
            'collected_by' => $data['collected_by'] ?? null,
            'sample_id' => $data['sample_id'] ?? null,
            'registered_at' => isset($data['registered_at']) ? Carbon::parse($data['registered_at']) : null,
            'collected_at' => isset($data['collected_at']) ? Carbon::parse($data['collected_at']) : null,
            'reported_at' => isset($data['reported_at']) ? Carbon::parse($data['reported_at']) : null,
            'finalized_at' => isset($data['finalized_at']) ? Carbon::parse($data['finalized_at']) : null,
            'clinical_notes' => $data['clinical_notes'] ?? null,
            'correction_reason' => $data['correction_reason'] ?? null,
            'abnormal_count' => $data['abnormal_count'] ?? 0,
        ]);

        $items = collect($revision->snapshot['items'] ?? [])
            ->map(fn (array $item) => (new ReportItem)->forceFill($item));

        $report->setRelation('items', $items);
        $report->setRelation('patient', null);
        $report->exists = false;

        return $report;
    }
}
