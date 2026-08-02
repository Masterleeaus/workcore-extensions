<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Compliance\ComplianceDuePolicy;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Compliance\ComplianceOccurrenceState;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Compliance\ComplianceRecurrence;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Compliance\ComplianceRequirementState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseComplianceEvidence;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseComplianceOccurrence;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseComplianceRequirement;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseComplianceEvidenceChanged;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseComplianceOccurrenceCompleted;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseComplianceOccurrenceWaived;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseComplianceRequirementCreated;

class PremiseComplianceService
{
    public function createRequirement(Property $premise, array $attributes): PremiseComplianceRequirement
    {
        $status = $attributes['status'] ?? 'active';
        if (!ComplianceRequirementState::canCreate($status)) {
            throw ValidationException::withMessages(['status' => 'Compliance requirements must begin active or paused.']);
        }
        $type = $attributes['requirement_type'] ?? 'custom';
        if (!in_array($type, PremiseComplianceRequirement::TYPES, true)) {
            throw ValidationException::withMessages(['requirement_type' => 'Unsupported compliance requirement type.']);
        }
        $recurrenceType = $attributes['recurrence_type'] ?? 'none';
        if (!in_array($recurrenceType, ComplianceRecurrence::TYPES, true)) {
            throw ValidationException::withMessages(['recurrence_type' => 'Unsupported recurrence type.']);
        }
        if (in_array($recurrenceType, ['days', 'months', 'years'], true) && (int) ($attributes['recurrence_interval'] ?? 0) < 1) {
            throw ValidationException::withMessages(['recurrence_interval' => 'A positive interval is required for this recurrence type.']);
        }
        if ($recurrenceType === 'annual') {
            $month = (int) ($attributes['annual_month'] ?? 0);
            $day = (int) ($attributes['annual_day'] ?? 0);
            if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                throw ValidationException::withMessages(['annual_day' => 'Annual recurrence requires a valid month and day.']);
            }
        }

