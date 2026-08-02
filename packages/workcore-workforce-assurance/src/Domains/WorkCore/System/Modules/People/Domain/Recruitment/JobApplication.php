<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\People\Domain\Recruitment;

use DomainException;
use InvalidArgumentException;

final class JobApplication
{
    private JobApplicationState $state = JobApplicationState::Draft;
    private ?string $interviewId = null;
    private ?string $offerId = null;
    private ?string $workerId = null;
    private ?string $decisionReason = null;

    public function __construct(
        public readonly string $id,
        public readonly string $companyId,
        public readonly string $vacancyId,
        public readonly string $candidateId,
    ) {
        if (trim($id) === '' || trim($companyId) === '' || trim($vacancyId) === '' || trim($candidateId) === '') {
            throw new InvalidArgumentException('Application, company, vacancy and candidate identity are required.');
        }
    }

    public function state(): JobApplicationState { return $this->state; }
    public function workerId(): ?string { return $this->workerId; }

    public function submit(): void { $this->move(JobApplicationState::Draft, JobApplicationState::Submitted); }
    public function moveToScreening(): void { $this->move(JobApplicationState::Submitted, JobApplicationState::Screening); }

    public function scheduleInterview(string $interviewId): void
    {
        if (trim($interviewId) === '') throw new InvalidArgumentException('Interview identity is required.');
        $this->move(JobApplicationState::Screening, JobApplicationState::Interview);
        $this->interviewId = $interviewId;
    }

    public function issueOffer(string $offerId): void
    {
        if (trim($offerId) === '') throw new InvalidArgumentException('Offer identity is required.');
        $this->move(JobApplicationState::Interview, JobApplicationState::Offer);
        $this->offerId = $offerId;
    }

    public function acceptOffer(string $workerId): void
    {
        if (trim($workerId) === '') throw new InvalidArgumentException('Worker identity is required.');
        $this->move(JobApplicationState::Offer, JobApplicationState::Hired);
        $this->workerId = $workerId;
    }

    public function reject(string $reason): void
    {
        if (! in_array($this->state, [JobApplicationState::Submitted, JobApplicationState::Screening, JobApplicationState::Interview, JobApplicationState::Offer], true)) {
            throw new DomainException('Application cannot be rejected in its current state.');
        }
        if (trim($reason) === '') throw new DomainException('A rejection reason is required.');
        $this->decisionReason = $reason;
        $this->state = JobApplicationState::Rejected;
    }

    public function withdraw(): void
    {
        if (in_array($this->state, [JobApplicationState::Hired, JobApplicationState::Rejected, JobApplicationState::Withdrawn], true)) {
            throw new DomainException('Application cannot be withdrawn in its current state.');
        }
        $this->state = JobApplicationState::Withdrawn;
    }

    private function move(JobApplicationState $from, JobApplicationState $to): void
    {
        if ($this->state !== $from) throw new DomainException("Application must be {$from->value} before moving to {$to->value}.");
        $this->state = $to;
    }
}
