<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Permits\WorkPermitState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseWorkPermit;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseWorkPermitApproval;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseWorkPermitStatusChanged;

class PremiseWorkPermitService
{
    public function create(Property $premise, array $attributes): PremiseWorkPermit
    {
        $validFrom = CarbonImmutable::parse($attributes['valid_from']);
        $validUntil = CarbonImmutable::parse($attributes['valid_until']);
        if ($validUntil->lessThanOrEqualTo($validFrom)) {
            throw ValidationException::withMessages(['valid_until' => 'Permit end must be after its start.']);
        }
        $attributes['valid_from'] = $validFrom;
        $attributes['valid_until'] = $validUntil;

        return PremiseWorkPermit::create(array_merge($attributes, [
            'company_id' => $premise->company_id,
            'user_id' => $attributes['user_id'] ?? $this->actorId(),
            'premise_id' => $premise->id,
            'permit_number' => 'WP-' . now()->format('ymd') . '-' . Str::upper(Str::random(8)),
            'status' => 'draft',
            'work_categories' => $attributes['work_categories'] ?? [],
            'hazards' => $attributes['hazards'] ?? [],
            'controls' => $attributes['controls'] ?? [],
            'evidence_document_ids' => $attributes['evidence_document_ids'] ?? [],
            'metadata' => $attributes['metadata'] ?? [],
        ]));
    }

    public function submit(PremiseWorkPermit $permit): PremiseWorkPermit
    {
        return $this->transition($permit, 'submitted');
    }

    public function approveManager(PremiseWorkPermit $permit, array $attributes = []): PremiseWorkPermit
    {
        return $this->approvalTransition($permit, 'submitted', 'manager_approved', 'manager', $attributes);
    }

    public function approveSite(PremiseWorkPermit $permit, array $attributes = []): PremiseWorkPermit
    {
        return $this->approvalTransition($permit, 'manager_approved', 'site_approved', 'premises_manager', $attributes);
    }

    public function validatePermit(PremiseWorkPermit $permit, array $attributes = []): PremiseWorkPermit
    {
        return DB::transaction(function () use ($permit, $attributes): PremiseWorkPermit {
            $permit = $this->locked($permit);
            if ($permit->status !== 'site_approved') {
                throw ValidationException::withMessages(['status' => 'The work permit requires manager and premises approval before validation.']);
            }
            $this->recordApproval($permit, 'validator', 'approved', $attributes);
            $permit->update([
                'status' => 'validated',
                'validation_remarks' => $attributes['remarks'] ?? null,
                'validated_at' => now(),
                'evidence_document_ids' => array_values(array_unique(array_merge($permit->evidence_document_ids ?? [], $attributes['evidence_document_ids'] ?? []))),
            ]);
            event(new PremiseWorkPermitStatusChanged($permit, 'validated'));
            return $permit->fresh(['approvals']);
        });
    }

    public function transition(PremiseWorkPermit $permit, string $toStatus, array $attributes = []): PremiseWorkPermit
    {
        return DB::transaction(function () use ($permit, $toStatus, $attributes): PremiseWorkPermit {
            $permit = $this->locked($permit);
            $from = (string) $permit->status;
            if (!WorkPermitState::canTransition($from, $toStatus)) {
                throw ValidationException::withMessages(['status' => "Work permit cannot move from {$from} to {$toStatus}."]);
            }
            if (in_array($toStatus, ['manager_approved', 'site_approved', 'validated'], true)) {
                throw ValidationException::withMessages(['status' => 'Use the governed approval or validation action.']);
            }
            if ($toStatus === 'active') {
                $inductionCompletedAt = $attributes['induction_completed_at'] ?? $permit->induction_completed_at;
                if ($permit->induction_required && !$inductionCompletedAt) {
                    throw ValidationException::withMessages(['induction' => 'Required induction must be completed before activation.']);
                }
                if ($permit->valid_from && $permit->valid_from->isFuture()) {
                    throw ValidationException::withMessages(['valid_from' => 'A work permit cannot activate before its validity window begins.']);
                }
                if ($permit->valid_until && $permit->valid_until->isPast()) {
                    throw ValidationException::withMessages(['valid_until' => 'An expired work permit cannot be activated.']);
                }
            }
            if ($toStatus === 'rejected') {
                $stage = match ($from) {
                    'submitted' => 'manager',
                    'manager_approved' => 'premises_manager',
                    'site_approved' => 'validator',
                    default => 'operator',
                };
                $this->recordApproval($permit, $stage, 'rejected', $attributes);
            }
            $updates = ['status' => $toStatus];
            if (array_key_exists('induction_completed_at', $attributes)) $updates['induction_completed_at'] = $attributes['induction_completed_at'];
            if (array_key_exists('conditions', $attributes)) $updates['conditions'] = $attributes['conditions'];
            $permit->update($updates);
            event(new PremiseWorkPermitStatusChanged($permit, $toStatus));
            return $permit->fresh(['approvals']);
        });
    }

    public function linkWorkCore(PremiseWorkPermit $permit, array $attributes): PremiseWorkPermit
    {
        return DB::transaction(function () use ($permit, $attributes): PremiseWorkPermit {
            $permit = $this->locked($permit);
            if (WorkPermitState::isTerminal((string) $permit->status)) {
                throw ValidationException::withMessages(['workcore' => 'Terminal work-permit history cannot be relinked.']);
            }
            $permit->update([
                'workcore_linked_module' => $attributes['workcore_linked_module'],
                'workcore_linked_id' => $attributes['workcore_linked_id'],
                'workcore_reference' => $attributes['workcore_reference'] ?? null,
                'workcore_status' => $attributes['workcore_status'] ?? null,
                'workcore_linked_at' => now(),
            ]);
            return $permit->fresh();
        });
    }

    private function approvalTransition(PremiseWorkPermit $permit, string $expected, string $toStatus, string $stage, array $attributes): PremiseWorkPermit
    {
        return DB::transaction(function () use ($permit, $expected, $toStatus, $stage, $attributes): PremiseWorkPermit {
            $permit = $this->locked($permit);
            if ($permit->status !== $expected) {
                throw ValidationException::withMessages(['status' => "Work permit must be {$expected} before {$stage} approval."]);
            }
            $this->recordApproval($permit, $stage, 'approved', $attributes);
            $permit->update(['status' => $toStatus]);
            event(new PremiseWorkPermitStatusChanged($permit, $toStatus));
            return $permit->fresh(['approvals']);
        });
    }

    private function recordApproval(PremiseWorkPermit $permit, string $stage, string $decision, array $attributes): PremiseWorkPermitApproval
    {
        return PremiseWorkPermitApproval::create([
            'company_id' => $permit->company_id,
            'work_permit_id' => $permit->id,
            'user_id' => $this->actorId(),
            'stage' => $stage,
            'decision' => $decision,
            'remarks' => $attributes['remarks'] ?? null,
            'evidence_document_ids' => $attributes['evidence_document_ids'] ?? [],
            'decided_at' => now(),
            'metadata' => $attributes['metadata'] ?? [],
        ]);
    }

    private function locked(PremiseWorkPermit $permit): PremiseWorkPermit
    {
        return PremiseWorkPermit::withoutGlobalScopes()->where('company_id', $permit->company_id)->whereKey($permit->id)->lockForUpdate()->firstOrFail();
    }

    private function actorId(): ?int
    {
        return function_exists('user') && user() ? user()->id : auth()->id();
    }
}
