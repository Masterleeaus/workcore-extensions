<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseComplianceOccurrence extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_compliance_occurrences';
    protected $guarded = ['id'];
    protected $casts = [
        'due_at' => 'datetime',
        'warning_at' => 'datetime',
        'completed_at' => 'datetime',
        'waived_at' => 'datetime',
        'generated_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function requirement(): BelongsTo { return $this->belongsTo(PremiseComplianceRequirement::class, 'requirement_id'); }
    public function selectedEvidence(): BelongsTo { return $this->belongsTo(PremiseComplianceEvidence::class, 'evidence_id'); }
    public function evidence(): HasMany { return $this->hasMany(PremiseComplianceEvidence::class, 'occurrence_id'); }
}
