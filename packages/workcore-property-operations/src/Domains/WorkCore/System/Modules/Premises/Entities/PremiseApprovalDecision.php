<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseApprovalDecision extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_approval_decisions';
    protected $guarded = ['id'];
    protected $casts = ['decided_at' => 'datetime', 'metadata' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Approval decisions are append-only.'));
        static::deleting(fn () => throw new \LogicException('Approval decisions cannot be deleted.'));
    }

    public function approvalRequest(): BelongsTo { return $this->belongsTo(PremiseApprovalRequest::class, 'approval_request_id'); }
    public function partyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'party_role_id'); }
}
