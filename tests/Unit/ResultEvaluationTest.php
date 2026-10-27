<?php

namespace Tests\Unit;

use App\Services\ReportResultValidationService;
use App\Services\ResultEvaluator;
use App\Support\FormulaEvaluator;
use Tests\TestCase;

class ResultEvaluationTest extends TestCase
{
    private function parameter(array $ranges, string $type = 'numeric'): array
    {
        return ['key' => 'x', 'label' => 'X', 'type' => $type, 'precision' => 1, 'reference_ranges' => $ranges, 'reference_text' => null];
    }

    public function test_flags_use_the_most_specific_applicable_range(): void
    {
        $evaluator = new ResultEvaluator;
        $p = $this->parameter([
            ['min' => 3.6, 'max' => 7.7, 'sex' => 'male', 'age_min' => null, 'age_max' => null],
            ['min' => 2.5, 'max' => 6.8, 'sex' => 'female', 'age_min' => null, 'age_max' => null],
            ['min' => 2.0, 'max' => 8.0, 'sex' => 'any', 'age_min' => null, 'age_max' => null],
        ]);

        $this->assertSame('high', $evaluator->flag($p, '7.0', 'female', 30));
        $this->assertSame('normal', $evaluator->flag($p, '7.0', 'male', 30));
        $this->assertSame('normal', $evaluator->flag($p, '7.0', 'unspecified', 30));
        $this->assertSame('low', $evaluator->flag($p, '1.5', 'unspecified', 30));
    }

    public function test_age_bounded_ranges_require_a_known_age(): void
    {
        $evaluator = new ResultEvaluator;
        $p = $this->parameter([['min' => 1, 'max' => 12, 'sex' => 'any', 'age_min' => 0, 'age_max' => 0.1]]);

        $this->assertSame('normal', $evaluator->flag($p, '8.5', 'male', 3 / 365.25));
        $this->assertNull($evaluator->flag($p, '8.5', 'male', null), 'Unknown age → not evaluated');
        $this->assertNull($evaluator->flag($p, '8.5', 'male', 40), 'Adult → range not applicable');
    }

    public function test_values_without_ranges_or_non_numeric_are_not_evaluated(): void
    {
        $evaluator = new ResultEvaluator;

        $this->assertNull($evaluator->flag($this->parameter([]), '5', 'male', 30));
        $this->assertNull($evaluator->flag($this->parameter([['min' => 1, 'max' => 2, 'sex' => 'any']]), '', 'male', 30));
        $this->assertNull($evaluator->flag($this->parameter([['min' => 1, 'max' => 2, 'sex' => 'any']], 'qualitative'), 'Positive', 'male', 30));
    }

    public function test_reference_label_formatting(): void
    {
        $evaluator = new ResultEvaluator;

        $this->assertSame('12.0 - 15.6', $evaluator->referenceLabel($this->parameter([['min' => 12, 'max' => 15.6, 'sex' => 'any']])));
        $this->assertSame('≤ 20.0', $evaluator->referenceLabel($this->parameter([['min' => null, 'max' => 20, 'sex' => 'any']])));
        $this->assertSame(
            'Male: 3.6 - 7.7; Female: 2.5 - 6.8',
            $evaluator->referenceLabel($this->parameter([
                ['min' => 3.6, 'max' => 7.7, 'sex' => 'male'],
                ['min' => 2.5, 'max' => 6.8, 'sex' => 'female'],
            ]), 'unspecified', null)
        );
    }

    public function test_formula_evaluator(): void
    {
        $this->assertEqualsWithDelta(0.35, FormulaEvaluator::evaluate('t - d', ['t' => '0.91', 'd' => '0.56']), 1e-9);
        $this->assertEqualsWithDelta(214.47, FormulaEvaluator::evaluate('28.7 * a - 46.7', ['a' => 9.1]), 1e-9);
        $this->assertSame(-4.0, FormulaEvaluator::evaluate('-2^2', []));
        $this->assertSame(14.0, FormulaEvaluator::evaluate('2 * (3 + 4)', []));
        $this->assertNull(FormulaEvaluator::evaluate('a / b', ['a' => 1, 'b' => 0]));
        $this->assertNull(FormulaEvaluator::evaluate('a + 1', ['a' => '']));
        $this->assertNull(FormulaEvaluator::evaluate('system("ls")', []));
    }

    public function test_result_validation_by_type(): void
    {
        $validator = new ReportResultValidationService(new ResultEvaluator);
        $schema = ['sections' => [['parameters' => [
            ['key' => 'n', 'label' => 'N', 'type' => 'numeric', 'required' => true, 'precision' => null, 'min_value' => 0, 'max_value' => 100],
            ['key' => 'i', 'label' => 'I', 'type' => 'integer', 'required' => false, 'min_value' => null, 'max_value' => null],
            ['key' => 'q', 'label' => 'Q', 'type' => 'qualitative', 'required' => false, 'options' => ['Negative', 'Positive']],
            ['key' => 'd', 'label' => 'D', 'type' => 'date', 'required' => false],
            ['key' => 'o', 'label' => 'O', 'type' => 'text', 'required' => false, 'max_length' => 255],
            ['key' => 'c', 'label' => 'C', 'type' => 'numeric', 'required' => false, 'precision' => 2, 'formula' => 'n / 2'],
        ]]]];

        [$results, $errors] = $validator->validate($schema, ['n' => '12.50', 'i' => '', 'q' => 'Negative', 'd' => '2026-10-24', 'o' => '  ok '], strict: true);
        $this->assertSame([], $errors);
        $this->assertSame('12.50', $results['n']);
        $this->assertNull($results['i'], 'Blank optional values stay blank, never zero');
        $this->assertSame('ok', $results['o']);
        $this->assertSame('6.25', $results['c']);

        [, $errors] = $validator->validate($schema, ['n' => 'abc', 'i' => '1.5', 'q' => 'Maybe', 'd' => '2026-13-45'], strict: false);
        $this->assertSame(['n', 'i', 'q', 'd'], array_keys($errors));

        [, $errors] = $validator->validate($schema, ['n' => '150'], strict: false);
        $this->assertArrayHasKey('n', $errors);

        [, $errors] = $validator->validate($schema, [], strict: false);
        $this->assertSame([], $errors, 'Drafts may leave required values empty');

        [, $errors] = $validator->validate($schema, [], strict: true);
        $this->assertSame(['n' => 'Required.'], $errors);
    }
}
