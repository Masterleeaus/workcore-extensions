<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Context;

use InvalidArgumentException;

final readonly class OperationContextSnapshot
{
    public function __construct(
        public int $companyId,
        public ?int $actorId,
        public string $correlationId,
        public ?string $causationId,
        public string $locale,
        public string $timezone,
    ) {
        if ($companyId < 1) {
            throw new InvalidArgumentException('Company ID must be positive.');
        }
        if ($actorId !== null && $actorId < 1) {
            throw new InvalidArgumentException('Actor ID must be positive when supplied.');
        }
        if (trim($correlationId) === '') {
            throw new InvalidArgumentException('Correlation ID is required.');
        }
        if (trim($locale) === '' || trim($timezone) === '') {
            throw new InvalidArgumentException('Locale and timezone are required.');
        }
    }

    /** @return array{company_id:int,actor_id:?int,correlation_id:string,causation_id:?string,locale:string,timezone:string} */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'actor_id' => $this->actorId,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
        ];
    }

    /** @param array{company_id:int,actor_id?:?int,correlation_id:string,causation_id?:?string,locale:string,timezone:string} $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            companyId: (int) $payload['company_id'],
            actorId: isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
            correlationId: (string) $payload['correlation_id'],
            causationId: isset($payload['causation_id']) ? (string) $payload['causation_id'] : null,
            locale: (string) $payload['locale'],
            timezone: (string) $payload['timezone'],
        );
    }
}
