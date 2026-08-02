<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Access\AccessCardRequestState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseAccessCardRequest extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_access_card_requests';
    protected $guarded = ['id'];
    protected $hidden = ['requested_for_contact'];
    protected $casts = [
        'expected_return_at' => 'datetime',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUSES = AccessCardRequestState::STATUSES;

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            foreach (['company_id', 'premise_id', 'request_number', 'legacy_security_access_card_id'] as $field) {
                if ($request->isDirty($field)) throw new \LogicException("Access-card request identity is immutable: {$field}.");
            }
            if (AccessCardRequestState::isTerminal((string) $request->getOriginal('status'))) {
                throw new \LogicException('Terminal access-card request history is immutable.');
            }
        });
    }

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function requesterPartyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'requester_party_role_id'); }
    public function occupancy(): BelongsTo { return $this->belongsTo(PremiseOccupancy::class, 'occupancy_id'); }
    public function agreement(): BelongsTo { return $this->belongsTo(PremiseAgreement::class, 'agreement_id'); }
    public function items(): HasMany { return $this->hasMany(PremiseAccessCardRequestItem::class, 'access_card_request_id')->orderBy('id'); }
}
