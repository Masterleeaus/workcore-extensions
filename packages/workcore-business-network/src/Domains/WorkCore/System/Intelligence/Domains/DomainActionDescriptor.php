<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains;

use InvalidArgumentException;

final readonly class DomainActionDescriptor
{
    public function __construct(
        public string $actionKey,
        public string $purpose,
        public string $risk,
        public string $permission,
        public bool $requiresConfirmation,
        public string $mode = 'proposal_only',
    ) {
        if (! preg_match('/^[a-z][a-z0-9_.-]{2,159}$/', $actionKey)) {
            throw new InvalidArgumentException('Domain action key is invalid.');
        }
        if (! in_array($risk, ['low', 'medium', 'high', 'critical'], true)) {
            throw new InvalidArgumentException('Domain action risk is invalid.');
        }
        if ($mode !== 'proposal_only') {
            throw new InvalidArgumentException('Pass 51 domain actions must remain proposal-only.');
        }
        if (trim($purpose) === '' || trim($permission) === '') {
            throw new InvalidArgumentException('Domain action purpose and permission are required.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'action_key' => $this->actionKey,
            'purpose' => $this->purpose,
            'risk' => $this->risk,
            'permission' => $this->permission,
            'requires_confirmation' => $this->requiresConfirmation,
            'mode' => $this->mode,
        ];
    }
}
