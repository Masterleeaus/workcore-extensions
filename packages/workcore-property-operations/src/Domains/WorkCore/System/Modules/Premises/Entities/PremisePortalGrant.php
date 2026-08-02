<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Portal\PortalGrantState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremisePortalGrant extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_portal_grants';
    protected $guarded = ['id'];
    protected $hidden = ['token_hash'];
    protected $casts = [
        'capabilities' => 'array',
        'invited_at' => 'datetime',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const STATUSES = PortalGrantState::STATUSES;

    protected static function booted(): void
    {
        static::updating(function (self $grant): void {
            foreach (['company_id', 'premise_id', 'portfolio_id', 'party_role_id', 'occupancy_id', 'agreement_id', 'token_hash'] as $field) {
                if ($grant->isDirty($field)) {
                    throw new \LogicException("Portal grant identity is immutable: {$field}.");
                }
            }
            if (PortalGrantState::isTerminal((string) $grant->getOriginal('status'))) {
                throw new \LogicException('Expired and revoked portal grant history is immutable.');
            }
        });
    }

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function portfolio(): BelongsTo { return $this->belongsTo(PremisePortfolio::class, 'portfolio_id'); }
    public function partyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'party_role_id'); }
    public function occupancy(): BelongsTo { return $this->belongsTo(PremiseOccupancy::class, 'occupancy_id'); }
    public function agreement(): BelongsTo { return $this->belongsTo(PremiseAgreement::class, 'agreement_id'); }
    public function events(): HasMany { return $this->hasMany(PremisePortalEvent::class, 'portal_grant_id')->orderByDesc('occurred_at'); }

    public function allows(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }
}
