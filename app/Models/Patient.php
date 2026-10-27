<?php

namespace App\Models;

use App\Support\Age;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    public const SEXES = [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
        'unspecified' => 'Not specified',
    ];

    protected $table = 'lab_patients';

    protected $fillable = [
        'name',
        'age_value',
        'age_unit',
        'date_of_birth',
        'sex',
        'phone',
        'email',
        'address',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'age_value' => 'integer',
            'is_demo' => 'boolean',
        ];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
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
                ->orWhere('patient_code', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhereHas('reports', fn (Builder $reports) => $reports->where('report_number', 'like', $like));
        });
    }

    public function getAgeLabelAttribute(): string
    {
        return Age::label($this->age_value, $this->age_unit, $this->date_of_birth);
    }

    public function getSexLabelAttribute(): string
    {
        return self::SEXES[$this->sex] ?? 'Not specified';
    }
}
