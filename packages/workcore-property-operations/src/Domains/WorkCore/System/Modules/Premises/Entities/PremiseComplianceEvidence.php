<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseComplianceEvidence extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_compliance_evidence';
    protected $guarded = ['id'];
    protected $casts = [
        'issued_on' => 'date',
        'expires_on' => 'date',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUSES = ['draft', 'submitted', 'verified', 'rejected', 'superseded'];

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function requirement(): BelongsTo { return $this->belongsTo(PremiseComplianceRequirement::class, 'requirement_id'); }
    public function occurrence(): BelongsTo { return $this->belongsTo(PremiseComplianceOccurrence::class, 'occurrence_id'); }
    public function document(): BelongsTo { return $this->belongsTo(PropertyDocument::class, 'document_id'); }
}
