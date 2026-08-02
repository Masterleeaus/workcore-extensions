<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseConditionRecord extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_condition_records';
    protected $guarded = ['id'];
    protected $casts = [
        'scheduled_for' => 'datetime', 'recorded_at' => 'datetime', 'completed_at' => 'datetime',
        'approved_at' => 'datetime', 'score' => 'decimal:2', 'findings' => 'array', 'actions' => 'array',
        'evidence_document_ids' => 'array', 'metadata' => 'array',
    ];

    public const TYPES = [
        'entry', 'exit', 'routine', 'room', 'storage_unit', 'damage', 'cleanliness',
        'safety', 'common_area', 'asset', 'vacant_readiness', 'custom',
    ];

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function occupancy(): BelongsTo { return $this->belongsTo(PremiseOccupancy::class, 'occupancy_id'); }
    public function agreement(): BelongsTo { return $this->belongsTo(PremiseAgreement::class, 'agreement_id'); }
    public function partyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'party_role_id'); }
    public function comparisonRecord(): BelongsTo { return $this->belongsTo(self::class, 'comparison_record_id'); }
}
