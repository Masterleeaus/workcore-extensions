<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation;

use App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation\AccommodationStayState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\{PremiseAccessAuthorisation,PremiseAgreement,PremiseOccupancy,PremiseSpace,Property};
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};

final class AccommodationStay extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_accommodation_stays';
    protected $guarded = ['id'];
    protected $casts = [
        'expected_arrival_at' => 'datetime', 'expected_departure_at' => 'datetime',
        'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'metadata' => 'array',
    ];

    public const STATUSES = AccommodationStayState::STATUSES;

    public function reservation(): BelongsTo { return $this->belongsTo(AccommodationReservation::class, 'reservation_id'); }
    public function guest(): BelongsTo { return $this->belongsTo(AccommodationGuest::class, 'guest_id'); }
    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function occupancy(): BelongsTo { return $this->belongsTo(PremiseOccupancy::class, 'occupancy_id'); }
    public function agreement(): BelongsTo { return $this->belongsTo(PremiseAgreement::class, 'agreement_id'); }
    public function accessAuthorisation(): BelongsTo { return $this->belongsTo(PremiseAccessAuthorisation::class, 'access_authorisation_id'); }
    public function housekeepingTasks(): HasMany { return $this->hasMany(AccommodationHousekeepingTask::class, 'stay_id'); }
    public function charges(): HasMany { return $this->hasMany(AccommodationCharge::class, 'stay_id'); }
}
