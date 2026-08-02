<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseAgreementParty extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_agreement_parties';
    protected $guarded = ['id'];

    protected $casts = [
        'signed_at' => 'datetime',
        'is_primary' => 'boolean',
        'metadata' => 'array',
    ];

    public const AGREEMENT_ROLES = [
        'provider',
        'owner',
        'manager',
        'resident',
        'tenant',
        'licensee',
        'storage_customer',
        'business_occupier',
        'guarantor',
        'authorised_representative',
        'witness',
        'billing_contact',
        'occupant',
        'other',
    ];

    public const SIGNING_STATUSES = [
        'not_required',
        'pending',
        'signed',
        'declined',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(PremiseAgreement::class, 'agreement_id');
    }

    public function partyRole(): BelongsTo
    {
        return $this->belongsTo(PremisePartyRole::class, 'party_role_id');
    }
}
