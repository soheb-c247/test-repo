<?php

namespace App\Support;

/**
 * Registry of the parameter input types a test definition may use.
 */
final class ParameterTypes
{
    public const TYPES = [
        'numeric' => 'Numeric result (unit + reference interval)',
        'decimal' => 'Decimal number',
        'integer' => 'Whole number',
        'text' => 'Short text',
        'textarea' => 'Long text',
        'qualitative' => 'Qualitative result (e.g. Positive / Negative)',
        'select' => 'Dropdown (single choice)',
        'multiselect' => 'Multi-select',
        'boolean' => 'Yes / No',
        'date' => 'Date',
        'time' => 'Time',
        'table' => 'Table / repeating rows',
    ];

    /** Types whose values are numbers and may carry units and reference intervals. */
    public const NUMERIC = ['numeric', 'decimal', 'integer'];

    /** Types that require a list of options. */
    public const WITH_OPTIONS = ['select', 'multiselect', 'qualitative'];

    /** Column types allowed inside a table parameter. */
    public const TABLE_COLUMN_TYPES = [
        'text' => 'Text',
        'number' => 'Number',
        'select' => 'Dropdown',
    ];

    public const SEXES = ['any', 'male', 'female'];

    public static function isNumeric(string $type): bool
    {
        return in_array($type, self::NUMERIC, true);
    }

    public static function hasOptions(string $type): bool
    {
        return in_array($type, self::WITH_OPTIONS, true);
    }

    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::TYPES);
    }
}
