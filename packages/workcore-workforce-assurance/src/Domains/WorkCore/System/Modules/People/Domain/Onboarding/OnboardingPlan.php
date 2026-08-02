<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Domain\Onboarding;

use DomainException;
use InvalidArgumentException;

final class OnboardingPlan
{
    private OnboardingState $state = OnboardingState::Draft;
    /** @var array<string,OnboardingTask> */
    private array $tasks = [];

    /** @param list<OnboardingTask> $tasks */
    public function __construct(public readonly string $id, public readonly string $companyId, public readonly string $workerId, array $tasks)
    {
        if (trim($id) === '' || trim($companyId) === '' || trim($workerId) === '') throw new InvalidArgumentException('Plan, company and worker identity are required.');
        if ($tasks === []) throw new InvalidArgumentException('An onboarding plan requires at least one task.');
        foreach ($tasks as $task) {
            if (isset($this->tasks[$task->key])) throw new InvalidArgumentException('Onboarding task keys must be unique.');
            $this->tasks[$task->key] = $task;
        }
    }

    public function state(): OnboardingState { return $this->state; }
    public function activate(): void
    {
        if ($this->state !== OnboardingState::Draft) throw new DomainException('Only draft onboarding plans can be activated.');
        $this->state = OnboardingState::Active;
    }
    public function completeTask(string $key, string $actorId): void
    {
        if ($this->state !== OnboardingState::Active) throw new DomainException('Tasks can only be completed on active onboarding plans.');
        $task = $this->tasks[$key] ?? null;
        if (! $task) throw new InvalidArgumentException('Unknown onboarding task.');
        $task->complete($actorId);
        foreach ($this->tasks as $candidate) {
            if ($candidate->required && ! $candidate->completed()) return;
        }
        $this->state = OnboardingState::Completed;
    }
    public function completionPercent(): int
    {
        $done = count(array_filter($this->tasks, static fn (OnboardingTask $task): bool => $task->completed()));
        return (int) round(($done / count($this->tasks)) * 100);
    }
}
