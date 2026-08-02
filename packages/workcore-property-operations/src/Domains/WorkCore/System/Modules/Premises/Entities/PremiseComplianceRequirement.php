<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseComplianceRequirement extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_compliance_requirements';
    protected $guarded = ['id'];
    protected $casts = [
        'source_verified_at' => 'datetime',
        'starts_on' => 'date',
        'initial_due_at' => 'datetime',
        'next_due_at' => 'datetime',
        'last_completed_at' => 'datetime',
        'evidence_required' => 'boolean',
        'evidence_types' => 'array',
        'metadata' => 'array',
    ];

    public const TYPES = [
        'registration_review', 'licence_review', 'capacity_review', 'fire_safety_review',
        'electrical_safety_review', 'gas_safety_review', 'pool_barrier_review',
        'emergency_plan_review', 'insurance_review', 'access_review', 'hazard_review',
        'condition_schedule_review', 'management_authority_review',
        'property_safety_review', 'customer_acknowledgement_review',
        'contractual_obligation', 'internal_policy', 'custom',
    ];
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    public const RECURRENCE_TYPES = ['none', 'days', 'months', 'years', 'annual'];

    public function recurrenceType(): string { return (string) $this->recurrence_type; }

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function responsiblePartyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'responsible_party_role_id'); }
    public function rulePack(): BelongsTo { return $this->belongsTo(PremiseComplianceRulePack::class, 'rule_pack_id'); }
    public function occurrences(): HasMany { return $this->hasMany(PremiseComplianceOccurrence::class, 'requirement_id')->orderByDesc('sequence'); }
    public function evidence(): HasMany { return $this->hasMany(PremiseComplianceEvidence::class, 'requirement_id')->orderByDesc('id'); }
}
