<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseOwnershipInterest extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_ownership_interests';
    protected $guarded = ['id'];
    protected $hidden = ['title_reference'];
    protected $casts = [
        'share_percent' => 'decimal:4', 'is_primary' => 'boolean',
        'valid_from' => 'date', 'valid_until' => 'date', 'metadata' => 'array',
    ];

    public const TYPES = ['sole', 'joint', 'company', 'trust', 'strata', 'investor', 'beneficial', 'other'];

    protected static function booted(): void
    {
        static::updating(function (self $interest): void {
            foreach (['company_id', 'premise_id', 'party_role_id', 'ownership_type', 'valid_from'] as $field) {
                if ($interest->isDirty($field)) throw new \LogicException("Ownership identity is immutable: {$field}.");
            }
            if ($interest->getOriginal('valid_until')) throw new \LogicException('Ended ownership history is immutable.');
        });
    }

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function partyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'party_role_id'); }
    public function document(): BelongsTo { return $this->belongsTo(PropertyDocument::class, 'document_id'); }
}
