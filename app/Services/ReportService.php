<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Report;
use App\Models\ReportItem;
use App\Models\TestDefinition;
use App\Models\User;
use App\Support\Age;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Report lifecycle:
 *
 *   draft ──finalize──▶ final ──startCorrection──▶ draft (revision n+1) ──finalize──▶ final
 *
 * - Drafts are freely editable (with optimistic locking via lock_version).
 * - Finalized reports are read-only. A correction first stores the released
 *   version in report_revisions, then reopens the report as a new revision.
 * - Report items carry their own test-definition snapshot.
 */
class ReportService
{
    private const REPORT_FIELDS = [
        'referring_doctor', 'collected_by', 'sample_id', 'registered_at', 'collected_at', 'clinical_notes',
    ];

    public function __construct(
        private readonly PatientService $patients,
        private readonly ReportSnapshotService $snapshots,
        private readonly ReportRevisionService $revisions,
        private readonly ReportResultValidationService $resultValidator,
        private readonly ResultEvaluator $evaluator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create a draft report. Submitting the same form twice (same form_token)
     * returns the report created by the first submission.
     */
    public function createDraft(array $data, ?User $user, bool $isDemo = false): Report
    {
        $token = $data['form_token'] ?? null;
        if ($token && ($existing = Report::withTrashed()->where('form_token', $token)->first())) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($data, $user, $isDemo, $token) {
                $patient = $this->patients->resolve($this->patientData($data), $isDemo);

                $report = new Report(Arr::only($data, self::REPORT_FIELDS));
                $report->fill($this->patientSnapshot($patient));
                $report->forceFill([
                    'patient_id' => $patient->id,
                    'form_token' => $token,
                    'status' => Report::STATUS_DRAFT,
                    'revision' => 0,
                    'lock_version' => 1,
                    'abnormal_count' => 0,
                    'created_by' => $user?->id,
                    'is_demo' => $isDemo,
                ]);
                $report->save();

                $report->forceFill(['report_number' => $this->reportNumber($report)])->save();

                $definitions = $this->activeDefinitions($data['test_ids'] ?? []);
                foreach ($definitions->values() as $position => $definition) {
                    $report->items()->create($this->snapshots->itemAttributes($definition, $position));
                }

                $this->audit->record('report.created', $report, [
                    'report_number' => $report->report_number,
                    'tests' => $definitions->pluck('code')->all(),
                ]);

                return $report->load('items');
            });
        } catch (QueryException $e) {
            // A concurrent duplicate submission won the race on form_token.
            if ($token && ($existing = Report::withTrashed()->where('form_token', $token)->first())) {
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * Save a draft: demographics, registration details, test selection and
     * results. Malformed values reject the whole save; missing required
     * values are allowed until finalization.
     *
     * @param  array<int|string, array<string, mixed>>  $resultsInput  keyed by report item id
     *
     * @throws ValidationException
     */
    public function updateDraft(Report $report, array $data, array $resultsInput, ?User $user): Report
    {
        return DB::transaction(function () use ($report, $data, $resultsInput) {
            $report = $this->lockForEdit($report, (int) ($data['lock_version'] ?? 0));

            $patient = $this->patients->resolve(['patient_id' => $report->patient_id] + $this->patientData($data));
            $report->fill(Arr::only($data, self::REPORT_FIELDS));
            $report->fill($this->patientSnapshot($patient));

            if (array_key_exists('test_ids', $data)) {
                $this->syncItems($report, $data['test_ids']);
            }

            $this->saveResults($report, $resultsInput);

            $report->lock_version++;
            $report->save();

            $this->audit->record('report.updated', $report, ['report_number' => $report->report_number]);

            return $report->load('items');
        });
    }

    /**
     * @throws ValidationException
     */
    public function finalize(Report $report, ?User $user, int $lockVersion): Report
    {
        return DB::transaction(function () use ($report, $user, $lockVersion) {
            $report = $this->lockForEdit($report, $lockVersion);
            $report->load('items');

            $errors = [];
            if ($report->items->isEmpty()) {
                $errors['test_ids'] = 'Add at least one test before finalizing.';
            }
            if (! $report->collected_at) {
                $errors['collected_at'] = 'Enter the sample collection date and time before finalizing.';
            }

            foreach ($report->items as $item) {
                foreach ($this->missingRequired($item) as $key => $label) {
                    $errors["results.{$item->id}.{$key}"] = "{$item->test_name}: {$label} is required.";
                }
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $this->recalculate($report);

            $report->forceFill([
                'status' => Report::STATUS_FINAL,
                'reported_at' => now(),
                'finalized_at' => now(),
                'finalized_by' => $user?->id,
                'lock_version' => $report->lock_version + 1,
            ])->save();

            $this->audit->record('report.finalized', $report, [
                'report_number' => $report->report_number,
                'revision' => $report->revision,
            ]);

            return $report;
        });
    }

    /**
     * Reopen a finalized report for correction. The released version is
     * preserved in the revision history before anything can change.
     */
    public function startCorrection(Report $report, string $reason, ?User $user): Report
    {
        return DB::transaction(function () use ($report, $reason, $user) {
            $report = Report::query()->lockForUpdate()->findOrFail($report->id);

            if (! $report->isFinal()) {
                throw ValidationException::withMessages(['reason' => 'Only finalized reports can be corrected.']);
            }

            $this->revisions->record($report, $reason, $user?->id);

            $report->forceFill([
                'status' => Report::STATUS_DRAFT,
                'revision' => $report->revision + 1,
                'correction_reason' => $reason,
                'finalized_at' => null,
                'finalized_by' => null,
                'lock_version' => $report->lock_version + 1,
            ])->save();

            $this->audit->record('report.correction_started', $report, [
                'report_number' => $report->report_number,
                'revision' => $report->revision,
            ]);

            return $report;
        });
    }

    /**
     * Only never-released drafts may be deleted (soft delete).
     */
    public function deleteDraft(Report $report): void
    {
        if (! $report->isDraft() || $report->revision > 0) {
            throw ValidationException::withMessages(['report' => 'Released reports cannot be deleted.']);
        }

        $bill = $report->bills()->where('status', '!=', Bill::STATUS_CANCELLED)->first();
        if ($bill) {
            throw ValidationException::withMessages(['report' => "Cancel bill {$bill->bill_number} before discarding this draft."]);
        }

        $report->delete();
        $this->audit->record('report.deleted', $report, ['report_number' => $report->report_number]);
    }

    /**
     * Start a new draft for the same patient and tests (no results copied).
     */
    public function repeat(Report $source, ?User $user): Report
    {
        $source->loadMissing('items', 'patient');

        $ids = $source->items->pluck('test_definition_id')->filter()->all();
        $available = TestDefinition::query()->active()->whereIn('id', $ids)->pluck('id')->all();

        if ($available === []) {
            throw ValidationException::withMessages(['report' => 'None of the tests on this report are currently active.']);
        }

        $report = $this->createDraft([
            'patient_id' => $source->patient_id,
            'patient_name' => $source->patient?->name ?? $source->patient_name,
            'age_value' => $source->patient?->age_value ?? $source->patient_age_value,
            'age_unit' => $source->patient?->age_unit ?? $source->patient_age_unit,
            'sex' => $source->patient?->sex ?? $source->patient_sex,
            'phone' => $source->patient?->phone,
            'referring_doctor' => $source->referring_doctor,
            'collected_by' => $source->collected_by,
            'registered_at' => now(),
            'test_ids' => array_values(array_intersect($ids, $available)),
        ], $user, $source->is_demo);

        $this->audit->record('report.repeated', $report, ['from' => $source->report_number]);

        return $report;
    }

    /**
     * Required parameters without a value, keyed by parameter key.
     *
     * @return array<string, string>
     */
    public function missingRequired(ReportItem $item): array
    {
        $missing = [];
        foreach ($item->sections() as $section) {
            foreach ($section['parameters'] ?? [] as $parameter) {
                if (! ($parameter['required'] ?? false) || ! empty($parameter['formula'])) {
                    continue;
                }
                $value = $item->results[$parameter['key']] ?? null;
                if ($value === null || $value === '' || $value === []) {
                    $missing[$parameter['key']] = $parameter['label'];
                }
            }
        }

        return $missing;
    }

    private function lockForEdit(Report $report, int $lockVersion): Report
    {
        $fresh = Report::query()->lockForUpdate()->findOrFail($report->id);

        if (! $fresh->isDraft()) {
            throw ValidationException::withMessages([
                'report' => 'This report has been finalized and can no longer be edited. Start a correction instead.',
            ]);
        }

        if ($lockVersion !== $fresh->lock_version) {
            throw ValidationException::withMessages([
                'lock_version' => 'This report was changed in another window or tab. Reload the page to see the latest version before saving.',
            ]);
        }

        return $fresh;
    }

    private function syncItems(Report $report, array $testIds): void
    {
        $testIds = array_values(array_unique(array_map('intval', $testIds)));
        if ($testIds === []) {
            throw ValidationException::withMessages(['test_ids' => 'Select at least one test.']);
        }

        $existing = $report->items()->get()->keyBy('test_definition_id');

        // Remove deselected tests.
        $existing->each(function (ReportItem $item, $definitionId) use ($testIds) {
            if (! in_array((int) $definitionId, $testIds, true)) {
                $item->delete();
            }
        });

        // Add newly selected tests (only active definitions may be added).
        $newIds = array_values(array_diff($testIds, $existing->keys()->map('intval')->all()));
        $definitions = $newIds ? $this->activeDefinitions($newIds) : collect();

        foreach ($testIds as $position => $id) {
            if ($item = $existing->get($id)) {
                $item->forceFill(['position' => $position])->save();
            } elseif ($definition = $definitions->get($id)) {
                $report->items()->create($this->snapshots->itemAttributes($definition, $position));
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function saveResults(Report $report, array $resultsInput): void
    {
        $errors = [];
        $ageYears = Age::inYears($report->patient_age_value, $report->patient_age_unit);

        foreach ($report->items()->get() as $item) {
            if (! array_key_exists($item->id, $resultsInput)) {
                // Newly added tests keep their defaults; still refresh flags.
                [$flags, $abnormal] = $this->evaluator->evaluate($item->schema_snapshot, $item->results ?? [], $report->patient_sex, $ageYears);
                $item->forceFill(['flags' => $flags, 'abnormal_count' => $abnormal])->save();

                continue;
            }

            [$results, $itemErrors] = $this->resultValidator->validate(
                $item->schema_snapshot,
                (array) $resultsInput[$item->id],
                strict: false,
            );

            foreach ($itemErrors as $key => $message) {
                $errors["results.{$item->id}.{$key}"] = $message;
            }

            [$flags, $abnormal] = $this->evaluator->evaluate($item->schema_snapshot, $results, $report->patient_sex, $ageYears);
            $item->forceFill(['results' => $results, 'flags' => $flags, 'abnormal_count' => $abnormal])->save();
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $report->abnormal_count = (int) $report->items()->sum('abnormal_count');
    }

    /**
     * Re-evaluate flags against the final demographics.
     */
    private function recalculate(Report $report): void
    {
        $ageYears = Age::inYears($report->patient_age_value, $report->patient_age_unit);
        $total = 0;

        foreach ($report->items as $item) {
            [$flags, $abnormal] = $this->evaluator->evaluate($item->schema_snapshot, $item->results ?? [], $report->patient_sex, $ageYears);
            $item->forceFill(['flags' => $flags, 'abnormal_count' => $abnormal])->save();
            $total += $abnormal;
        }

        $report->abnormal_count = $total;
    }

    /**
     * @return Collection<int, TestDefinition> keyed by id, in requested order
     *
     * @throws ValidationException
     */
    private function activeDefinitions(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            throw ValidationException::withMessages(['test_ids' => 'Select at least one test.']);
        }

        $definitions = TestDefinition::query()->active()->whereIn('id', $ids)->get()->keyBy('id');

        if ($definitions->count() !== count($ids)) {
            throw ValidationException::withMessages(['test_ids' => 'One or more selected tests are inactive or no longer exist.']);
        }

        return collect($ids)->mapWithKeys(fn ($id) => [$id => $definitions->get($id)]);
    }

    private function patientData(array $data): array
    {
        return [
            'patient_id' => $data['patient_id'] ?? null,
            'name' => $data['patient_name'],
            'age_value' => $data['age_value'] ?? null,
            'age_unit' => $data['age_unit'] ?? null,
            'sex' => $data['sex'] ?? 'unspecified',
            'phone' => $data['phone'] ?? null,
        ];
    }

    private function patientSnapshot($patient): array
    {
        return [
            'patient_name' => $patient->name,
            'patient_age_value' => $patient->age_value,
            'patient_age_unit' => $patient->age_unit,
            'patient_sex' => $patient->sex,
        ];
    }

    private function reportNumber(Report $report): string
    {
        return sprintf(
            '%s-%s-%05d',
            config('lab.report_prefix', 'ACP'),
            ($report->registered_at ?? now())->format('ym'),
            $report->id
        );
    }
}
