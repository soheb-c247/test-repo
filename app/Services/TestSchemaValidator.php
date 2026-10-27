<?php

namespace App\Services;

use App\Support\FormulaEvaluator;
use App\Support\ParameterTypes;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Validates and normalizes the JSON parameter schema of a test definition.
 *
 * Normalized structure (schema_version 1):
 *
 * {
 *   "schema_version": 1,
 *   "sections": [
 *     { "key": "hemoglobin", "title": "Hemoglobin", "order": 1,
 *       "parameters": [
 *         { "key": "hb", "label": "Haemoglobin (Hb)", "type": "numeric",
 *           "required": true, "unit": "gm/dL", "precision": 1,
 *           "reference_ranges": [ { "min": 12, "max": 15.6, "sex": "any",
 *                                   "age_min": null, "age_max": null } ],
 *           "reference_text": null, "method": null, "help": null,
 *           "default": null, "show_in_pdf": true, "order": 1, ... } ] } ]
 * }
 *
 * Unknown properties are dropped; anything malformed is rejected.
 *
 * Errors are reported twice:
 *  - under the "schema" key as readable sentences naming the section and
 *    parameter (used for the summary);
 *  - under "schema.sections.{i}.parameters.{j}.{field}" with a short message,
 *    so the parameter builder can show it beside the offending input.
 *
 * resources/js/schema-builder.js mirrors these rules for instant feedback.
 */
class TestSchemaValidator
{
    public const SCHEMA_VERSION = 1;

