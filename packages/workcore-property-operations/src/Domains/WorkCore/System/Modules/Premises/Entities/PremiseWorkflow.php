<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Operations\WorkflowProgress;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Operations\WorkflowState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseWorkflow extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_workflows';
    protected $guarded = ['id'];

    protected $casts = [
        'stages_snapshot' => 'array',
        'completed_stage_keys' => 'array',
        'started_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUSES = WorkflowState::STATUSES;

    protected static function booted(): void
    {
        static::updating(function (self $workflow): void {
            foreach (['company_id', 'premise_id', 'profile_key', 'template_key', 'subject_type', 'stages_snapshot'] as $field) {
                if ($workflow->isDirty($field)) {
                    throw new \LogicException("Workflow identity and stage snapshots are immutable: {$field}.");
                }
            }

            $originalStatus = (string) $workflow->getOriginal('status');
            if (WorkflowState::isTerminal($originalStatus)) {
                throw new \LogicException('Completed and cancelled workflow history is immutable.');
            }
        });
    }

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function occupancy(): BelongsTo { return $this->belongsTo(PremiseOccupancy::class, 'occupancy_id'); }
    public function agreement(): BelongsTo { return $this->belongsTo(PremiseAgreement::class, 'agreement_id'); }
    public function incident(): BelongsTo { return $this->belongsTo(PremiseIncident::class, 'incident_id'); }

    public function completionPercent(): int
    {
        if ($this->status === 'completed') {
            return 100;
        }

        $total = count(WorkflowProgress::stageKeys($this->stages_snapshot ?? []));
        if ($total === 0) {
            return 0;
        }

        $completed = count(array_unique($this->completed_stage_keys ?? []));
        return min(99, (int) round(($completed / $total) * 100));
    }

    public function currentStageLabel(): ?string
    {
        foreach ($this->stages_snapshot ?? [] as $stage) {
            if (($stage['key'] ?? null) === $this->current_stage_key) {
                return $stage['label'] ?? $this->current_stage_key;
            }
        }

        return $this->current_stage_key;
    }
}
