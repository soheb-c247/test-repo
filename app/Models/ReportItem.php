<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportItem extends Model
{
    protected $fillable = [
        'test_definition_id',
        'definition_version',
        'test_code',
        'test_name',
        'category',
        'sample_type',
        'method',
        'reporting_notes',
        'price',
        'schema_snapshot',
        'results',
        'flags',
        'abnormal_count',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'schema_snapshot' => 'array',
            'results' => 'array',
            'flags' => 'array',
            'price' => 'decimal:2',
            'definition_version' => 'integer',
            'abnormal_count' => 'integer',
            'position' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(TestDefinition::class, 'test_definition_id')->withTrashed();
    }

    public function sections(): array
    {
        return $this->schema_snapshot['sections'] ?? [];
    }

    public function result(string $key): mixed
    {
        return $this->results[$key] ?? null;
    }

    public function flag(string $key): ?string
    {
        return $this->flags[$key] ?? null;
    }
}
