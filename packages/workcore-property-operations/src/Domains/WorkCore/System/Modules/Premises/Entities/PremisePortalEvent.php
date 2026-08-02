<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremisePortalEvent extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_portal_events';
    protected $guarded = ['id'];
    protected $casts = ['occurred_at' => 'datetime', 'metadata' => 'array'];
    public function grant(): BelongsTo { return $this->belongsTo(PremisePortalGrant::class, 'portal_grant_id'); }
}
