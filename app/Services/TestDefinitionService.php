<?php

namespace App\Services;

use App\Models\TestDefinition;
use App\Models\TestDefinitionVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Business rules for laboratory test definitions.
 *
 * Every change to the reportable content of a definition (schema, units,
 * reference intervals, names, notes...) increments its version and stores a
 * full snapshot in test_definition_versions. Reports keep their own snapshot,
 * so editing a definition never alters an existing report.
 */
class TestDefinitionService
{
    /** Attributes whose change produces a new definition version. */
    private const VERSIONED = [
        'code', 'name', 'category', 'description', 'sample_type', 'collection_instructions',
        'preparation_instructions', 'method', 'reporting_notes', 'price', 'turnaround_hours', 'schema',
    ];

    public function __construct(
        private readonly TestSchemaValidator $schemaValidator,
        private readonly AuditLogger $audit,
    ) {}

    public function create(array $attributes): TestDefinition
    {
        $attributes['schema'] = $this->schemaValidator->validate($attributes['schema'] ?? null);
        $attributes['code'] = Str::upper(trim($attributes['code']));

        return DB::transaction(function () use ($attributes) {
            $definition = new TestDefinition($attributes);
            $definition->version = 1;
            $definition->save();

            $this->storeVersion($definition);
            $this->audit->record('test.created', $definition, ['code' => $definition->code]);

            return $definition;
        });
    }

    public function update(TestDefinition $definition, array $attributes): TestDefinition
    {
        $attributes['schema'] = $this->schemaValidator->validate($attributes['schema'] ?? null);
        $attributes['code'] = Str::upper(trim($attributes['code']));

        return DB::transaction(function () use ($definition, $attributes) {
            $definition = TestDefinition::query()->lockForUpdate()->findOrFail($definition->id);
            $before = $this->versionedPayload($definition);

            $definition->fill($attributes);
            $changed = $this->versionedPayload($definition) != $before;

            if ($changed) {
                $definition->version = $definition->version + 1;
            }
            $definition->save();

            if ($changed) {
                $this->storeVersion($definition);
            }

            $this->audit->record('test.updated', $definition, [
                'code' => $definition->code,
                'version' => $definition->version,
                'new_version' => $changed,
            ]);

            return $definition;
        });
    }

    public function setActive(TestDefinition $definition, bool $active): TestDefinition
    {
        $definition->forceFill(['is_active' => $active])->save();
        $this->audit->record($active ? 'test.activated' : 'test.deactivated', $definition, ['code' => $definition->code]);

        return $definition;
    }

    public function duplicate(TestDefinition $source): TestDefinition
    {
        $attributes = Arr::only($source->getAttributes(), [
            'name', 'category', 'description', 'sample_type', 'collection_instructions',
            'preparation_instructions', 'method', 'reporting_notes', 'price', 'turnaround_hours',
        ]);
        $attributes['name'] = Str::limit($source->name.' (copy)', 150, '');
        $attributes['code'] = $this->uniqueCode($source->code.'-COPY');
        $attributes['schema'] = $source->schema;
        $attributes['is_active'] = false;
        $attributes['needs_review'] = $source->needs_review;
        $attributes['review_notes'] = $source->review_notes;

        $copy = $this->create($attributes);
        $this->audit->record('test.duplicated', $copy, ['from' => $source->code]);

        return $copy;
    }

    /**
     * Archive (soft delete). Referenced definitions remain resolvable from
     * their reports; nothing is ever hard-deleted.
     */
    public function archive(TestDefinition $definition): void
    {
        DB::transaction(function () use ($definition) {
            $definition->forceFill(['is_active' => false])->save();
            $definition->delete();
            $this->audit->record('test.archived', $definition, ['code' => $definition->code]);
        });
    }

    public function restore(TestDefinition $definition): void
    {
        $definition->restore();
        $this->audit->record('test.restored', $definition, ['code' => $definition->code]);
    }

    public function markReviewed(TestDefinition $definition): void
    {
        $definition->forceFill(['needs_review' => false])->save();
        $this->audit->record('test.reviewed', $definition, ['code' => $definition->code]);
    }

    public function uniqueCode(string $base): string
    {
        $base = Str::upper(Str::limit(preg_replace('/[^A-Za-z0-9\-_]/', '', $base), 26, ''));
        $code = $base;
        $i = 2;

        while (TestDefinition::withTrashed()->where('code', $code)->exists()) {
            $code = $base.'-'.$i++;
        }

        return $code;
    }

    /**
     * Full snapshot of everything a report needs from this definition.
     */
    public function snapshot(TestDefinition $definition): array
    {
        return [
            'id' => $definition->id,
            'version' => $definition->version,
            'code' => $definition->code,
            'name' => $definition->name,
            'category' => $definition->category,
            'description' => $definition->description,
            'sample_type' => $definition->sample_type,
            'collection_instructions' => $definition->collection_instructions,
            'preparation_instructions' => $definition->preparation_instructions,
            'method' => $definition->method,
            'reporting_notes' => $definition->reporting_notes,
            'price' => $definition->price,
            'turnaround_hours' => $definition->turnaround_hours,
            'schema' => $definition->schema,
        ];
    }

    private function storeVersion(TestDefinition $definition): void
    {
        TestDefinitionVersion::create([
            'test_definition_id' => $definition->id,
            'version' => $definition->version,
            'snapshot' => $this->snapshot($definition),
            'created_by' => Auth::id(),
        ]);
    }

    private function versionedPayload(TestDefinition $definition): array
    {
        $payload = [];
        foreach (self::VERSIONED as $attribute) {
            $value = $definition->getAttribute($attribute);
            $payload[$attribute] = $attribute === 'price' && $value !== null ? (string) round((float) $value, 2) : $value;
        }

        return $payload;
    }
}
