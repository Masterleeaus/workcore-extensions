<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseComplianceRulePack extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_compliance_rule_packs';
    protected $guarded = ['id'];
    protected $casts = [
        'source_published_at' => 'date',
        'verified_at' => 'datetime',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'requires_local_verification' => 'boolean',
        'template_requirements' => 'array',
        'metadata' => 'array',
    ];

    public const STATUSES = ['draft', 'active', 'retired'];
    public const VERIFICATION_STATUSES = ['unverified', 'reviewed', 'verified', 'rejected'];

    public function requirements(): HasMany
    {
        return $this->hasMany(PremiseComplianceRequirement::class, 'rule_pack_id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified' && $this->verified_at !== null;
    }
}
