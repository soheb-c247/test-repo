<?php

namespace App\Services;

use App\Support\FormulaEvaluator;
use Illuminate\Support\Carbon;

/**
 * Validates submitted results against a report item's schema snapshot and
 * returns the normalized values to store.
 *
 * - Blank optional values are stored as null, never coerced to zero.
 * - Draft saves accept missing required values but still reject malformed
 *   ones; finalization ($strict) additionally requires every required value.
 * - Calculated parameters are computed here and never taken from input.
 */
class ReportResultValidationService
{
    public function __construct(private readonly ResultEvaluator $evaluator) {}

    /**
     * @param  array<string, mixed>  $input  raw values keyed by parameter key
     * @return array{0: array<string, mixed>, 1: array<string, string>} [results, errors keyed by parameter]
     */
    public function validate(array $schema, array $input, bool $strict): array
    {
        $results = [];
        $errors = [];
        $calculated = [];

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['parameters'] ?? [] as $parameter) {
                $key = $parameter['key'];

                if (! empty($parameter['formula'])) {
                    $calculated[] = $parameter;

                    continue;
                }

                [$value, $error] = $this->validateValue($parameter, $input[$key] ?? null);

                if ($error === null && $strict && $parameter['required'] && $this->isBlank($value)) {
                    $error = 'Required.';
                }

                if ($error !== null) {
                    $errors[$key] = $error;
                }

                $results[$key] = $value;
            }
        }

        foreach ($calculated as $parameter) {
            $value = FormulaEvaluator::evaluate($parameter['formula'], $results);
            $results[$parameter['key']] = $value === null
                ? null
                : $this->evaluator->number($value, $parameter['precision'] ?? 2);
        }

        return [$results, $errors];
    }

    /**
     * @return array{0: mixed, 1: ?string}
     */
    private function validateValue(array $p, mixed $raw): array
    {
        $type = $p['type'];

        if ($type === 'table') {
            return $this->validateTable($p, $raw);
        }

        if ($type === 'multiselect') {
            $values = array_values(array_filter(array_map(
                fn ($v) => is_scalar($v) ? trim((string) $v) : '',
                is_array($raw) ? $raw : ($raw === null || $raw === '' ? [] : [$raw])
            ), fn ($v) => $v !== ''));
            foreach ($values as $v) {
                if (! in_array($v, $p['options'] ?? [], true)) {
                    return [null, 'Choose from the listed options.'];
                }
            }

            return [$values === [] ? null : array_values(array_unique($values)), null];
        }

        if (is_array($raw) || (! is_scalar($raw) && $raw !== null)) {
            return [null, 'Invalid value.'];
        }

        $value = $raw === null ? '' : trim((string) $raw);
        if ($value === '') {
            return [null, null];
        }

        switch ($type) {
            case 'integer':
                if (! preg_match('/^-?\d+$/', $value)) {
                    return [$value, 'Enter a whole number.'];
                }

                return $this->checkBounds($p, $value);

            case 'decimal':
            case 'numeric':
                $value = str_replace(',', '', $value);
                if (! preg_match('/^-?(\d+(\.\d*)?|\.\d+)$/', $value)) {
                    return [$value, 'Enter a number.'];
                }
                if (str_starts_with($value, '.') || str_starts_with($value, '-.')) {
                    $value = str_replace('.', '0.', $value);
                }
                $value = rtrim($value, '.');

                return $this->checkBounds($p, $value);

            case 'select':
            case 'qualitative':
                if (! in_array($value, $p['options'] ?? [], true)) {
                    return [$value, 'Choose from the listed options.'];
                }

                return [$value, null];

            case 'boolean':
                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === null) {
                    return [null, 'Choose Yes or No.'];
                }

                return [$bool ? 'yes' : 'no', null];

            case 'date':
                try {
                    $date = Carbon::createFromFormat('!Y-m-d', $value);
                } catch (\Throwable) {
                    $date = null;
                }
                if (! $date || $date->format('Y-m-d') !== $value) {
                    return [$value, 'Enter a valid date.'];
                }

                return [$value, null];

            case 'time':
                if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
                    return [$value, 'Enter a valid time (HH:MM).'];
                }

                return [$value, null];

            default: // text, textarea
                $max = $p['max_length'] ?? 255;
                if (mb_strlen($value) > $max) {
                    return [mb_substr($value, 0, $max), "May not be longer than {$max} characters."];
                }

                return [$value, null];
        }
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function checkBounds(array $p, string $value): array
    {
        $number = (float) $value;
        if (($p['min_value'] ?? null) !== null && $number < $p['min_value']) {
            return [$value, 'Must be at least '.$this->evaluator->number($p['min_value'], null).'.'];
        }
        if (($p['max_value'] ?? null) !== null && $number > $p['max_value']) {
            return [$value, 'Must be at most '.$this->evaluator->number($p['max_value'], null).'.'];
        }

        return [$value, null];
    }

    /**
     * Table values are stored as a list of rows:
     *   [ { "key": "o", "label": "S.Typhi \"O\"", "values": { "titre": "1:80" } }, ... ]
     *
     * Predefined rows keep their schema labels; extra rows (when allowed)
     * must carry a label. Rows with no values are dropped.
     *
     * @return array{0: ?array, 1: ?string}
     */
    private function validateTable(array $p, mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $columns = $p['columns'] ?? [];
        $rows = [];
        $error = null;

        $readValues = function (array $source) use ($columns, &$error): array {
            $values = [];
            foreach ($columns as $column) {
                $v = $source[$column['key']] ?? null;
                $v = is_scalar($v) ? trim((string) $v) : '';
                if ($v === '') {
                    continue;
                }
                if ($column['type'] === 'number' && ! is_numeric($v)) {
                    $error = "\"{$column['label']}\" must be a number.";
                }
                if ($column['type'] === 'select' && ! in_array($v, $column['options'] ?? [], true)) {
                    $error = "\"{$column['label']}\" has an invalid choice.";
                }
                $values[$column['key']] = mb_substr($v, 0, 255);
            }

            return $values;
        };

        foreach ($p['rows'] ?? [] as $row) {
            $values = $readValues((array) ($raw['rows'][$row['key']] ?? []));
            if ($values !== []) {
                $rows[] = ['key' => $row['key'], 'label' => $row['label'], 'values' => $values];
            }
        }

        if ($p['allow_add_rows'] ?? false) {
            foreach (array_slice((array) ($raw['extra'] ?? []), 0, 100) as $extra) {
                if (! is_array($extra)) {
                    continue;
                }
                $label = trim((string) ($extra['label'] ?? ''));
                $values = $readValues((array) ($extra['values'] ?? []));
                if ($label === '' && $values === []) {
                    continue;
                }
                if ($label === '') {
                    $error = 'Every added row needs a name.';

                    continue;
                }
                $rows[] = ['key' => null, 'label' => mb_substr($label, 0, 100), 'values' => $values];
            }
        }

        return [$rows === [] ? null : $rows, $error];
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
