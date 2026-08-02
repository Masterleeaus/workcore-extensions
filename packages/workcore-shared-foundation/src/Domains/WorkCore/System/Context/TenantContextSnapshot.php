<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Context;

use InvalidArgumentException;

final readonly class TenantContextSnapshot
{
    public function __construct(public int $companyId, public ?int $userId = null)
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException('Company ID must be positive.');
        }
        if ($userId !== null && $userId < 1) {
            throw new InvalidArgumentException('User ID must be positive when supplied.');
        }
    }

    /** @return array{company_id:int,user_id:?int} */
    public function toArray(): array
    {
        return ['company_id' => $this->companyId, 'user_id' => $this->userId];
    }

    /** @param array{company_id:int,user_id?:?int} $payload */
    public static function fromArray(array $payload): self
    {
        return new self((int) $payload['company_id'], isset($payload['user_id']) ? (int) $payload['user_id'] : null);
    }
}
