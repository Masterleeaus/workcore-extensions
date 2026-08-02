<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Portfolio\ManagementAuthorityState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseManagementAuthority extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_management_authorities';
    protected $guarded = ['id'];
    protected $hidden = ['titan_money_policy_reference'];
    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'notice_given_at' => 'date',
        'ended_at' => 'date', 'notice_period_days' => 'integer',
        'authorised_actions' => 'array', 'restrictions' => 'array',
        'approved_at' => 'datetime', 'metadata' => 'array',
    ];

    public const STATUSES = ManagementAuthorityState::STATUSES;
    public const TYPES = [
        'full_management', 'leasing_only', 'maintenance_only', 'rooming_house_operation',
        'storage_operation', 'commercial_management', 'portfolio_management', 'custom',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $authority): void {
            foreach (['company_id', 'premise_id', 'party_role_id', 'authority_type'] as $field) {
                if ($authority->isDirty($field)) throw new \LogicException("Management authority identity is immutable: {$field}.");
            }
            if (ManagementAuthorityState::isTerminal((string) $authority->getOriginal('status'))) {
                throw new \LogicException('Terminal management authority history is immutable.');
            }
        });
    }

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function partyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'party_role_id'); }
    public function agreement(): BelongsTo { return $this->belongsTo(PremiseAgreement::class, 'agreement_id'); }
    public function document(): BelongsTo { return $this->belongsTo(PropertyDocument::class, 'document_id'); }
}
