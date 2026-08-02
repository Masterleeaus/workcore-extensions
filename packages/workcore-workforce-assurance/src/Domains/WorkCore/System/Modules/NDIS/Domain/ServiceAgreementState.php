<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Domain;

use DomainException;

final class ServiceAgreementState
{
    private const TRANSITIONS = [
        'draft' => ['offered', 'signed', 'cancelled'],
        'offered' => ['signed', 'declined', 'cancelled'],
        'signed' => ['active', 'cancelled'],
        'active' => ['suspended', 'ended', 'cancelled'],
        'suspended' => ['active', 'ended', 'cancelled'],
        'declined' => [], 'ended' => [], 'cancelled' => [],
    ];
    public static function canTransition(string $from, string $to): bool { return in_array($to, self::TRANSITIONS[$from] ?? [], true); }
    public static function assertAllowed(string $status): void { if (! array_key_exists($status, self::TRANSITIONS)) throw new DomainException("Unsupported service-agreement status [{$status}]."); }
}
