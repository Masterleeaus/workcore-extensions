<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Knowledge;

final class VectorValidator
{
    public function __construct(private int $expectedDimensions, private float $maxAbsoluteValue = 1000000.0) {}

    /** @param array<int,mixed> $vector @return list<string> */
    public function validate(array $vector): array
    {
        $errors = [];
        if (count($vector) !== $this->expectedDimensions) {
            $errors[] = 'Vector dimensions do not match the configured index.';
        }
        foreach ($vector as $index => $value) {
            if (! is_int($value) && ! is_float($value)) {
                $errors[] = "Vector value {$index} is not numeric.";
                continue;
            }
            $number = (float) $value;
            if (! is_finite($number)) {
                $errors[] = "Vector value {$index} is not finite.";
            } elseif (abs($number) > $this->maxAbsoluteValue) {
                $errors[] = "Vector value {$index} exceeds the configured bound.";
            }
        }

        return $errors;
    }
}
