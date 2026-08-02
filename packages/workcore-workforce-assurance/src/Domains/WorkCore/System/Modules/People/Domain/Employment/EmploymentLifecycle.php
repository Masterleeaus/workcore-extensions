<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Domain\Employment;

use DomainException;
use InvalidArgumentException;

final class EmploymentLifecycle
{
    private EmploymentState $state = EmploymentState::Draft;

    /** @var list<array{from:string,to:string,actor_id:string,reason:?string}> */
    private array $history = [];

    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $workerId,
    ) {
        if (trim($id) === '' || trim($companyId) === '' || trim($workerId) === '') {
            throw new InvalidArgumentException('Employment, company and worker identity are required.');
        }
    }

    public function state(): EmploymentState { return $this->state; }

    /** @return list<array{from:string,to:string,actor_id:string,reason:?string}> */
    public function history(): array { return $this->history; }

    public function commencePreEmployment(string $actorId): void { $this->transition(EmploymentState::PreEmployment, $actorId); }
    public function startOnboarding(string $actorId): void { $this->transition(EmploymentState::Onboarding, $actorId); }
    public function activate(string $actorId): void { $this->transition(EmploymentState::Active, $actorId); }
    public function suspend(string $actorId, string $reason): void { $this->transition(EmploymentState::Suspended, $actorId, $reason); }
    public function resume(string $actorId): void { $this->transition(EmploymentState::Active, $actorId); }
    public function beginOffboarding(string $actorId, string $reason): void { $this->transition(EmploymentState::Offboarding, $actorId, $reason); }
    public function end(string $actorId, string $reason): void { $this->transition(EmploymentState::Ended, $actorId, $reason); }

    private function transition(EmploymentState $to, string $actorId, ?string $reason = null): void
    {
        if (trim($actorId) === '') {
            throw new InvalidArgumentException('A named actor is required for employment transitions.');
        }
        $allowed = match ($this->state) {
            EmploymentState::Draft => [EmploymentState::PreEmployment],
            EmploymentState::PreEmployment => [EmploymentState::Onboarding, EmploymentState::Ended],
            EmploymentState::Onboarding => [EmploymentState::Active, EmploymentState::Ended],
            EmploymentState::Active => [EmploymentState::Suspended, EmploymentState::Offboarding],
            EmploymentState::Suspended => [EmploymentState::Active, EmploymentState::Offboarding],
            EmploymentState::Offboarding => [EmploymentState::Ended],
            EmploymentState::Ended => [],
        };
        if (! in_array($to, $allowed, true)) {
            throw new DomainException("Employment cannot move from {$this->state->value} to {$to->value}.");
        }
        if (in_array($to, [EmploymentState::Suspended, EmploymentState::Offboarding, EmploymentState::Ended], true) && trim((string) $reason) === '') {
            throw new DomainException('A reason is required for this employment transition.');
        }
        $from = $this->state;
        $this->state = $to;
        $this->history[] = ['from' => $from->value, 'to' => $to->value, 'actor_id' => $actorId, 'reason' => $reason];
    }
}
