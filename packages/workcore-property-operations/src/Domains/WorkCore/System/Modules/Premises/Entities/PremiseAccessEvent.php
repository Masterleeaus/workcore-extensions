<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseAccessEvent extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_access_events';
    protected $guarded = ['id'];
    protected $casts = ['occurred_at' => 'datetime', 'expected_return_at' => 'datetime', 'metadata' => 'array'];

    public const TYPES = ['created', 'issued', 'returned', 'lost', 'found', 'revoked', 'expired', 'decommissioned', 'transferred', 'audited'];
    public function accessItem(): BelongsTo { return $this->belongsTo(PremiseAccessItem::class, 'access_item_id'); }
    public function partyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'party_role_id'); }
}
