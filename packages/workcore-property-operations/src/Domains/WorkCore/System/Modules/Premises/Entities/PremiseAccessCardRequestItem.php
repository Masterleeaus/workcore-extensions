<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseAccessCardRequestItem extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_access_card_request_items';
    protected $guarded = ['id'];
    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'issued_at' => 'datetime',
        'returned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUSES = ['requested', 'approved', 'rejected', 'issued', 'returned', 'revoked', 'cancelled'];

    protected static function booted(): void
    {
        static::updating(function (self $item): void {
            foreach (['company_id', 'access_card_request_id', 'holder_party_role_id', 'legacy_security_access_card_item_id'] as $field) {
                if ($item->isDirty($field)) throw new \LogicException("Access-card request item identity is immutable: {$field}.");
            }
            if (in_array((string) $item->getOriginal('status'), ['rejected', 'returned', 'revoked', 'cancelled'], true)) {
                throw new \LogicException('Terminal access-card item history is immutable.');
            }
        });
    }

    public function request(): BelongsTo { return $this->belongsTo(PremiseAccessCardRequest::class, 'access_card_request_id'); }
    public function holderPartyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'holder_party_role_id'); }
    public function accessItem(): BelongsTo { return $this->belongsTo(PremiseAccessItem::class, 'access_item_id'); }
}
