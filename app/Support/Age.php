<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Patient age helpers. Ages are captured as a value plus unit (years, months
 * or days) because neonatal and paediatric reports need sub-year precision.
 */
final class Age
{
    public const UNITS = [
        'years' => 'Years',
        'months' => 'Months',
        'days' => 'Days',
    ];

    public static function label(?int $value, ?string $unit, ?CarbonInterface $dateOfBirth = null): string
    {
        if ($value === null && $dateOfBirth) {
            [$value, $unit] = self::fromDateOfBirth($dateOfBirth);
        }

        if ($value === null) {
            return '—';
        }

        $unit = $unit ?: 'years';
        $label = self::UNITS[$unit] ?? ucfirst($unit);

        if ($value === 1) {
            $label = rtrim($label, 's');
        }

        return $value.' '.$label;
    }

    /**
     * Age expressed in (fractional) years, used to match age-specific
     * reference intervals. Returns null when the age is unknown.
     */
    public static function inYears(?int $value, ?string $unit): ?float
    {
        if ($value === null) {
            return null;
        }

        return match ($unit) {
            'days' => $value / 365.25,
            'months' => $value / 12,
            default => (float) $value,
        };
    }

    /**
     * @return array{0:int,1:string}
     */
    public static function fromDateOfBirth(CarbonInterface $dob, ?CarbonInterface $at = null): array
    {
        $at = $at ? Carbon::instance($at) : Carbon::now();

        $years = (int) $dob->diffInYears($at);
        if ($years >= 1) {
            return [$years, 'years'];
        }

        $months = (int) $dob->diffInMonths($at);
        if ($months >= 1) {
            return [$months, 'months'];
        }

        return [max(0, (int) $dob->diffInDays($at)), 'days'];
    }
}
