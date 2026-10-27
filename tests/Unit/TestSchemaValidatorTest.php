<?php

namespace Tests\Unit;

use App\Services\TestSchemaValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TestSchemaValidatorTest extends TestCase
{
    private function validate(mixed $schema): array
    {
        return (new TestSchemaValidator)->validate($schema);
    }

    private function errorsFor(mixed $schema): array
    {
        try {
            $this->validate($schema);
        } catch (ValidationException $e) {
            return $e->errors()['schema'];
        }

        $this->fail('Schema was expected to be rejected.');
    }

    private function schemaWith(array $parameters): array
    {
        return ['schema_version' => 1, 'sections' => [['title' => 'Results', 'parameters' => $parameters]]];
    }

    public function test_valid_schema_is_normalized(): void
    {
        $schema = $this->validate($this->schemaWith([
            ['label' => 'Haemoglobin (Hb)', 'type' => 'numeric', 'unit' => 'g/dL', 'precision' => '1', 'required' => '1',
                'reference_ranges' => [['min' => '12', 'max' => '15.6', 'sex' => 'female', 'unknown' => 'dropped']]],
            ['label' => 'Result', 'type' => 'qualitative', 'options' => "Negative\nPositive"],
        ]));

        $p = $schema['sections'][0]['parameters'];
        $this->assertSame('results', $schema['sections'][0]['key']);
        $this->assertSame('haemoglobin_hb', $p[0]['key']);
        $this->assertTrue($p[0]['required']);
        $this->assertSame(1, $p[0]['precision']);
        $this->assertSame(12, $p[0]['reference_ranges'][0]['min']);
        $this->assertSame(15.6, $p[0]['reference_ranges'][0]['max']);
        $this->assertArrayNotHasKey('unknown', $p[0]['reference_ranges'][0]);
        $this->assertSame(['Negative', 'Positive'], $p[1]['options']);
    }

    public function test_all_supported_types_are_accepted(): void
    {
        $schema = $this->validate($this->schemaWith([
            ['key' => 'a', 'label' => 'Text', 'type' => 'text'],
            ['key' => 'b', 'label' => 'Integer', 'type' => 'integer'],
            ['key' => 'c', 'label' => 'Decimal', 'type' => 'decimal'],
            ['key' => 'd', 'label' => 'Select', 'type' => 'select', 'options' => ['A', 'B']],
            ['key' => 'e', 'label' => 'Multi', 'type' => 'multiselect', 'options' => ['A', 'B']],
            ['key' => 'f', 'label' => 'Bool', 'type' => 'boolean'],
            ['key' => 'g', 'label' => 'Date', 'type' => 'date'],
            ['key' => 'h', 'label' => 'Time', 'type' => 'time'],
            ['key' => 'i', 'label' => 'Long', 'type' => 'textarea'],
            ['key' => 'j', 'label' => 'Qual', 'type' => 'qualitative', 'options' => ['Negative', 'Positive']],
            ['key' => 'k', 'label' => 'Numeric', 'type' => 'numeric', 'unit' => 'mg/dL'],
            ['key' => 'l', 'label' => 'Table', 'type' => 'table', 'columns' => [['label' => 'Result', 'type' => 'select', 'options' => ['S', 'R']]], 'rows' => [['label' => 'Amikacin']], 'allow_add_rows' => true],
        ]));

        $this->assertCount(12, $schema['sections'][0]['parameters']);
        $this->assertSame('amikacin', $schema['sections'][0]['parameters'][11]['rows'][0]['key']);
    }

    public function test_malformed_json_is_rejected(): void
    {
        $this->assertContains('The parameter schema is not valid JSON.', $this->errorsFor('{"sections": ['));
    }

    public function test_unsupported_type_is_rejected(): void
    {
        $errors = $this->errorsFor($this->schemaWith([['key' => 'x', 'label' => 'X', 'type' => 'spreadsheet']]));
        $this->assertStringContainsString('unsupported input type “spreadsheet”', implode(' ', $errors));
    }

    public function test_duplicate_keys_are_rejected(): void
    {
        $errors = $this->errorsFor($this->schemaWith([
            ['key' => 'hb', 'label' => 'A', 'type' => 'text'],
            ['key' => 'hb', 'label' => 'B', 'type' => 'text'],
        ]));
        $this->assertStringContainsString('key “hb” is already used by', implode(' ', $errors));
    }

    public function test_invalid_reference_ranges_are_rejected(): void
    {
        $errors = implode(' ', $this->errorsFor($this->schemaWith([
            ['key' => 'a', 'label' => 'A', 'type' => 'numeric', 'reference_ranges' => [['min' => 10, 'max' => 5]]],
            ['key' => 'b', 'label' => 'B', 'type' => 'numeric', 'reference_ranges' => [['sex' => 'any']]],
            ['key' => 'c', 'label' => 'C', 'type' => 'numeric', 'reference_ranges' => [['min' => 1, 'sex' => 'robot']]],
            ['key' => 'd', 'label' => 'D', 'type' => 'numeric', 'reference_ranges' => [['min' => 1, 'age_min' => 10, 'age_max' => 5]]],
        ])));

        $this->assertStringContainsString('high limit must be greater than the low limit', $errors);
        $this->assertStringContainsString('enter a low limit, a high limit or both', $errors);
        $this->assertStringContainsString('sex must be any, male or female', $errors);
        $this->assertStringContainsString('“age to” must be greater than “age from”', $errors);
    }

    public function test_option_types_require_options(): void
    {
        $errors = $this->errorsFor($this->schemaWith([['key' => 'x', 'label' => 'X', 'type' => 'select', 'options' => []]]));
        $this->assertStringContainsString('add at least one option', implode(' ', $errors));
    }

    public function test_formula_must_reference_numeric_parameters_of_the_same_test(): void
    {
        $errors = implode(' ', $this->errorsFor($this->schemaWith([
            ['key' => 'a', 'label' => 'A', 'type' => 'numeric'],
            ['key' => 'b', 'label' => 'B', 'type' => 'numeric', 'formula' => 'a + missing'],
            ['key' => 'c', 'label' => 'C', 'type' => 'numeric', 'formula' => 'a +* 2'],
        ])));

        $this->assertStringContainsString('formula uses “missing”', $errors);
        $this->assertStringContainsString('invalid formula', $errors);
    }

    public function test_errors_are_also_reported_per_field_path(): void
    {
        try {
            $this->validate($this->schemaWith([
                ['key' => 'khoon', 'label' => 'Khoon', 'type' => 'numeric', 'precision' => 12, 'formula' => 'khoon + 1',
                    'reference_ranges' => [['min' => 5, 'max' => 1]]],
            ]));
            $this->fail('Schema was expected to be rejected.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $base = 'schema.sections.0.parameters.0';
            $this->assertSame(['Decimal places must be a whole number from 0 to 6'], $errors["{$base}.precision"]);
            $this->assertSame(['A formula cannot use its own parameter'], $errors["{$base}.formula"]);
            $this->assertSame(['High limit must be greater than the low limit'], $errors["{$base}.reference_ranges.0.max"]);
            $this->assertStringContainsString('Results › Khoon: decimal places', implode(' ', $errors['schema']));
        }
    }

    public function test_empty_schema_is_rejected(): void
    {
        $this->assertNotEmpty($this->errorsFor(['schema_version' => 1, 'sections' => []]));
        $this->assertNotEmpty($this->errorsFor($this->schemaWith([])));
    }

    public function test_workbook_catalog_is_valid(): void
    {
        $catalog = require database_path('data/workbook_test_catalog.php');
        $codes = [];

        foreach ($catalog as $entry) {
            $this->validate($entry['schema']);
            $codes[] = $entry['code'];
        }

        $this->assertSame(count($codes), count(array_unique($codes)), 'Catalog codes must be unique.');
        $this->assertGreaterThanOrEqual(35, count($codes));
    }
}
