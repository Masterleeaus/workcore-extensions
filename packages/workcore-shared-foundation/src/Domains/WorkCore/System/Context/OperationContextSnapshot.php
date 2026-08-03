<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Context;

use InvalidArgumentException;

final readonly class OperationContextSnapshot
{
    public string $actorSubject;
    public ?int $workerId;
    public ?int $branchId;
    public ?int $territoryId;
    public ?string $deviceId;
    public string $authenticationAssurance;
    public int $securityRevision;
    public int $membershipRevision;

    public function __construct(
        public int $companyId,
        public ?int $actorId,
        public string $correlationId,
        public ?string $causationId,
        public string $locale,
        public string $timezone,
        ?string $actorSubject = null,
        ?int $workerId = null,
        ?int $branchId = null,
        ?int $territoryId = null,
        ?string $deviceId = null,
        string $authenticationAssurance = 'authenticated',
        int $securityRevision = 0,
        int $membershipRevision = 0,
    ) {
        if ($companyId < 1) {
            throw new InvalidArgumentException('Company ID must be positive.');
        }
        if ($actorId !== null && $actorId < 1) {
            throw new InvalidArgumentException('Actor ID must be positive when supplied.');
        }
        foreach (['worker' => $workerId, 'branch' => $branchId, 'territory' => $territoryId] as $label => $value) {
            if ($value !== null && $value < 1) {
                throw new InvalidArgumentException(ucfirst($label) . ' ID must be positive when supplied.');
            }
        }
        if (trim($correlationId) === '') {
            throw new InvalidArgumentException('Correlation ID is required.');
        }
        if (trim($locale) === '' || trim($timezone) === '') {
            throw new InvalidArgumentException('Locale and timezone are required.');
        }
        if ($securityRevision < 0 || $membershipRevision < 0) {
            throw new InvalidArgumentException('Security revisions cannot be negative.');
        }

        $subject = trim((string) $actorSubject);
        $assurance = trim($authenticationAssurance);
        if ($assurance === '') {
            throw new InvalidArgumentException('Authentication assurance is required.');
        }

        $this->actorSubject = $subject !== ''
            ? $subject
            : ($actorId === null ? 'system' : 'magicai:user:' . $actorId);
        $this->workerId = $workerId;
        $this->branchId = $branchId;
        $this->territoryId = $territoryId;
        $this->deviceId = $deviceId === null || trim($deviceId) === '' ? null : trim($deviceId);
        $this->authenticationAssurance = $assurance;
        $this->securityRevision = $securityRevision;
        $this->membershipRevision = $membershipRevision;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'actor_id' => $this->actorId,
            'actor_subject' => $this->actorSubject,
            'worker_id' => $this->workerId,
            'branch_id' => $this->branchId,
            'territory_id' => $this->territoryId,
            'device_id' => $this->deviceId,
            'authentication_assurance' => $this->authenticationAssurance,
            'security_revision' => $this->securityRevision,
            'membership_revision' => $this->membershipRevision,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
        ];
    }

    /** @param array<string,mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $actorId = isset($payload['actor_id']) ? (int) $payload['actor_id'] : null;

        return new self(
            companyId: (int) $payload['company_id'],
            actorId: $actorId,
            correlationId: (string) $payload['correlation_id'],
            causationId: isset($payload['causation_id']) ? (string) $payload['causation_id'] : null,
            locale: (string) $payload['locale'],
            timezone: (string) $payload['timezone'],
            actorSubject: (string) ($payload['actor_subject'] ?? ($actorId === null ? 'system' : 'magicai:user:' . $actorId)),
            workerId: isset($payload['worker_id']) ? (int) $payload['worker_id'] : null,
            branchId: isset($payload['branch_id']) ? (int) $payload['branch_id'] : null,
            territoryId: isset($payload['territory_id']) ? (int) $payload['territory_id'] : null,
            deviceId: isset($payload['device_id']) ? (string) $payload['device_id'] : null,
            authenticationAssurance: (string) ($payload['authentication_assurance'] ?? 'authenticated'),
            securityRevision: (int) ($payload['security_revision'] ?? 0),
            membershipRevision: (int) ($payload['membership_revision'] ?? 0),
        );
    }
}
