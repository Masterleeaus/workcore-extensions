<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Wizards\Services;

use InvalidArgumentException;

final class WizardAnswerValidator
{
    /** @param array<string,mixed> $question */
    public function validate(array $question, mixed $value): void
    {
        if (($question['required'] ?? false) && ($value === null || $value === '' || $value === [])) throw new InvalidArgumentException('This wizard question requires an answer.');
        if ($value === null || $value === '') return;
        $type=(string)($question['response_type'] ?? 'text');
        if ($type === 'boolean' && ! is_bool($value)) throw new InvalidArgumentException('Expected a boolean answer.');
        if (in_array($type,['integer'],true) && filter_var($value,FILTER_VALIDATE_INT)===false) throw new InvalidArgumentException('Expected an integer answer.');
        if ($type === 'email' && filter_var((string)$value,FILTER_VALIDATE_EMAIL)===false) throw new InvalidArgumentException('Expected a valid email address.');
        if (in_array($type,['multi_select','collection','checklist'],true) && ! is_array($value)) throw new InvalidArgumentException('Expected a list answer.');
        if (isset($question['options']) && ! is_array($value) && ! in_array($value,(array)$question['options'],true)) throw new InvalidArgumentException('Answer is not one of the permitted options.');
    }
}
