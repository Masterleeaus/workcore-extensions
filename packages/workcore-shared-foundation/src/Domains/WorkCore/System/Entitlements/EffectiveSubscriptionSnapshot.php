<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Entitlements;

use DateTimeImmutable;

final readonly class EffectiveSubscriptionSnapshot
{
    /** @param array<string,mixed> $features */
    public function __construct(
        public string $subscriptionId,
        public string $planId,
        public string $status,
        public array $features,
        public ?DateTimeImmutable $accessValidUntil,
        public string $sourceRevision,
        public ?DateTimeImmutable $accessValidFrom = null,
        public array $metadata = [],
    ) {}

    public function grantsAccessAt(DateTimeImmutable $at): bool
    {
        $status = strtolower(trim($this->status));
        if (! in_array($status, ['trial', 'active', 'grace_period', 'cancel_at_period_end', 'lifetime'], true)) {
            return false;
        }
        if ($this->accessValidFrom !== null && $this->accessValidFrom > $at) {
            return false;
        }
        if ($status !== 'lifetime' && $this->accessValidUntil !== null && $this->accessValidUntil <= $at) {
            return false;
        }

        return true;
    }

    public function featureEnabled(string $key): bool
    {
        $value = $this->features[$key] ?? false;
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value > 0;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'enabled', 'active', 'unlimited'], true);
        }

        return false;
    }
}
