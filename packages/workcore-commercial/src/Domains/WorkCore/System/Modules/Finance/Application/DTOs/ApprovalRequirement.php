<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ApprovalMode;
use InvalidArgumentException;

final readonly class ApprovalRequirement
{
    /** @param list<string> $requiredFields */
    public function __construct(
        public ApprovalMode $mode,
        public bool $required,
        public string $reason,
        public array $requiredFields = [],
    ) {
        if ($required && $mode === ApprovalMode::None) {
            throw new InvalidArgumentException('A required approval cannot use the none mode.');
        }

        if ($required && trim($reason) === '') {
            throw new InvalidArgumentException('A required approval must include a reason.');
        }
    }

    /** @return array{mode:string,required:bool,reason:string,required_fields:list<string>} */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'required' => $this->required,
            'reason' => $this->reason,
            'required_fields' => array_values($this->requiredFields),
        ];
    }

    public static function none(): self
    {
        return new self(ApprovalMode::None, false, '');
    }
}
