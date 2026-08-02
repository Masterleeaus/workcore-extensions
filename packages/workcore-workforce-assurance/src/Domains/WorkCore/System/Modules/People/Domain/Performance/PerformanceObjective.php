<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Domain\Performance;

use DomainException;
use InvalidArgumentException;

final class PerformanceObjective
{
    private PerformanceObjectiveState $state = PerformanceObjectiveState::Draft;
    private int $progress = 0;
    /** @var list<array{progress:int,note:string}> */
    private array $checkIns = [];

    public function __construct(public readonly string $id, public readonly string $companyId, public readonly string $workerId, public readonly string $title)
    {
        if (trim($id) === '' || trim($companyId) === '' || trim($workerId) === '' || trim($title) === '') throw new InvalidArgumentException('Objective identity and title are required.');
    }
    public function state(): PerformanceObjectiveState { return $this->state; }
    /** @return list<array{progress:int,note:string}> */
    public function checkIns(): array { return $this->checkIns; }
    public function activate(): void
    {
        if ($this->state !== PerformanceObjectiveState::Draft) throw new DomainException('Only draft objectives can be activated.');
        $this->state = PerformanceObjectiveState::Active;
    }
    public function checkIn(int $progress, string $note): void
    {
        if (! in_array($this->state, [PerformanceObjectiveState::Active, PerformanceObjectiveState::AtRisk], true)) throw new DomainException('Only active objectives accept check-ins.');
        if ($progress < 0 || $progress > 100) throw new InvalidArgumentException('Objective progress must be between 0 and 100.');
        $this->progress = $progress;
        $this->checkIns[] = ['progress' => $progress, 'note' => $note];
    }
    public function markAtRisk(): void
    {
        if ($this->state !== PerformanceObjectiveState::Active) throw new DomainException('Only active objectives can be marked at risk.');
        $this->state = PerformanceObjectiveState::AtRisk;
    }
    public function complete(): void
    {
        if (! in_array($this->state, [PerformanceObjectiveState::Active, PerformanceObjectiveState::AtRisk], true) || $this->progress !== 100) throw new DomainException('Objective requires 100 percent progress before completion.');
        $this->state = PerformanceObjectiveState::Completed;
    }
}
