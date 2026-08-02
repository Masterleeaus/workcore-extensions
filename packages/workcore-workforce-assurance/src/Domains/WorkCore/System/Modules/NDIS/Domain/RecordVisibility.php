<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Domain;

use DomainException;

final class RecordVisibility
{
    private const ALLOWED = ['team', 'management', 'participant', 'restricted'];
    private const SENSITIVITY = ['normal', 'sensitive', 'highly_sensitive'];
    public static function isAllowed(string $value): bool { return in_array($value, self::ALLOWED, true); }
    public static function assertAllowed(string $value): void { if (! self::isAllowed($value)) throw new DomainException("Unsupported NDIS record visibility [{$value}]."); }
    public static function assertSensitivity(string $value): void { if (! in_array($value, self::SENSITIVITY, true)) throw new DomainException("Unsupported NDIS record sensitivity [{$value}]."); }
}
