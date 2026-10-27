<?php

namespace App\Models;

use App\Support\Age;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINAL = 'final';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_FINAL => 'Finalized',
    ];

    protected $fillable = [
        'patient_id',
        'patient_name',
        'patient_age_value',
        'patient_age_unit',
        'patient_sex',
        'referring_doctor',
        'collected_by',
        'sample_id',
        'registered_at',
        'collected_at',
        'reported_at',
        'clinical_notes',
        'form_token',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'collected_at' => 'datetime',
            'reported_at' => 'datetime',
            'finalized_at' => 'datetime',
            'patient_age_value' => 'integer',
            'revision' => 'integer',
            'lock_version' => 'integer',
            'abnormal_count' => 'integer',
            'is_demo' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReportItem::class)->orderBy('position');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ReportRevision::class)->orderByDesc('revision');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class)->latest('id');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    /**
     * A draft that is being corrected after it was released at least once.
     */
    public function isUnderCorrection(): bool
    {
        return $this->isDraft() && $this->revision > 0;
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->isUnderCorrection()) {
            return 'Correction';
        }

        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getPatientAgeLabelAttribute(): string
    {
        return Age::label($this->patient_age_value, $this->patient_age_unit);
    }

    public function getPatientSexLabelAttribute(): string
    {
        return Patient::SEXES[$this->patient_sex] ?? 'Not specified';
    }

    public function getTestNamesAttribute(): string
    {
        return $this->items->pluck('test_name')->implode(', ');
    }
}
