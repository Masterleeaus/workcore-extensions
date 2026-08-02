<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Forms\Services;

use InvalidArgumentException;

final class FormFieldPolicy
{
    private const TYPES = ['text','textarea','number','boolean','date','datetime','select','multiselect','signature','photo','file','pass_fail','rating','meter_reading'];
    private const FAILURE_ACTIONS = ['none','flag','create_work_order'];
    private const SEVERITIES = ['low','medium','high','critical','life_safety'];

    public function assertField(array $field): void
    {
        $type = (string) ($field['field_type'] ?? '');
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unsupported form field type.');
        }
        if (! in_array((string) ($field['failure_action'] ?? 'flag'), self::FAILURE_ACTIONS, true)) {
            throw new InvalidArgumentException('Unsupported form failure action.');
        }
        if (! in_array((string) ($field['failure_severity'] ?? 'medium'), self::SEVERITIES, true)) {
            throw new InvalidArgumentException('Unsupported form failure severity.');
        }
        if (in_array($type, ['select','multiselect'], true) && empty($field['options'])) {
            throw new InvalidArgumentException('Select fields require options.');
        }
    }

    public function isAnswered(string $fieldType, mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }
        if (is_array($value)) {
            return $value !== [];
        }
        return true;
    }
}
