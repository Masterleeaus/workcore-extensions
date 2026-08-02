<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\DTO;

use InvalidArgumentException;

final readonly class ApprovalDecision
{
    public function __construct(public string $action, public string $reason)
    {
        if (! in_array($action, ['execute', 'pause', 'block'], true)) {
            throw new InvalidArgumentException("Invalid approval action [{$action}].");
        }
    }
}
