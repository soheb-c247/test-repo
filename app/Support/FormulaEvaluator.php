<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Minimal, side-effect-free arithmetic evaluator for calculated parameters
 * (e.g. "bilirubin_total - bilirubin_direct").
 *
 * Supports numbers, parameter keys, + - * / ^, parentheses and unary minus.
 * Expressions are parsed into reverse Polish notation; nothing is ever
 * passed to eval().
 */
final class FormulaEvaluator
{
    private const PRECEDENCE = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, '^' => 3, '~' => 3];

    /**
     * Parse and validate an expression.
     *
     * @return list<string> referenced variable names
     *
     * @throws InvalidArgumentException
     */
    public static function variables(string $expression): array
    {
        $rpn = self::toRpn($expression);

        return array_values(array_unique(array_filter(
            $rpn,
            fn ($token) => preg_match('/^[a-z_][a-z0-9_]*$/', $token) === 1
        )));
    }

    /**
     * Evaluate an expression. Returns null when a variable is missing or not
     * numeric, or when the result is not a finite number.
     *
     * @param  array<string, mixed>  $variables
     */
    public static function evaluate(string $expression, array $variables): ?float
    {
        try {
            $rpn = self::toRpn($expression);
        } catch (InvalidArgumentException) {
            return null;
        }

        $stack = [];

        foreach ($rpn as $token) {
            if (is_numeric($token)) {
                $stack[] = (float) $token;
            } elseif (preg_match('/^[a-z_][a-z0-9_]*$/', $token)) {
                $value = $variables[$token] ?? null;
                if ($value === null || $value === '' || ! is_numeric($value)) {
                    return null;
                }
                $stack[] = (float) $value;
            } elseif ($token === '~') {
                if (! $stack) {
                    return null;
                }
                $stack[] = -array_pop($stack);
            } else {
                if (count($stack) < 2) {
                    return null;
                }
                $b = array_pop($stack);
                $a = array_pop($stack);
                $result = match ($token) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    '/' => $b == 0.0 ? null : $a / $b,
                    '^' => $a ** $b,
                };
                if ($result === null) {
                    return null;
                }
                $stack[] = $result;
            }
        }

        if (count($stack) !== 1 || ! is_finite($stack[0])) {
            return null;
        }

        return $stack[0];
    }

    /**
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    private static function toRpn(string $expression): array
    {
        if (strlen($expression) > 255) {
            throw new InvalidArgumentException('Formula is too long.');
        }

        preg_match_all('/\s*(\d+(?:\.\d+)?|[a-z_][a-z0-9_]*|[-+*\/^()])\s*/A', $expression, $matches);

        if (implode('', $matches[0]) !== $expression || $matches[1] === []) {
            throw new InvalidArgumentException('Formula contains unsupported characters.');
        }

        $output = [];
        $operators = [];
        $expectOperand = true;

        foreach ($matches[1] as $token) {
            if (is_numeric($token) || preg_match('/^[a-z_][a-z0-9_]*$/', $token)) {
                if (! $expectOperand) {
                    throw new InvalidArgumentException('Missing operator in formula.');
                }
                $output[] = $token;
                $expectOperand = false;
            } elseif ($token === '(') {
                if (! $expectOperand) {
                    throw new InvalidArgumentException('Missing operator before "(".');
                }
                $operators[] = $token;
            } elseif ($token === ')') {
                if ($expectOperand) {
                    throw new InvalidArgumentException('Unexpected ")" in formula.');
                }
                while ($operators && end($operators) !== '(') {
                    $output[] = array_pop($operators);
                }
                if (! $operators) {
                    throw new InvalidArgumentException('Unbalanced parentheses in formula.');
                }
                array_pop($operators);
            } else {
                if ($expectOperand) {
                    if ($token !== '-') {
                        throw new InvalidArgumentException("Unexpected operator \"{$token}\".");
                    }
                    $token = '~';
                }
                while ($operators) {
                    $top = end($operators);
                    if ($top === '(') {
                        break;
                    }
                    $rightAssoc = $token === '^' || $token === '~';
                    if (self::PRECEDENCE[$top] > self::PRECEDENCE[$token]
                        || (! $rightAssoc && self::PRECEDENCE[$top] === self::PRECEDENCE[$token])) {
                        $output[] = array_pop($operators);
                    } else {
                        break;
                    }
                }
                $operators[] = $token;
                $expectOperand = true;
            }
        }

        if ($expectOperand) {
            throw new InvalidArgumentException('Formula ends unexpectedly.');
        }

        while ($operators) {
            $op = array_pop($operators);
            if ($op === '(') {
                throw new InvalidArgumentException('Unbalanced parentheses in formula.');
            }
            $output[] = $op;
        }

        return $output;
    }
}