        return DB::transaction(function () use ($premise, $attributes, $status) {
            $initialDueAt = $attributes['initial_due_at'] ?? null;
            $requirement = PremiseComplianceRequirement::create(array_merge($attributes, [
                'company_id' => $premise->company_id,
                'user_id' => $attributes['user_id'] ?? $this->actorId(),
                'premise_id' => $premise->id,
                'status' => $status,
                'next_due_at' => $initialDueAt,
                'source_verification_status' => $attributes['source_verification_status'] ?? 'unverified',
            ]));

            if ($initialDueAt) {
                $this->createOccurrence($requirement, new DateTimeImmutable((string) $initialDueAt), 1);
            }

            event(new PremiseComplianceRequirementCreated($requirement));
            return $requirement->fresh(['occurrences']);
        });
    }

    public function transitionRequirement(PremiseComplianceRequirement $requirement, string $toStatus): PremiseComplianceRequirement
    {
        return DB::transaction(function () use ($requirement, $toStatus) {
            $requirement = $this->lockedRequirement($requirement);
            if (!ComplianceRequirementState::canTransition($requirement->status, $toStatus)) {
                throw ValidationException::withMessages(['status' => "Requirement cannot move from {$requirement->status} to {$toStatus}."]);
            }

            $requirement->update(['status' => $toStatus]);
            if ($toStatus === 'retired') {
                PremiseComplianceOccurrence::withoutGlobalScopes()
                    ->where('company_id', $requirement->company_id)
                    ->where('requirement_id', $requirement->id)
                    ->whereIn('status', ['pending', 'due', 'overdue'])
                    ->get()
                    ->each(function (PremiseComplianceOccurrence $occurrence): void {
                        $occurrence->update([
                            'status' => 'not_applicable',
                            'notes' => $occurrence->notes ?: 'Requirement retired',
                        ]);
                    });
                $requirement->update(['next_due_at' => null]);
            } elseif ($toStatus === 'active') {
                $this->refreshRequirement($requirement);
            }

            return $requirement->fresh(['occurrences', 'evidence']);
        });
    }

    public function refreshDueStatuses(Property $premise): array
    {
        $counts = ['pending' => 0, 'due' => 0, 'overdue' => 0, 'changed' => 0];
        $requirements = PremiseComplianceRequirement::withoutGlobalScopes()
            ->where('company_id', $premise->company_id)
            ->where('premise_id', $premise->id)
            ->where('status', 'active')
            ->get();

        foreach ($requirements as $requirement) {
            $result = $this->refreshRequirement($requirement);
            if ($result) {
                $counts[$result['status']]++;
                $counts['changed'] += $result['changed'] ? 1 : 0;
            }
        }

        return $counts;
    }

    public function addEvidence(PremiseComplianceRequirement $requirement, array $attributes): PremiseComplianceEvidence
    {
        $status = $attributes['status'] ?? 'submitted';
        if (!in_array($status, ['draft', 'submitted'], true)) {
            throw ValidationException::withMessages(['status' => 'New evidence must begin as draft or submitted.']);
        }

        if (!empty($attributes['occurrence_id'])) {
            $validOccurrence = PremiseComplianceOccurrence::withoutGlobalScopes()
                ->where('company_id', $requirement->company_id)
                ->where('requirement_id', $requirement->id)
                ->whereKey($attributes['occurrence_id'])
                ->exists();
            if (!$validOccurrence) {
                throw ValidationException::withMessages(['occurrence_id' => 'Evidence occurrence does not belong to this requirement.']);
            }
        }

        $evidence = PremiseComplianceEvidence::create(array_merge($attributes, [
            'company_id' => $requirement->company_id,
            'user_id' => $attributes['user_id'] ?? $this->actorId(),
            'premise_id' => $requirement->premise_id,
            'requirement_id' => $requirement->id,
            'status' => $status,
        ]));
        event(new PremiseComplianceEvidenceChanged($evidence, 'created'));
        return $evidence;
    }

    public function verifyEvidence(PremiseComplianceEvidence $evidence, string $status, ?string $rejectionReason = null): PremiseComplianceEvidence
    {
        if (!in_array($status, ['verified', 'rejected'], true)) {
            throw ValidationException::withMessages(['status' => 'Evidence review must result in verified or rejected.']);
        }

        $evidence = PremiseComplianceEvidence::withoutGlobalScopes()
            ->where('company_id', $evidence->company_id)->whereKey($evidence->id)->lockForUpdate()->firstOrFail();
        if ($evidence->status === 'superseded') {
            throw ValidationException::withMessages(['evidence' => 'Superseded evidence cannot be reviewed.']);
        }
        if ($status === 'rejected' && !$rejectionReason) {
            throw ValidationException::withMessages(['rejection_reason' => 'A rejection reason is required.']);
        }

        $evidence->update([
            'status' => $status,
            'verified_at' => now(),
            'verified_by_user_id' => $this->actorId(),
            'rejection_reason' => $status === 'rejected' ? $rejectionReason : null,
        ]);
        event(new PremiseComplianceEvidenceChanged($evidence, $status));
        return $evidence->fresh();
    }

    public function completeOccurrence(PremiseComplianceOccurrence $occurrence, array $attributes = []): PremiseComplianceOccurrence
    {
        return DB::transaction(function () use ($occurrence, $attributes) {
            $occurrence = $this->lockedOccurrence($occurrence);
            if (!ComplianceOccurrenceState::canTransition($occurrence->status, 'completed')) {
                throw ValidationException::withMessages(['status' => 'This occurrence cannot be completed from its current state.']);
            }
            $requirement = $this->lockedRequirement($occurrence->requirement);
            $evidenceId = $attributes['evidence_id'] ?? null;

            if ($evidenceId) {
                $selectedEvidence = PremiseComplianceEvidence::withoutGlobalScopes()
                    ->where('company_id', $requirement->company_id)
                    ->where('requirement_id', $requirement->id)
                    ->whereKey($evidenceId)
                    ->first();
                if (!$selectedEvidence) {
                    throw ValidationException::withMessages(['evidence_id' => 'Selected evidence does not belong to this requirement.']);
                }
            }

            if ($requirement->evidence_required) {
                $evidenceQuery = PremiseComplianceEvidence::withoutGlobalScopes()
                    ->where('company_id', $requirement->company_id)
                    ->where('requirement_id', $requirement->id)
                    ->whereIn('status', ['submitted', 'verified']);
                if ($evidenceId) {
                    $evidenceQuery->whereKey($evidenceId);
                } else {
                    $evidenceQuery->where(function ($query) use ($occurrence) {
                        $query->where('occurrence_id', $occurrence->id)->orWhereNull('occurrence_id');
                    });
                }
                $evidence = $evidenceQuery->latest('id')->first();
                if (!$evidence) {
                    throw ValidationException::withMessages(['evidence_id' => 'Evidence is required before this occurrence can be completed.']);
                }
                $evidenceId = $evidence->id;
            }

            $occurrence->update([
                'status' => 'completed',
                'completed_at' => $attributes['completed_at'] ?? now(),
                'completed_by_user_id' => $this->actorId(),
                'evidence_id' => $evidenceId,
                'notes' => $attributes['notes'] ?? $occurrence->notes,
            ]);
            $requirement->update(['last_completed_at' => $occurrence->completed_at]);
            $this->scheduleSuccessor($requirement, $occurrence);
            event(new PremiseComplianceOccurrenceCompleted($occurrence));
            return $occurrence->fresh(['requirement', 'selectedEvidence']);
        });
    }

    public function waiveOccurrence(PremiseComplianceOccurrence $occurrence, string $reason): PremiseComplianceOccurrence
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['waiver_reason' => 'A waiver reason is required.']);
        }

        return DB::transaction(function () use ($occurrence, $reason) {
            $occurrence = $this->lockedOccurrence($occurrence);
            if (!ComplianceOccurrenceState::canTransition($occurrence->status, 'waived')) {
                throw ValidationException::withMessages(['status' => 'This occurrence cannot be waived from its current state.']);
            }
            $requirement = $this->lockedRequirement($occurrence->requirement);
            $occurrence->update([
                'status' => 'waived',
                'waived_at' => now(),
                'waived_by_user_id' => $this->actorId(),
                'waiver_reason' => $reason,
            ]);
            $this->scheduleSuccessor($requirement, $occurrence);
            event(new PremiseComplianceOccurrenceWaived($occurrence));
            return $occurrence->fresh(['requirement']);
        });
    }

    private function refreshRequirement(PremiseComplianceRequirement $requirement): ?array
    {
        $occurrence = PremiseComplianceOccurrence::withoutGlobalScopes()
            ->where('company_id', $requirement->company_id)
            ->where('requirement_id', $requirement->id)
            ->whereIn('status', ['pending', 'due', 'overdue'])
            ->orderBy('sequence')
            ->first();

        if (!$occurrence && $requirement->next_due_at) {
            $nextSequence = (int) PremiseComplianceOccurrence::withoutGlobalScopes()
                ->where('requirement_id', $requirement->id)->max('sequence') + 1;
            $occurrence = $this->createOccurrence(
                $requirement,
                new DateTimeImmutable($requirement->next_due_at->format('c')),
                max(1, $nextSequence)
            );
        }
        if (!$occurrence) {
            return null;
        }

        $newStatus = ComplianceDuePolicy::statusFor(
            new DateTimeImmutable($occurrence->due_at->format('c')),
            new DateTimeImmutable('now'),
            (int) $requirement->warning_days,
            (int) $requirement->grace_days
        );
        $changed = $newStatus !== $occurrence->status;
        if ($changed && ComplianceOccurrenceState::canTransition($occurrence->status, $newStatus)) {
            $occurrence->update(['status' => $newStatus]);
        }

        return ['status' => $occurrence->fresh()->status, 'changed' => $changed];
    }

    private function createOccurrence(PremiseComplianceRequirement $requirement, DateTimeImmutable $dueAt, int $sequence): PremiseComplianceOccurrence
    {
        $status = ComplianceDuePolicy::statusFor(
            $dueAt,
            new DateTimeImmutable('now'),
            (int) $requirement->warning_days,
            (int) $requirement->grace_days
        );
        $warningAt = $dueAt->modify('-' . max(0, (int) $requirement->warning_days) . ' days');

        return PremiseComplianceOccurrence::create([
            'company_id' => $requirement->company_id,
            'user_id' => $this->actorId(),
            'premise_id' => $requirement->premise_id,
            'requirement_id' => $requirement->id,
            'sequence' => $sequence,
            'due_at' => $dueAt->format('Y-m-d H:i:s'),
            'warning_at' => $warningAt->format('Y-m-d H:i:s'),
            'status' => $status,
            'generated_at' => now(),
        ]);
    }

    private function scheduleSuccessor(PremiseComplianceRequirement $requirement, PremiseComplianceOccurrence $occurrence): void
    {
        if ($requirement->status !== 'active') {
            $requirement->update(['next_due_at' => null]);
            return;
        }

        $next = ComplianceRecurrence::nextDueDate(
            new DateTimeImmutable($occurrence->due_at->format('c')),
            $requirement->recurrence_type,
            $requirement->recurrence_interval,
            $requirement->annual_month,
            $requirement->annual_day
        );

        if (!$next) {
            $requirement->update(['next_due_at' => null]);
            return;
        }

        $nextSequence = (int) $occurrence->sequence + 1;
        $existing = PremiseComplianceOccurrence::withoutGlobalScopes()
            ->where('requirement_id', $requirement->id)
            ->where('sequence', $nextSequence)
            ->first();
        if (!$existing) {
            $this->createOccurrence($requirement, $next, $nextSequence);
        }
        $requirement->update(['next_due_at' => $next->format('Y-m-d H:i:s')]);
    }

    private function lockedRequirement(PremiseComplianceRequirement $requirement): PremiseComplianceRequirement
    {
        return PremiseComplianceRequirement::withoutGlobalScopes()
            ->where('company_id', $requirement->company_id)->whereKey($requirement->id)->lockForUpdate()->firstOrFail();
    }

    private function lockedOccurrence(PremiseComplianceOccurrence $occurrence): PremiseComplianceOccurrence
    {
        return PremiseComplianceOccurrence::withoutGlobalScopes()
            ->where('company_id', $occurrence->company_id)->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
    }

    private function actorId(): ?int
    {
        return function_exists('user') && user() ? user()->id : auth()->id();
    }
}
