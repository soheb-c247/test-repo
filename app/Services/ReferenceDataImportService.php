<?php

namespace App\Services;

use App\Models\TestDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Imports the reference test catalog (database/data/workbook_test_catalog.php)
 * built from the supplied Excel workbook.
 *
 * The import is repeatable and keyed by test code:
 *  - missing definitions are created (source = "workbook");
 *  - existing definitions are left untouched so administrator edits are
 *    never overwritten, unless $refresh is true and the definition is still
 *    an unmodified workbook definition, in which case a new version is saved.
 */
class ReferenceDataImportService
{
    public function __construct(private readonly TestDefinitionService $definitions) {}

    public static function catalogPath(): string
    {
        return database_path('data/workbook_test_catalog.php');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        return require self::catalogPath();
    }

    /**
     * @return array{created: list<string>, updated: list<string>, skipped: list<string>}
     */
    public function import(bool $refresh = false): array
    {
        $summary = ['created' => [], 'updated' => [], 'skipped' => []];

        DB::transaction(function () use ($refresh, &$summary) {
            foreach ($this->catalog() as $entry) {
                $attributes = $this->attributes($entry);
                $existing = TestDefinition::withTrashed()->where('code', $attributes['code'])->first();

                if (! $existing) {
                    $this->definitions->create($attributes);
                    $summary['created'][] = $attributes['code'];

                    continue;
                }

                if ($refresh && $existing->source === 'workbook' && ! $existing->trashed()) {
                    unset($attributes['is_active']);
                    $this->definitions->update($existing, $attributes);
                    $summary['updated'][] = $attributes['code'];

                    continue;
                }

                $summary['skipped'][] = $attributes['code'];
            }
        });

        return $summary;
    }

    private function attributes(array $entry): array
    {
        $review = $entry['review'] ?? null;

        return [
            'code' => $entry['code'],
            'name' => $entry['name'],
            'category' => $entry['category'],
            'description' => $entry['description'] ?? null,
            'sample_type' => $entry['sample_type'] ?? null,
            'collection_instructions' => $entry['collection_instructions'] ?? null,
            'preparation_instructions' => $entry['preparation_instructions'] ?? null,
            'method' => $entry['method'] ?? null,
            'reporting_notes' => $entry['reporting_notes'] ?? null,
            'price' => $entry['price'] ?? null,
            'turnaround_hours' => $entry['turnaround_hours'] ?? null,
            'is_active' => true,
            'schema' => $entry['schema'],
            'needs_review' => $review !== null,
            'review_notes' => trim(($review ? $review.' ' : '').'Source sheet(s): '.implode(', ', array_map('trim', $entry['sheets'] ?? [])).'.'),
            'source' => 'workbook',
        ];
    }
}
