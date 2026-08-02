<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;

final readonly class RiskAssessment
{
    /** @param list<string> $reasons */
    public function __construct(
        public RiskLevel $level,
        public array $reasons,
        public ApprovalRequirement $approval,
    ) {
    }

    /** @return array{level:string,reasons:list<string>,approval:array<string,mixed>} */
    public function toArray(): array
    {
        return [
            'level' => $this->level->value,
            'reasons' => array_values($this->reasons),
            'approval' => $this->approval->toArray(),
        ];
    }
}
