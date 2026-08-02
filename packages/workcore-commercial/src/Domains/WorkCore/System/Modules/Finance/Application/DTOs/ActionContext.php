<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\InterfaceOrigin;
use App\Domains\WorkCore\System\Modules\Finance\Audit\AuditActor;
use InvalidArgumentException;

final readonly class ActionContext
{
    public function __construct(
        public string $companyId,
        public ?string $userId,
        public ?string $membershipId,
        public InterfaceOrigin $origin,
        public ?string $deviceId,
        public ?string $conversationId,
        public ?string $agentId,
        public ?string $workflowId,
        public ?string $approvalId,
        public string $correlationId,
        public string $idempotencyKey,
        public ?AuditActor $actor = null,
    ) {
        self::requireNonBlank($companyId, 'companyId');
        self::requireNonBlank($correlationId, 'correlationId');
        self::requireNonBlank($idempotencyKey, 'idempotencyKey');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'user_id' => $this->userId,
            'membership_id' => $this->membershipId,
            'origin' => $this->origin->value,
            'device_id' => $this->deviceId,
            'conversation_id' => $this->conversationId,
            'agent_id' => $this->agentId,
            'workflow_id' => $this->workflowId,
            'approval_id' => $this->approvalId,
            'correlation_id' => $this->correlationId,
            'idempotency_key' => $this->idempotencyKey,
            'actor' => $this->actor?->toArray(),
        ];
    }

    private static function requireNonBlank(string $value, string $field): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException("{$field} must not be blank.");
        }
    }
}
