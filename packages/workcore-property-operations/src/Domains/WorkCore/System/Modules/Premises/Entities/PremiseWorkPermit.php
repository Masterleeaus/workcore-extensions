<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Permits\WorkPermitState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseWorkPermit extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_work_permits';
    protected $guarded = ['id'];
    protected $hidden = ['contact_phone'];
    protected $casts = [
        'work_categories' => 'array',
        'hazards' => 'array',
        'controls' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'induction_required' => 'boolean',
        'induction_completed_at' => 'datetime',
        'validated_at' => 'datetime',
        'evidence_document_ids' => 'array',
        'workcore_linked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUSES = WorkPermitState::STATUSES;
    public const TYPES = [
        'renovation', 'maintenance', 'fitout', 'inspection', 'cleaning',
        'delivery_installation', 'hot_work', 'confined_space', 'electrical',
        'mechanical', 'other',
    ];
    public const RISK_LEVELS = ['low', 'medium', 'high', 'critical'];

    protected static function booted(): void
    {
        static::updating(function (self $permit): void {
            foreach (['company_id', 'premise_id', 'permit_number', 'legacy_security_work_permit_id'] as $field) {
                if ($permit->isDirty($field)) throw new \LogicException("Work-permit identity is immutable: {$field}.");
            }
            if (WorkPermitState::isTerminal((string) $permit->getOriginal('status'))) {
                throw new \LogicException('Terminal work-permit history is immutable.');
            }
        });
    }

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function applicantPartyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'applicant_party_role_id'); }
    public function approvals(): HasMany { return $this->hasMany(PremiseWorkPermitApproval::class, 'work_permit_id')->orderBy('decided_at'); }
}
