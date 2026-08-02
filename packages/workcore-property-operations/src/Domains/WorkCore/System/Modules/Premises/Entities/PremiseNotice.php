<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Communications\NoticeState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseNotice extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_notices';
    protected $guarded = ['id'];
    protected $casts = ['requires_acknowledgement' => 'boolean', 'approval_requested_at' => 'datetime', 'approved_at' => 'datetime', 'issued_at' => 'datetime', 'acknowledged_at' => 'datetime', 'withdrawn_at' => 'datetime', 'expires_at' => 'datetime', 'metadata' => 'array'];
    public const STATUSES = NoticeState::STATUSES;
    protected static function booted(): void
    {
        static::updating(function (self $notice): void {
            foreach (['company_id', 'premise_id', 'portal_grant_id', 'party_role_id', 'occupancy_id', 'agreement_id', 'notice_type'] as $field) {
                if ($notice->isDirty($field)) throw new \LogicException("Notice identity is immutable: {$field}.");
            }
            if (NoticeState::isTerminal((string) $notice->getOriginal('status'))) throw new \LogicException('Terminal notice history is immutable.');
            if (in_array($notice->getOriginal('status'), ['issued'], true) && ($notice->isDirty('title') || $notice->isDirty('body'))) throw new \LogicException('Issued notice content is immutable.');
        });
    }
    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function portalGrant(): BelongsTo { return $this->belongsTo(PremisePortalGrant::class, 'portal_grant_id'); }
}
