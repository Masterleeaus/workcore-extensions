<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\AttendanceVerification\Domain;

use DomainException;
use InvalidArgumentException;

final class AttendanceVerificationAttempt
{
    private VerificationAttemptState $state = VerificationAttemptState::Pending;
    /** @var array<string,AttendanceEvidence> */
    private array $evidence = [];

    public function __construct(public readonly string $id, public readonly string $companyId, public readonly string $workerId)
    {
        if (trim($id) === '' || trim($companyId) === '' || trim($workerId) === '') throw new InvalidArgumentException('Attempt, company and worker identity are required.');
    }
    public function state(): VerificationAttemptState { return $this->state; }
    public function addEvidence(AttendanceEvidence $evidence): void
    {
        if ($this->state !== VerificationAttemptState::Pending) throw new DomainException('Evidence cannot be added after evaluation.');
        $this->evidence[$evidence->method->value] = $evidence;
    }
    public function evaluate(AttendanceVerificationPolicy $policy): void
    {
        if ($this->state !== VerificationAttemptState::Pending) throw new DomainException('Attendance attempt has already been evaluated.');
        if ($policy->companyId !== $this->companyId) throw new DomainException('Attendance policy belongs to another company.');
        $this->state = $policy->accepts(array_values($this->evidence)) ? VerificationAttemptState::Accepted : VerificationAttemptState::Rejected;
    }
    public function consume(): void
    {
        if ($this->state !== VerificationAttemptState::Accepted) throw new DomainException('Only accepted verification attempts can be consumed.');
        $this->state = VerificationAttemptState::Consumed;
    }
}
