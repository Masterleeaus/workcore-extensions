<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseAccessAuthorisation extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_access_authorisations';
    protected $guarded = ['id'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'revoked_at' => 'datetime', 'metadata' => 'array'];

    public const STATUSES = ['active', 'expired', 'revoked', 'cancelled'];
    public const SCOPES = ['premise', 'space', 'common_areas', 'emergency', 'contractor', 'staff', 'custom'];
    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function partyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'party_role_id'); }
}
