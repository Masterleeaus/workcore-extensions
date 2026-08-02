<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Domains;

use DateTimeImmutable;

final readonly class DomainIntelligenceContext
{
    /**
     * @param array<string,int|float|null> $metrics
     * @param list<DomainSignal> $signals
     * @param list<DomainActionDescriptor> $actions
     * @param list<string> $dataGaps
     */
    public function __construct(
        public string $domain,
        public string $name,
        public string $capability,
        public int $companyId,
        public array $metrics,
        public array $signals,
        public array $actions,
        public array $dataGaps = [],
        public ?DateTimeImmutable $generatedAt = null,
    ) {}

    public function hasSignal(string $type): bool
    {
        foreach ($this->signals as $signal) {
            if ($signal->type === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<DomainActionDescriptor>|null $visibleActions Null preserves all actions.
     * @return array<string,mixed>
     */
    public function toArray(?array $visibleActions = null): array
    {
        $actions = $visibleActions ?? $this->actions;
        $actionKeys = array_map(static fn (DomainActionDescriptor $action): string => $action->actionKey, $actions);

        return [
            'domain' => $this->domain,
            'name' => $this->name,
            'capability' => $this->capability,
            'company_id' => $this->companyId,
            'metrics' => $this->metrics,
            'signals' => array_map(static fn (DomainSignal $signal): array => $signal->toArray($visibleActions === null ? null : $actionKeys), $this->signals),
            'actions' => array_map(static fn (DomainActionDescriptor $action): array => $action->toArray(), $actions),
            'data_gaps' => $this->dataGaps,
            'generated_at' => ($this->generatedAt ?? new DateTimeImmutable())->format(DATE_ATOM),
            'execution_mode' => 'proposal_only',
        ];
    }
}
