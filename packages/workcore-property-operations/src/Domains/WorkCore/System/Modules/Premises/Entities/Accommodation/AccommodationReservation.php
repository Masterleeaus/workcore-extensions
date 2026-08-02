<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation;

use App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation\AccommodationReservationState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\{PremiseAgreement,PremiseOccupancy,PremiseSpace,Property};
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany,HasOne};
use Illuminate\Database\Eloquent\SoftDeletes;

final class AccommodationReservation extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $table = 'pm_accommodation_reservations';
    protected $guarded = ['id'];
    protected $casts = [
        'arrival_at' => 'datetime', 'departure_at' => 'datetime', 'booked_at' => 'datetime',
        'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime',
        'adults' => 'integer', 'children' => 'integer', 'capacity' => 'integer',
        'subtotal_amount' => 'decimal:4', 'tax_amount' => 'decimal:4', 'total_amount' => 'decimal:4',
        'deposit_amount' => 'decimal:4', 'balance_amount' => 'decimal:4', 'metadata' => 'array',
    ];

    public const STATUSES = AccommodationReservationState::STATUSES;
    public const TYPES = ['short_stay','hotel','student','rooming_house','ndis_sta','ndis_mta','internal','other'];
    public const CHANNELS = ['direct','airbnb','booking_com','vrbo','expedia','university','ndis_referral','internal','other'];

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function primarySpace(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'primary_space_id'); }
    public function occupancy(): BelongsTo { return $this->belongsTo(PremiseOccupancy::class, 'occupancy_id'); }
    public function agreement(): BelongsTo { return $this->belongsTo(PremiseAgreement::class, 'agreement_id'); }
    public function ratePlan(): BelongsTo { return $this->belongsTo(AccommodationRatePlan::class, 'rate_plan_id'); }
    public function spaces(): HasMany { return $this->hasMany(AccommodationReservationSpace::class, 'reservation_id'); }
    public function guests(): HasMany { return $this->hasMany(AccommodationGuest::class, 'reservation_id'); }
    public function stay(): HasOne { return $this->hasOne(AccommodationStay::class, 'reservation_id'); }
    public function housekeepingTasks(): HasMany { return $this->hasMany(AccommodationHousekeepingTask::class, 'reservation_id'); }
    public function charges(): HasMany { return $this->hasMany(AccommodationCharge::class, 'reservation_id'); }
    public function channelReferences(): HasMany { return $this->hasMany(AccommodationChannelReference::class, 'reservation_id'); }
}
