<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ResultStatus;
use InvalidArgumentException;

final readonly class ActionResult
{
    /**
     * @param array<string, mixed> $data
     * @param list<array<string, mixed>|string> $messages
     */
    public function __construct(
        public ResultStatus $status,
        public string $code,
        public array $data,
        public ?GenerativeUiEnvelope $ui,
        public array $messages,
        public ?ApprovalRequirement $approval,
    ) {
        if (trim($code) === '') {
            throw new InvalidArgumentException('Result code must not be blank.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'code' => $this->code,
            'data' => $this->data,
            'ui' => $this->ui?->toArray(),
            'messages' => array_values($this->messages),
            'approval' => $this->approval?->toArray(),
        ];
    }
}
