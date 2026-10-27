<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestDefinition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'sample_type',
        'collection_instructions',
        'preparation_instructions',
        'method',
        'reporting_notes',
        'price',
        'turnaround_hours',
        'is_active',
        'schema',
        'needs_review',
        'review_notes',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'is_active' => 'boolean',
            'needs_review' => 'boolean',
            'price' => 'decimal:2',
            'turnaround_hours' => 'integer',
            'version' => 'integer',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(TestDefinitionVersion::class)->orderByDesc('version');
    }

    public function reportItems(): HasMany
    {
        return $this->hasMany(ReportItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('category', 'like', $like);
        });
    }

    /**
     * Number of reportable parameters in the schema (table parameters count once).
     */
    public function getParameterCountAttribute(): int
    {
        return collect($this->schema['sections'] ?? [])
            ->sum(fn ($section) => count($section['parameters'] ?? []));
    }

    public function isReferenced(): bool
    {
        return $this->reportItems()->exists();
    }
}