    public const MAX_PRECISION = 6;

    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,49}$/';

    /** @var list<string> summary messages */
    private array $errors = [];

    /** @var array<string, string> short messages keyed by field path */
    private array $fieldErrors = [];

    /**
     * @throws ValidationException
     */
    public function validate(mixed $schema, string $field = 'schema'): array
    {
        $this->errors = [];
        $this->fieldErrors = [];

        if (is_string($schema)) {
            $decoded = json_decode($schema, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([$field => ['The parameter schema is not valid JSON.']]);
            }
            $schema = $decoded;
        }

        if (! is_array($schema)) {
            throw ValidationException::withMessages([$field => ['The parameter schema must be a JSON object.']]);
        }

        $normalized = $this->normalize($schema);

        if ($this->errors) {
            $messages = [$field => array_values(array_unique($this->errors))];
            foreach ($this->fieldErrors as $path => $message) {
                $messages["{$field}.{$path}"] = [$message];
            }

            throw ValidationException::withMessages($messages);
        }

        return $normalized;
    }

    /**
     * Returns true when the schema is valid, without throwing.
     */
    public function passes(mixed $schema): bool
    {
        try {
            $this->validate($schema);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Record an error. $where names the location for the summary, $path is
     * the field path (null for schema-wide problems), $message is the short
     * field-level message.
     */
    private function fail(string $where, ?string $path, string $message): void
    {
        $this->errors[] = $where !== '' ? "{$where}: {$message}" : $message;

        if ($path !== null && ! isset($this->fieldErrors[$path])) {
            $this->fieldErrors[$path] = ucfirst($message);
        }
    }

    private function normalize(array $schema): array
    {
        $version = $schema['schema_version'] ?? self::SCHEMA_VERSION;
        if ((int) $version !== self::SCHEMA_VERSION) {
            $this->fail('', null, 'Unsupported schema_version; expected '.self::SCHEMA_VERSION.'.');
        }

        $sections = $schema['sections'] ?? null;
        if (! is_array($sections) || ! array_is_list($sections) || $sections === []) {
            $this->fail('', null, 'Add at least one section with at least one parameter.');

            return ['schema_version' => self::SCHEMA_VERSION, 'sections' => []];
        }

        if (count($sections) > 30) {
            $this->fail('', null, 'A test may have at most 30 sections.');
        }

        $sectionKeys = [];
        $parameterKeys = [];
        $numericKeys = [];
        $formulas = [];
        $out = [];
        $parameterTotal = 0;

        foreach (array_values($sections) as $i => $section) {
            $sPath = "sections.{$i}";
            $where = 'Section '.($i + 1);

            if (! is_array($section)) {
                $this->fail($where, null, 'is malformed.');

                continue;
            }

            $title = $this->string($section['title'] ?? '', 120, $where, "{$sPath}.title", 'title');
            $sName = $title !== '' ? "Section “{$title}”" : $where;
            $key = (string) ($section['key'] ?? '');
            if ($key === '') {
                $key = $this->slug($title !== '' ? $title : 'section_'.($i + 1));
            }
            if (! preg_match(self::KEY_PATTERN, $key)) {
                $this->fail($sName, "{$sPath}.key", "key “{$key}” must start with a letter and use only a–z, 0–9 and _");
            } elseif (isset($sectionKeys[$key])) {
                $this->fail($sName, "{$sPath}.title", "another section already uses the key “{$key}” — give it a different title");
            }
            $sectionKeys[$key] = true;

            $parameters = $section['parameters'] ?? [];
            if (! is_array($parameters) || ! array_is_list($parameters)) {
                $this->fail($sName, null, 'parameters must be a list.');
                $parameters = [];
            }
            if ($parameters === []) {
                $this->fail($sName, "{$sPath}.parameters", 'add at least one parameter to this section');
            }

            $normalizedParameters = [];
            foreach ($parameters as $j => $parameter) {
                $pPath = "{$sPath}.parameters.{$j}";
                $label = is_array($parameter) ? trim((string) ($parameter['label'] ?? '')) : '';
                $pWhere = ($title !== '' ? $title : $where).' › '.($label !== '' ? $label : 'Parameter '.($j + 1));

                if (! is_array($parameter)) {
                    $this->fail($pWhere, null, 'is malformed.');

                    continue;
                }

                $normalizedParameter = $this->normalizeParameter($parameter, $pWhere, $pPath, $j + 1);

                $pKey = $normalizedParameter['key'];
                if (isset($parameterKeys[$pKey])) {
                    $this->fail($pWhere, "{$pPath}.key", "key “{$pKey}” is already used by {$parameterKeys[$pKey]} — keys must be unique within the test");
                } else {
                    $parameterKeys[$pKey] = $label !== '' ? "“{$label}”" : 'another parameter';
                }

                if (ParameterTypes::isNumeric($normalizedParameter['type'])) {
                    $numericKeys[$pKey] = true;
                }
                if (! empty($normalizedParameter['formula'])) {
                    $formulas[$pKey] = [$normalizedParameter['formula'], $pWhere, $pPath];
                }

                $normalizedParameters[] = $normalizedParameter;
                $parameterTotal++;
            }

            $out[] = [
                'key' => $key,
                'title' => $title,
                'order' => $i + 1,
                'parameters' => $normalizedParameters,
            ];
        }

        if ($parameterTotal > 200) {
            $this->fail('', null, 'A test may have at most 200 parameters.');
        }

        foreach ($formulas as $key => [$formula, $pWhere, $pPath]) {
            try {
                $variables = FormulaEvaluator::variables($formula);
            } catch (InvalidArgumentException $e) {
                $this->fail($pWhere, "{$pPath}.formula", 'invalid formula — '.lcfirst(rtrim($e->getMessage(), '.')));

                continue;
            }
            foreach ($variables as $variable) {
                if ($variable === $key) {
                    $this->fail($pWhere, "{$pPath}.formula", 'a formula cannot use its own parameter');
                } elseif (! isset($numericKeys[$variable])) {
                    $this->fail($pWhere, "{$pPath}.formula", "formula uses “{$variable}”, which is not the key of a numeric parameter in this test");
                } elseif (isset($formulas[$variable])) {
                    $this->fail($pWhere, "{$pPath}.formula", "formula uses “{$variable}”, which is itself calculated");
                }
            }
        }

        return ['schema_version' => self::SCHEMA_VERSION, 'sections' => $out];
    }

    private function normalizeParameter(array $p, string $where, string $path, int $order): array
    {
        $label = $this->string($p['label'] ?? '', 150, $where, "{$path}.label", 'label');
        if ($label === '') {
            $this->fail($where, "{$path}.label", 'label is required');
        }

        $key = (string) ($p['key'] ?? '');
        if ($key === '') {
            $key = $this->slug($label);
        }
        if (! preg_match(self::KEY_PATTERN, $key)) {
            $this->fail($where, "{$path}.key", "key “{$key}” must start with a letter and use only a–z, 0–9 and _ (max 50)");
        }

        $type = (string) ($p['type'] ?? '');
        if (! ParameterTypes::exists($type)) {
            $this->fail($where, "{$path}.type", "unsupported input type “{$type}”");
            $type = 'text';
        }

        $out = [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $this->bool($p['required'] ?? false),
            'unit' => $this->nullableString($p['unit'] ?? null, 40, $where, "{$path}.unit", 'unit'),
            'method' => $this->nullableString($p['method'] ?? null, 191, $where, "{$path}.method", 'method'),
            'help' => $this->nullableString($p['help'] ?? null, 500, $where, "{$path}.help", 'help text'),
            'reference_text' => $this->nullableString($p['reference_text'] ?? null, 255, $where, "{$path}.reference_text", 'reference text'),
            'show_in_pdf' => $this->bool($p['show_in_pdf'] ?? true),
            'order' => $order,
        ];

        // Defaults are plain scalars (or a list for multi-select).
        $default = $p['default'] ?? null;
        if (is_array($default)) {
            $default = $type === 'multiselect' ? array_values(array_map('strval', $default)) : null;
        } elseif ($default !== null) {
            $default = $this->nullableString((string) $default, 255, $where, "{$path}.default", 'default value');
        }
        $out['default'] = $default === '' ? null : $default;

        if (ParameterTypes::hasOptions($type)) {
            $out['options'] = $this->options($p['options'] ?? [], $where, "{$path}.options");
            if ($out['default'] !== null) {
                $defaults = is_array($out['default']) ? $out['default'] : [$out['default']];
                foreach ($defaults as $d) {
                    if (! in_array($d, $out['options'], true)) {
                        $this->fail($where, "{$path}.default", "default value “{$d}” is not one of the options");
                    }
                }
            }
        }

        if (ParameterTypes::isNumeric($type)) {
            $precision = $p['precision'] ?? null;
            if ($precision === '' || $precision === null) {
                $out['precision'] = $type === 'integer' ? 0 : null;
            } elseif (! is_numeric($precision) || (int) $precision != $precision || (int) $precision < 0 || (int) $precision > self::MAX_PRECISION) {
                $this->fail($where, "{$path}.precision", 'decimal places must be a whole number from 0 to '.self::MAX_PRECISION);
                $out['precision'] = null;
            } else {
                $out['precision'] = $type === 'integer' ? 0 : (int) $precision;
            }

            $out['min_value'] = $this->nullableNumber($p['min_value'] ?? null, $where, "{$path}.min_value", 'minimum allowed entry');
            $out['max_value'] = $this->nullableNumber($p['max_value'] ?? null, $where, "{$path}.max_value", 'maximum allowed entry');
            if ($out['min_value'] !== null && $out['max_value'] !== null && $out['min_value'] > $out['max_value']) {
                $this->fail($where, "{$path}.max_value", 'maximum allowed entry must be greater than the minimum');
            }

            $out['reference_ranges'] = $this->ranges($p['reference_ranges'] ?? [], $where, "{$path}.reference_ranges");

            $formula = trim((string) ($p['formula'] ?? ''));
            if (mb_strlen($formula) > 255) {
                $this->fail($where, "{$path}.formula", 'formula may not be longer than 255 characters');
            }
            $out['formula'] = $formula === '' ? null : $formula;
            if ($out['formula'] !== null && $out['required']) {
                // Calculated values are produced by the system, never typed.
                $out['required'] = false;
            }
        } else {
            $out['reference_ranges'] = [];
            $out['formula'] = null;
        }

        if (in_array($type, ['text', 'textarea'], true)) {
            $max = $p['max_length'] ?? null;
            $limit = $type === 'textarea' ? 5000 : 255;
            if ($max === null || $max === '') {
                $out['max_length'] = $limit;
            } elseif (! is_numeric($max) || (int) $max != $max || (int) $max < 1 || (int) $max > $limit) {
                $this->fail($where, "{$path}.max_length", "max length must be a whole number from 1 to {$limit}");
                $out['max_length'] = $limit;
            } else {
                $out['max_length'] = (int) $max;
            }
        }

        if ($type === 'table') {
            $out += $this->table($p, $where, $path);
        }

        return $out;
    }

    private function options(mixed $options, string $where, string $path): array
    {
        if (is_string($options)) {
            $options = preg_split('/\r\n|\r|\n|\|/', $options);
        }
        if (! is_array($options)) {
            $this->fail($where, $path, 'options must be a list');

            return [];
        }

        $clean = [];
        foreach ($options as $option) {
            if (! is_scalar($option)) {
                $this->fail($where, $path, 'every option must be text');

                continue;
            }
            $option = trim((string) $option);
            if ($option === '') {
                continue;
            }
            if (mb_strlen($option) > 100) {
                $this->fail($where, $path, 'option “'.mb_substr($option, 0, 20).'…” is longer than 100 characters');
            }
            if (in_array($option, $clean, true)) {
                $this->fail($where, $path, "option “{$option}” is listed twice");

                continue;
            }
            $clean[] = $option;
        }

        if ($clean === []) {
            $this->fail($where, $path, 'add at least one option (one per line)');
        }
        if (count($clean) > 100) {
            $this->fail($where, $path, 'at most 100 options are allowed');
        }

        return $clean;
    }

    private function ranges(mixed $ranges, string $where, string $path): array
    {
        if ($ranges === null || $ranges === '') {
            return [];
        }
        if (! is_array($ranges) || ! array_is_list($ranges)) {
            $this->fail($where, $path, 'reference intervals must be a list');

            return [];
        }
        if (count($ranges) > 20) {
            $this->fail($where, $path, 'at most 20 reference intervals are allowed');
        }

        $out = [];
        foreach ($ranges as $k => $range) {
            $rPath = "{$path}.{$k}";
            $rWhere = "{$where} (interval ".($k + 1).')';
            if (! is_array($range)) {
                $this->fail($rWhere, $rPath, 'interval is malformed');

                continue;
            }

            $min = $this->nullableNumber($range['min'] ?? null, $rWhere, "{$rPath}.min", 'low limit');
            $max = $this->nullableNumber($range['max'] ?? null, $rWhere, "{$rPath}.max", 'high limit');
            $ageMin = $this->nullableNumber($range['age_min'] ?? null, $rWhere, "{$rPath}.age_min", 'age from');
            $ageMax = $this->nullableNumber($range['age_max'] ?? null, $rWhere, "{$rPath}.age_max", 'age to');
            $sex = (string) ($range['sex'] ?? 'any');
            $sex = $sex === '' ? 'any' : $sex;

            if ($min === null && $max === null && ! isset($this->fieldErrors["{$rPath}.min"]) && ! isset($this->fieldErrors["{$rPath}.max"])) {
                $this->fail($rWhere, "{$rPath}.min", 'enter a low limit, a high limit or both');
            }
            if ($min !== null && $max !== null && $min > $max) {
                $this->fail($rWhere, "{$rPath}.max", 'high limit must be greater than the low limit');
            }
            if (! in_array($sex, ParameterTypes::SEXES, true)) {
                $this->fail($rWhere, "{$rPath}.sex", 'sex must be any, male or female');
                $sex = 'any';
            }
            if ($ageMin !== null && $ageMin < 0) {
                $this->fail($rWhere, "{$rPath}.age_min", 'age cannot be negative');
            }
            if ($ageMax !== null && $ageMax < 0) {
                $this->fail($rWhere, "{$rPath}.age_max", 'age cannot be negative');
            }
            if ($ageMin !== null && $ageMax !== null && $ageMin >= $ageMax) {
                $this->fail($rWhere, "{$rPath}.age_max", '“age to” must be greater than “age from”');
            }

            $out[] = [
                'min' => $min,
                'max' => $max,
                'sex' => $sex,
                'age_min' => $ageMin,
                'age_max' => $ageMax,
                'label' => $this->nullableString($range['label'] ?? null, 60, $rWhere, "{$rPath}.label", 'label'),
            ];
        }

        return $out;
    }

    private function table(array $p, string $where, string $path): array
    {
        $columns = $p['columns'] ?? [];
        $rows = $p['rows'] ?? [];
        $outColumns = [];
        $outRows = [];

        if (! is_array($columns) || ! array_is_list($columns) || $columns === []) {
            $this->fail($where, "{$path}.columns", 'a table needs at least one result column');
            $columns = [];
        }
        if (count($columns) > 8) {
            $this->fail($where, "{$path}.columns", 'a table may have at most 8 columns');
        }

        $columnKeys = [];
        foreach ($columns as $c => $column) {
            $cPath = "{$path}.columns.{$c}";
            $cWhere = "{$where} (column ".($c + 1).')';
            if (! is_array($column)) {
                $this->fail($cWhere, $cPath, 'column is malformed');

                continue;
            }
            $label = $this->string($column['label'] ?? '', 60, $cWhere, "{$cPath}.label", 'column label');
            if ($label === '') {
                $this->fail($cWhere, "{$cPath}.label", 'column label is required');
            }
            $key = (string) ($column['key'] ?? '') ?: $this->slug($label);
            if (! preg_match(self::KEY_PATTERN, $key)) {
                $this->fail($cWhere, "{$cPath}.label", 'column label must contain at least one letter');
            } elseif (isset($columnKeys[$key])) {
                $this->fail($cWhere, "{$cPath}.label", 'two columns have the same label');
            }
            $columnKeys[$key] = true;

            $type = (string) ($column['type'] ?? 'text');
            if (! array_key_exists($type, ParameterTypes::TABLE_COLUMN_TYPES)) {
                $this->fail($cWhere, "{$cPath}.type", "unsupported column type “{$type}”");
                $type = 'text';
            }

            $outColumn = ['key' => $key, 'label' => $label, 'type' => $type];
            if ($type === 'select') {
                $outColumn['options'] = $this->options($column['options'] ?? [], $cWhere, "{$cPath}.options");
            }
            $outColumns[] = $outColumn;
        }

        if (! is_array($rows) || ! array_is_list($rows)) {
            $this->fail($where, "{$path}.rows", 'table rows must be a list');
            $rows = [];
        }
        if (count($rows) > 100) {
            $this->fail($where, "{$path}.rows", 'a table may have at most 100 predefined rows');
        }

        $rowKeys = [];
        foreach ($rows as $r => $row) {
            $label = is_array($row) ? ($row['label'] ?? '') : $row;
            $label = $this->string($label, 100, $where, "{$path}.rows", 'row name');
            if ($label === '') {
                continue;
            }
            $key = (is_array($row) ? (string) ($row['key'] ?? '') : '') ?: $this->slug($label);
            if (! preg_match(self::KEY_PATTERN, $key)) {
                $key = 'row_'.($r + 1);
            }
            if (isset($rowKeys[$key])) {
                $key .= '_'.($r + 1);
            }
            $rowKeys[$key] = true;
            $outRows[] = ['key' => $key, 'label' => $label];
        }

        $allowAdd = $this->bool($p['allow_add_rows'] ?? false);
        if ($outRows === [] && ! $allowAdd) {
            $this->fail($where, "{$path}.rows", 'add predefined rows or allow rows to be added during result entry');
        }

        return [
            'columns' => $outColumns,
            'rows' => $outRows,
            'allow_add_rows' => $allowAdd,
            'row_header' => $this->nullableString($p['row_header'] ?? null, 60, $where, "{$path}.row_header", 'first column heading') ?? 'Item',
        ];
    }

    private function string(mixed $value, int $max, string $where, string $path, string $what): string
    {
        if (! is_scalar($value) && $value !== null) {
            $this->fail($where, $path, "{$what} must be text");

            return '';
        }
        $value = trim((string) $value);
        if (mb_strlen($value) > $max) {
            $this->fail($where, $path, "{$what} may not be longer than {$max} characters");
        }

        return $value;
    }

    private function nullableString(mixed $value, int $max, string $where, string $path, string $what): ?string
    {
        $value = $this->string($value, $max, $where, $path, $what);

        return $value === '' ? null : $value;
    }

    private function nullableNumber(mixed $value, string $where, string $path, string $what): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            $this->fail($where, $path, "{$what} must be a number");

            return null;
        }

        return $value + 0;
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function slug(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $text), '_'));
        if ($slug === '' || ! preg_match('/^[a-z]/', $slug)) {
            $slug = 'p_'.$slug;
        }

        return substr(rtrim($slug, '_'), 0, 50);
    }
}
