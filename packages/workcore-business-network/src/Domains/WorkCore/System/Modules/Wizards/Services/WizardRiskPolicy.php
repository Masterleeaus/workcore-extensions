<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Wizards\Services;

use InvalidArgumentException;

final class WizardRiskPolicy
{
    public function decision(string $risk): string
    {
        return match ($risk) {
            'low' => 'auto_draft',
            'medium' => 'section_approval',
            'high' => 'explicit_approval',
            'critical' => 'blocked',
            default => throw new InvalidArgumentException("Unknown wizard risk [{$risk}]."),
        };
    }

    public function canAiExecute(string $risk): bool
    {
        return $this->decision($risk) === 'auto_draft';
    }

    public function requiresApproval(string $risk): bool
    {
        return in_array($this->decision($risk), ['section_approval', 'explicit_approval'], true);
    }
}
