<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseSpace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccommodationReservationSpace extends Model
{
    protected $table = 'pm_accommodation_reservation_spaces';
    protected $guarded = ['id'];
    protected $casts = [
        'arrival_at' => 'datetime', 'departure_at' => 'datetime', 'capacity' => 'integer',
        'unit_amount' => 'decimal:4', 'tax_amount' => 'decimal:4', 'total_amount' => 'decimal:4', 'metadata' => 'array',
    ];

    public function reservation(): BelongsTo { return $this->belongsTo(AccommodationReservation::class, 'reservation_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function ratePlan(): BelongsTo { return $this->belongsTo(AccommodationRatePlan::class, 'rate_plan_id'); }
}
