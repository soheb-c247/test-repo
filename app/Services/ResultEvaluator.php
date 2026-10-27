<?php

namespace App\Services;

use App\Support\ParameterTypes;
use Illuminate\Support\Carbon;

/**
 * Compares numeric results against configured reference intervals and formats
 * values / intervals for display.
 *
 * Flags are informational only:
 *   - "low" / "high"  value outside the applicable interval
 *   - "normal"         value inside the applicable interval
 *   - null             not evaluated (non-numeric, blank, or no applicable
 *                      interval for this patient's sex/age)
 *
 * Qualitative results are never flagged and no interpretation is inferred.
 */
class ResultEvaluator
{
    public const FLAG_LABELS = [
        'low' => 'Low',
        'high' => 'High',
        'normal' => 'Normal',
    ];

    /**
     * Pick the most specific reference range that applies to the patient.
     * A range with age limits only applies when the age is known; a
     * sex-specific range only applies when the sex matches.
     */
    public function applicableRange(array $parameter, ?string $sex, ?float $ageYears): ?array
    {
        $best = null;
        $bestScore = -1;

        foreach ($parameter['reference_ranges'] ?? [] as $range) {
            $rangeSex = $range['sex'] ?? 'any';
            if ($rangeSex !== 'any' && $rangeSex !== $sex) {
                continue;
            }

            $hasAge = ($range['age_min'] ?? null) !== null || ($range['age_max'] ?? null) !== null;
            if ($hasAge) {
                if ($ageYears === null) {
                    continue;
                }
                if (($range['age_min'] ?? null) !== null && $ageYears < $range['age_min']) {
                    continue;
                }
                if (($range['age_max'] ?? null) !== null && $ageYears >= $range['age_max']) {
                    continue;
                }
            }

            $score = ($rangeSex !== 'any' ? 2 : 0) + ($hasAge ? 1 : 0);
            if ($score > $bestScore) {
                $best = $range;
                $bestScore = $score;
            }
        }

        return $best;
    }

    public function flag(array $parameter, mixed $value, ?string $sex, ?float $ageYears): ?string
    {
        if (! ParameterTypes::isNumeric($parameter['type'] ?? '') || $value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $range = $this->applicableRange($parameter, $sex, $ageYears);
        if (! $range) {
            return null;
        }

        $number = (float) $value;
        if ($range['min'] !== null && $number < (float) $range['min']) {
            return 'low';
        }
        if ($range['max'] !== null && $number > (float) $range['max']) {
            return 'high';
        }

        return 'normal';
    }

    /**
     * Evaluate every numeric parameter of a schema.
     *
     * @return array{0: array<string, string>, 1: int} [flags keyed by parameter, abnormal count]
     */
    public function evaluate(array $schema, array $results, ?string $sex, ?float $ageYears): array
    {
        $flags = [];
        $abnormal = 0;

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['parameters'] ?? [] as $parameter) {
                $flag = $this->flag($parameter, $results[$parameter['key']] ?? null, $sex, $ageYears);
                if ($flag !== null) {
                    $flags[$parameter['key']] = $flag;
                    if ($flag !== 'normal') {
                        $abnormal++;
                    }
                }
            }
        }

        return [$flags, $abnormal];
    }

    /**
     * Human-readable biological reference interval for a parameter.
     */
    public function referenceLabel(array $parameter, ?string $sex = null, ?float $ageYears = null, bool $patientKnown = true): string
    {
        if (! empty($parameter['reference_text'])) {
            return $parameter['reference_text'];
        }

        $ranges = $parameter['reference_ranges'] ?? [];
        if ($ranges === []) {
            return '';
        }

        $precision = $parameter['precision'] ?? null;

        if ($patientKnown) {
            $range = $this->applicableRange($parameter, $sex, $ageYears);
            if ($range) {
                return $this->formatRange($range, $precision);
            }
        }

        // No single applicable range: list them all with their qualifiers.
        return collect($ranges)
            ->map(fn ($range) => $this->qualifier($range).$this->formatRange($range, $precision))
            ->implode('; ');
    }

    public function formatRange(array $range, ?int $precision): string
    {
        $min = $range['min'] ?? null;
        $max = $range['max'] ?? null;

        if ($min !== null && $max !== null) {
            return $this->number($min, $precision).' - '.$this->number($max, $precision);
        }
        if ($max !== null) {
            return '≤ '.$this->number($max, $precision);
        }
        if ($min !== null) {
            return '≥ '.$this->number($min, $precision);
        }

        return '';
    }

    /**
     * Format a stored result for display. Blank values stay blank.
     */
    public function formatValue(array $parameter, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return match ($parameter['type'] ?? 'text') {
            'numeric', 'decimal', 'integer' => is_numeric($value)
                ? $this->number($value, $parameter['precision'] ?? null)
                : (string) $value,
            'boolean' => $value === true || $value === 'yes' || $value === '1' || $value === 1 ? 'Yes' : 'No',
            'multiselect' => implode(', ', (array) $value),
            'date' => $this->date($value),
            'table' => '',
            default => is_array($value) ? implode(', ', $value) : (string) $value,
        };
    }

    public function number(mixed $value, ?int $precision): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        if ($precision === null) {
            // Preserve what was entered; avoid float noise such as 0.30000000000000004.
            $formatted = rtrim(rtrim(sprintf('%.6F', (float) $value), '0'), '.');

            return $formatted === '-0' ? '0' : $formatted;
        }

        return number_format((float) $value, $precision, '.', '');
    }

    private function qualifier(array $range): string
    {
        $parts = [];
        if (($range['label'] ?? null) !== null) {
            $parts[] = $range['label'];
        } else {
            if (($range['sex'] ?? 'any') !== 'any') {
                $parts[] = ucfirst($range['sex']);
            }
            $ageMin = $range['age_min'] ?? null;
            $ageMax = $range['age_max'] ?? null;
            if ($ageMin !== null && $ageMax !== null) {
                $parts[] = $this->number($ageMin, null).'–'.$this->number($ageMax, null).' y';
            } elseif ($ageMin !== null) {
                $parts[] = '≥'.$this->number($ageMin, null).' y';
            } elseif ($ageMax !== null) {
                $parts[] = '<'.$this->number($ageMax, null).' y';
            }
        }

        return $parts ? implode(' ', $parts).': ' : '';
    }

    private function date(mixed $value): string
    {
        try {
            return Carbon::parse((string) $value)->format('d M Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
