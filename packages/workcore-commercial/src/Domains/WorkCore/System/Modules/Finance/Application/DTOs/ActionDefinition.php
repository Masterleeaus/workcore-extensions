<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Application\DTOs;

use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\ApprovalMode;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\RiskLevel;
use App\Domains\WorkCore\System\Modules\Finance\Application\Enums\SurfaceType;
use InvalidArgumentException;

final readonly class ActionDefinition
{
    /**
     * @param class-string $actionClass
     * @param array<string, mixed> $inputSchema
     * @param list<SurfaceType> $surfaces
     * @param array<string, mixed> $resultSchema
     */
    public function __construct(
        public string $key,
        public string $actionClass,
        public string $description,
        public array $inputSchema,
        public RiskLevel $riskLevel,
        public ApprovalMode $approvalMode,
        public array $surfaces,
        public array $resultSchema = [],
    ) {
        if (preg_match('/^money\.[a-z0-9][a-z0-9._-]*$/', $key) !== 1) {
            throw new InvalidArgumentException('Action keys must use the money.* namespace.');
        }

        if (trim($actionClass) === '' || trim($description) === '') {
            throw new InvalidArgumentException('Action class and description are required.');
        }

        foreach ($surfaces as $surface) {
            if (! $surface instanceof SurfaceType) {
                throw new InvalidArgumentException('All action surfaces must be SurfaceType values.');
            }
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'action_class' => $this->actionClass,
            'description' => $this->description,
            'input_schema' => $this->inputSchema,
            'result_schema' => $this->resultSchema,
            'risk_level' => $this->riskLevel->value,
            'approval_mode' => $this->approvalMode->value,
            'surfaces' => array_values(array_map(
                static fn (SurfaceType $surface): string => $surface->value,
                $this->surfaces,
            )),
        ];
    }
}
