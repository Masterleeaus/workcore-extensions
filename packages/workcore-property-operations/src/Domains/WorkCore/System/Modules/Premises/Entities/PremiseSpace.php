<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Occupancy\OccupancyState;
use Illuminate\Support\Str;

class PremiseSpace extends Model
{
    use BelongsToCompany;

    protected static function booted(): void
    {
        static::creating(static function (self $space): void {
            if (empty($space->public_id)) {
                $space->public_id = (string) Str::ulid();
            }
        });
    }

    protected $table = 'pm_premise_spaces';
    protected $guarded = ['id'];

    protected $casts = [
        'capacity' => 'integer',
        'area' => 'decimal:2',
        'is_occupiable' => 'boolean',
        'is_bookable' => 'boolean',
        'is_billable' => 'boolean',
        'is_shared' => 'boolean',
        'metadata' => 'array',
    ];

    public const AVAILABILITY_STATUSES = [
        'available', 'reserved', 'occupied', 'unavailable', 'maintenance',
        'cleaning', 'inspection_hold', 'compliance_hold', 'owner_hold', 'decommissioned',
    ];

    public function premise(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'premise_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_space_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_space_id')->orderBy('name');
    }

    public function partyRoles(): HasMany
    {
        return $this->hasMany(PremisePartyRole::class, 'space_id')->orderBy('display_name');
    }

    public function occupancies(): HasMany
    {
        return $this->hasMany(PremiseOccupancy::class, 'space_id')->orderByDesc('id');
    }

    public function currentOccupancies(): HasMany
    {
        return $this->hasMany(PremiseOccupancy::class, 'space_id')->whereIn('status', OccupancyState::CAPACITY_CONSUMING);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(PremiseAgreement::class, 'space_id')->orderByDesc('id');
    }


    public function accommodationReservations(): HasMany
    {
        return $this->hasMany(\App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation\AccommodationReservation::class, 'primary_space_id')->orderByDesc('arrival_at');
    }

    public function accommodationStays(): HasMany
    {
        return $this->hasMany(\App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation\AccommodationStay::class, 'space_id')->orderByDesc('expected_arrival_at');
    }

    public function accommodationHousekeepingTasks(): HasMany
    {
        return $this->hasMany(\App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation\AccommodationHousekeepingTask::class, 'space_id')->orderBy('due_at');
    }

    public function accessItems(): HasMany
    {
        return $this->hasMany(PremiseAccessItem::class, 'space_id')->orderBy('label');
    }

    public function accessAuthorisations(): HasMany
    {
        return $this->hasMany(PremiseAccessAuthorisation::class, 'space_id')->orderByDesc('id');
    }

    public function accessCardRequests(): HasMany
    {
        return $this->hasMany(PremiseAccessCardRequest::class, 'space_id')->orderByDesc('id');
    }

    public function workPermits(): HasMany
    {
        return $this->hasMany(PremiseWorkPermit::class, 'space_id')->orderByDesc('id');
    }

    public function conditionRecords(): HasMany
    {
        return $this->hasMany(PremiseConditionRecord::class, 'space_id')->orderByDesc('recorded_at');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(PremiseIncident::class, 'space_id')->orderByDesc('reported_at');
    }


    public function vacancyListings(): HasMany
    {
        return $this->hasMany(PremiseVacancyListing::class, 'space_id')->orderByDesc('id');
    }

    public function occupiedCapacity(): int
    {
        if ($this->relationLoaded('currentOccupancies')) {
            return (int) $this->currentOccupancies->sum('capacity_used');
        }

        return (int) $this->currentOccupancies()->sum('capacity_used');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_space_id');
    }
}
