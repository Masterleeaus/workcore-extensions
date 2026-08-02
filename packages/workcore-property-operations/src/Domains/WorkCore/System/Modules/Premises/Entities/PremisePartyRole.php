<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

use Illuminate\Support\Str;

class PremisePartyRole extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_party_roles';


    protected static function booted(): void
    {
        static::creating(static function (self $record): void {
            if (empty($record->public_id)) {
                $record->public_id = (string) Str::ulid();
            }
        });
    }

    protected $guarded = ['id'];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_primary' => 'boolean',
        'permissions' => 'array',
        'metadata' => 'array',
    ];

    public const ROLES = [
        'owner', 'property_manager', 'real_estate_agent', 'resident', 'tenant',
        'licensee', 'storage_customer', 'business_occupier', 'guarantor',
        'emergency_contact', 'support_worker', 'advocate', 'facility_manager',
        'maintenance_contractor', 'cleaner', 'authorised_visitor', 'billing_contact',
        'guest', 'student', 'participant', 'support_person',
        'contact', 'other',
    ];

    public function premise(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'premise_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(PremiseSpace::class, 'space_id');
    }

    public function agreementParties(): HasMany
    {
        return $this->hasMany(PremiseAgreementParty::class, 'party_role_id');
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(PremiseOccupancy::class, 'party_role_id');
    }


    public function ownershipInterests(): HasMany
    {
        return $this->hasMany(PremiseOwnershipInterest::class, 'party_role_id');
    }

    public function managementAuthorities(): HasMany
    {
        return $this->hasMany(PremiseManagementAuthority::class, 'party_role_id');
    }

    public function requestedApprovals(): HasMany
    {
        return $this->hasMany(PremiseApprovalRequest::class, 'requested_party_role_id');
    }
}
