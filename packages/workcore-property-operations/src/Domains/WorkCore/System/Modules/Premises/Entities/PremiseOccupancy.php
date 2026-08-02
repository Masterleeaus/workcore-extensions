<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Occupancy\OccupancyState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

use Illuminate\Support\Str;

class PremiseOccupancy extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premise_occupancies';


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
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_end_date' => 'date',
        'capacity_used' => 'integer',
        'is_primary' => 'boolean',
        'metadata' => 'array',
    ];

    public const STATUSES = OccupancyState::STATUSES;
    public const INITIAL_STATUSES = OccupancyState::INITIAL_STATUSES;

    public const OCCUPANCY_TYPES = [
        'resident', 'tenant', 'licensee', 'storage_customer', 'owner_occupier',
        'business_occupier', 'staff_allocation', 'temporary_guest',
        'service_client', 'internal_allocation', 'occupant', 'other',
    ];

    public function premise(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'premise_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(PremiseSpace::class, 'space_id');
    }

    public function partyRole(): BelongsTo
    {
        return $this->belongsTo(PremisePartyRole::class, 'party_role_id');
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(PremiseAgreement::class, 'occupancy_id')->orderByDesc('id');
    }

    public function previousOccupancy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_occupancy_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(self::class, 'previous_occupancy_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereIn('status', OccupancyState::CAPACITY_CONSUMING);
    }

    public function scopeHistory(Builder $query): Builder
    {
        return $query->whereIn('status', ['vacated', 'cancelled']);
    }
}
