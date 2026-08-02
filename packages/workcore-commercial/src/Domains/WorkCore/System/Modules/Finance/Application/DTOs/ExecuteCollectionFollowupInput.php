<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\ActionInput;
use InvalidArgumentException;

final readonly class ExecuteCollectionFollowupInput implements ActionInput
{
    /** @param list<string> $channels */
    public function __construct(
        public string $invoiceId,
        public string $stepCode,
        public array $channels,
        public string $tone,
        public bool $approvalRequired,
    ) {
        if (trim($invoiceId) === '' || trim($stepCode) === '' || $channels === []) {
            throw new InvalidArgumentException('Collection follow-up requires invoice, step and channels.');
        }
    }
    public function toArray(): array
    {
        return [
            'invoice_id' => $this->invoiceId,
            'step_code' => $this->stepCode,
            'channels' => array_values($this->channels),
            'tone' => $this->tone,
            'approval_required' => $this->approvalRequired,
        ];
    }
}
