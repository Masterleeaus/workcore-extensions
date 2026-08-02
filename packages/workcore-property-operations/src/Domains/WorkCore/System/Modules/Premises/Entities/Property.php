<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Occupancy\OccupancyState;

class Property extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_properties';

    protected $guarded = ['id'];

    protected $casts = [
        'preferred_window_start'    => 'datetime',
        'preferred_window_end'      => 'datetime',
        'special_equipment_needed'  => 'boolean',
        'terminology_overrides'      => 'array',
        'feature_overrides'          => 'array',
        'profile_attributes'         => 'array',
    ];

    public const DEFAULT_PROFILE = 'service_site';

    protected static function booted(): void
    {
        static::creating(static function (self $property): void {
            if (empty($property->public_id)) {
                $property->public_id = (string) Str::ulid();
            }
        });
    }

    /** Built-in premises profiles. Unknown keys are supported as custom profiles. */
    public const PROFILE_KEYS = [
        'service_site',
        'rooming_house',
        'residential_rental',
        'commercial_property',
        'strata_property',
        'storage_facility',
        'warehouse',
        'office_centre',
        'community_housing',
        'mixed_use',
        'short_stay',
        'hotel',
        'student_accommodation',
        'ndis_accommodation',
        'custom',
    ];

    /** Generic top-level premise types. Existing values remain valid. */
    public const PROPERTY_TYPES = [
        'house' => 'House',
        'rooming_house' => 'Rooming House',
        'apartment' => 'Apartment',
        'unit' => 'Unit',
        'building' => 'Building',
        'office' => 'Office',
        'office_centre' => 'Office Centre',
        'warehouse' => 'Warehouse',
        'storage_facility' => 'Storage Facility',
        'commercial_property' => 'Commercial Property',
        'strata_complex' => 'Strata Complex',
        'community_housing' => 'Community Housing',
        'caravan_park' => 'Caravan Park',
        'mixed_use' => 'Mixed Use',
        'jobsite' => 'Jobsite',
        'airbnb' => 'Short-Stay Property',
        'hotel' => 'Hotel',
        'motel' => 'Motel',
        'hostel' => 'Hostel',
        'resort' => 'Resort',
        'student_accommodation' => 'Student Accommodation',
        'ndis_accommodation' => 'NDIS Accommodation',
        'other' => 'Other',
    ];

    /** Cleaning frequency options. */
    public const CLEANING_FREQUENCIES = [
        'weekly'       => 'Weekly',
        'fortnightly'  => 'Fortnightly',
        'monthly'      => 'Monthly',
        'one-off'      => 'One-Off',
        'custom'       => 'Custom',
    ];

    /** Key holding status options. */
    public const KEY_HOLDING_STATUSES = [
        'office_holds'       => 'Office Holds Key',
        'customer_provides'  => 'Customer Provides',
        'lockbox'            => 'Lockbox',
        'other'              => 'Other',
    ];


    public function profileKey(): string
    {
        return $this->profile_key ?: self::DEFAULT_PROFILE;
    }

    public function profileAttributes(): array
    {
        return $this->profile_attributes ?? [];
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(PremiseSpace::class, 'premise_id')->orderBy('name');
    }

    public function rootSpaces(): HasMany
    {
        return $this->hasMany(PremiseSpace::class, 'premise_id')->whereNull('parent_space_id')->orderBy('name');
    }

    public function partyRoles(): HasMany
    {
        return $this->hasMany(PremisePartyRole::class, 'premise_id')->orderBy('display_name');
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(PremiseOccupancy::class, 'premise_id')->orderByDesc('id');
    }

    public function currentOccupancies(): HasMany
    {
        return $this->hasMany(PremiseOccupancy::class, 'premise_id')->whereIn('status', OccupancyState::CAPACITY_CONSUMING);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(PremiseAgreement::class, 'premise_id')->orderByDesc('id');
    }

    public function currentAgreements(): HasMany
    {
        return $this->hasMany(PremiseAgreement::class, 'premise_id')
            ->whereIn('status', ['offered', 'pending_signature', 'active', 'notice_given', 'ending']);
    }


    public function accommodationReservations(): HasMany
    {
        return $this->hasMany(\App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation\AccommodationReservation::class, 'premise_id')->orderByDesc('arrival_at');
    }

    public function accommodationStays(): HasMany
    {
        return $this->hasMany(\App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation\AccommodationStay::class, 'premise_id')->orderByDesc('expected_arrival_at');
    }

    public function accommodationHousekeepingTasks(): HasMany
    {
        return $this->hasMany(\App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation\AccommodationHousekeepingTask::class, 'premise_id')->orderBy('due_at');
    }

    public function units(): HasMany
    {
        return $this->hasMany(PropertyUnit::class, 'property_id');
    }

    /**
     * Pass 35 Stage D: contacts are canonical CRM records; premises hold links only.
     */
    /**
     * Pass 35 Stage E: forms are canonical WorkCore records; premises hold links only.
     */
    public function formLinks(): HasMany
    {
        return $this->hasMany(PremisesFormLink::class, 'property_id');
    }

    /**
     * Pass 35 Stage E: assets are canonical WorkCore records; premises hold links only.
     */
    public function assetLinks(): HasMany
    {
        return $this->hasMany(PremisesAssetLink::class, 'property_id');
    }

    /**
     * @deprecated Pass 35 Stage E: checklists migrated to WorkCore Forms.
     */
    public function contactLinks(): HasMany
    {
        return $this->hasMany(PremisesContactLink::class, 'property_id');
    }

    /**
     * Pass 35 Stage D: jobs are canonical WorkCore work orders; premises hold links only.
     */
    public function workOrderLinks(): HasMany
    {
        return $this->hasMany(PremisesWorkOrderLink::class, 'property_id');
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(PropertyMeterReading::class, 'property_id')->orderByDesc('reading_date');
    }

    public function accessItems(): HasMany
    {
        return $this->hasMany(PremiseAccessItem::class, 'premise_id')->orderBy('label');
    }

    public function accessAuthorisations(): HasMany
    {
        return $this->hasMany(PremiseAccessAuthorisation::class, 'premise_id')->orderByDesc('id');
    }

    public function accessCardRequests(): HasMany
    {
        return $this->hasMany(PremiseAccessCardRequest::class, 'premise_id')->orderByDesc('id');
    }

    public function workPermits(): HasMany
    {
        return $this->hasMany(PremiseWorkPermit::class, 'premise_id')->orderByDesc('id');
    }

    public function conditionRecords(): HasMany
    {
        return $this->hasMany(PremiseConditionRecord::class, 'premise_id')->orderByDesc('recorded_at');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(PremiseIncident::class, 'premise_id')->orderByDesc('reported_at');
    }


    public function complianceRequirements(): HasMany
    {
        return $this->hasMany(PremiseComplianceRequirement::class, 'premise_id')->orderBy('next_due_at');
    }

    public function complianceOccurrences(): HasMany
    {
        return $this->hasMany(PremiseComplianceOccurrence::class, 'premise_id')->orderBy('due_at');
    }

    public function complianceEvidence(): HasMany
    {
        return $this->hasMany(PremiseComplianceEvidence::class, 'premise_id')->orderByDesc('id');
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(PremiseWorkflow::class, 'premise_id')->orderByDesc('id');
    }


    public function portalGrants(): HasMany
    {
        return $this->hasMany(PremisePortalGrant::class, 'premise_id')->orderByDesc('id');
    }

    public function vacancyListings(): HasMany
    {
        return $this->hasMany(PremiseVacancyListing::class, 'premise_id')->orderByDesc('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PropertyDocument::class, 'property_id')->orderByDesc('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PropertyPhoto::class, 'property_id');
    }



    public function portfolioMemberships()
    {
        return $this->hasMany(PremisePortfolioMembership::class, 'premise_id');
    }

    public function ownershipInterests()
    {
        return $this->hasMany(PremiseOwnershipInterest::class, 'premise_id');
    }

    public function managementAuthorities()
    {
        return $this->hasMany(PremiseManagementAuthority::class, 'premise_id');
    }

    public function approvalRequests()
    {
        return $this->hasMany(PremiseApprovalRequest::class, 'premise_id');
    }
}
