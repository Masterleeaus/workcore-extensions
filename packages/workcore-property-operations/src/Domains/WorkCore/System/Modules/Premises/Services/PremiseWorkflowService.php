<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Operations\WorkflowProgress;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Operations\WorkflowState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreement;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseIncident;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseOccupancy;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseSpace;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseWorkflow;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseWorkflowCreated;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseWorkflowStatusChanged;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremiseWorkflowStageAdvanced;

class PremiseWorkflowService
{
    public function __construct(private PremiseWorkflowRegistry $registry) {}

    public function start(Property $property, string $templateKey, array $attributes = []): PremiseWorkflow
    {
        $template = $this->registry->template($property, $templateKey);
        $status = (string) ($attributes['status'] ?? 'active');
        if (!WorkflowState::canCreate($status)) {
            throw ValidationException::withMessages(['status' => 'A workflow must begin active or paused.']);
        }

        $subjectType = (string) ($template['subject_type'] ?? 'premise');
        $this->assertSubject($property, $subjectType, $attributes);
        $stages = array_values($template['stages'] ?? []);
        $currentStage = WorkflowProgress::normaliseCurrentStage($stages, null);
        if ($currentStage === null) {
            throw ValidationException::withMessages(['template_key' => 'The selected workflow has no valid stages.']);
        }

        $workflow = PremiseWorkflow::create([
            'company_id' => $property->company_id,
            'user_id' => $attributes['user_id'] ?? $this->actorId(),
            'premise_id' => $property->id,
            'space_id' => $attributes['space_id'] ?? null,
            'occupancy_id' => $attributes['occupancy_id'] ?? null,
            'agreement_id' => $attributes['agreement_id'] ?? null,
            'incident_id' => $attributes['incident_id'] ?? null,
            'assigned_user_id' => $attributes['assigned_user_id'] ?? null,
            'profile_key' => $this->registry->operatingProfileKey($property),
            'template_key' => $templateKey,
            'subject_type' => $subjectType,
            'title' => $attributes['title'] ?? ($template['label'] ?? $templateKey),
            'status' => $status,
            'current_stage_key' => $currentStage,
            'stages_snapshot' => $stages,
            'completed_stage_keys' => [],
            'started_at' => $attributes['started_at'] ?? now(),
            'due_at' => $attributes['due_at'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'metadata' => $attributes['metadata'] ?? [],
        ]);

        event(new PremiseWorkflowCreated($workflow));
        return $workflow->fresh(['space', 'occupancy', 'agreement', 'incident']);
    }

    public function advance(PremiseWorkflow $workflow, ?string $note = null): PremiseWorkflow
    {
        return DB::transaction(function () use ($workflow, $note) {
            $workflow = $this->lockedWorkflow($workflow);
            if ($workflow->status !== 'active') {
                throw ValidationException::withMessages(['workflow' => 'Only an active workflow can advance.']);
            }

            $previousStage = $workflow->current_stage_key;
            $completed = array_values(array_unique(array_filter(array_merge(
                $workflow->completed_stage_keys ?? [],
                [$previousStage]
            ))));
            $nextStage = WorkflowProgress::nextStageKey($workflow->stages_snapshot ?? [], $previousStage);
            $metadata = $workflow->metadata ?? [];
            $history = is_array($metadata['stage_history'] ?? null) ? $metadata['stage_history'] : [];
            $history[] = [
                'stage_key' => $previousStage,
                'completed_at' => now()->toIso8601String(),
                'completed_by_user_id' => $this->actorId(),
                'note' => $note,
            ];
            $metadata['stage_history'] = $history;

            $previousStatus = $workflow->status;
            $updates = [
                'completed_stage_keys' => $completed,
                'metadata' => $metadata,
            ];

            if ($nextStage === null) {
                $updates['status'] = 'completed';
                $updates['completed_at'] = now();
            } else {
                $updates['current_stage_key'] = $nextStage;
            }

            $workflow->update($updates);
            event(new PremiseWorkflowStageAdvanced($workflow, $previousStage, $nextStage));
            if ($workflow->status !== $previousStatus) {
                event(new PremiseWorkflowStatusChanged($workflow, $previousStatus, $previousStage));
            }

            return $workflow->fresh(['space', 'occupancy', 'agreement', 'incident']);
        });
    }

    public function transition(PremiseWorkflow $workflow, string $toStatus, ?string $reason = null): PremiseWorkflow
    {
        return DB::transaction(function () use ($workflow, $toStatus, $reason) {
            $workflow = $this->lockedWorkflow($workflow);
            if ($toStatus === 'completed') {
                throw ValidationException::withMessages(['status' => 'Complete the final workflow stage instead of forcing completion.']);
            }
            if (!WorkflowState::canTransition($workflow->status, $toStatus)) {
                throw ValidationException::withMessages(['status' => 'The requested workflow status transition is not allowed.']);
            }

            $previousStatus = $workflow->status;
            $metadata = $workflow->metadata ?? [];
            if ($reason !== null && trim($reason) !== '') {
                $metadata['last_status_reason'] = trim($reason);
            }
            $statusHistory = is_array($metadata['status_history'] ?? null) ? $metadata['status_history'] : [];
            $statusHistory[] = [
                'from' => $previousStatus,
                'to' => $toStatus,
                'changed_at' => now()->toIso8601String(),
                'changed_by_user_id' => $this->actorId(),
                'reason' => $reason,
            ];
            $metadata['status_history'] = $statusHistory;

            $updates = ['status' => $toStatus, 'metadata' => $metadata];
            if ($toStatus === 'cancelled') {
                $updates['cancelled_at'] = now();
            }

            $workflow->update($updates);
            event(new PremiseWorkflowStatusChanged($workflow, $previousStatus, $workflow->current_stage_key));

            return $workflow->fresh(['space', 'occupancy', 'agreement', 'incident']);
        });
    }

    private function assertSubject(Property $property, string $subjectType, array $attributes): void
    {
        $map = [
            'space' => [PremiseSpace::class, 'space_id'],
            'occupancy' => [PremiseOccupancy::class, 'occupancy_id'],
            'agreement' => [PremiseAgreement::class, 'agreement_id'],
            'incident' => [PremiseIncident::class, 'incident_id'],
        ];

        if (!isset($map[$subjectType])) {
            return;
        }

        [$modelClass, $field] = $map[$subjectType];
        $id = $attributes[$field] ?? null;
        if (!$id) {
            throw ValidationException::withMessages([$field => "This workflow requires a linked {$subjectType}."]);
        }

        $exists = $modelClass::withoutGlobalScopes()
            ->where('company_id', $property->company_id)
            ->where('premise_id', $property->id)
            ->whereKey($id)
            ->exists();
        if (!$exists) {
            throw ValidationException::withMessages([$field => 'The selected workflow subject does not belong to this premise.']);
        }
    }

    private function lockedWorkflow(PremiseWorkflow $workflow): PremiseWorkflow
    {
        return PremiseWorkflow::withoutGlobalScopes()
            ->where('company_id', $workflow->company_id)
            ->where('premise_id', $workflow->premise_id)
            ->whereKey($workflow->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function actorId(): ?int
    {
        if (!function_exists('user')) {
            return null;
        }

        try {
            return user()->id ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
