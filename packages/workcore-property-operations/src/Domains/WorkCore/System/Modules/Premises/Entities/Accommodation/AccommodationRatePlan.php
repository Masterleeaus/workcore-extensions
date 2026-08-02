<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AccommodationRatePlan extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $table = 'pm_accommodation_rate_plans';
    protected $guarded = ['id'];
    protected $casts = [
        'base_amount' => 'decimal:4',
        'minimum_nights' => 'integer',
        'maximum_nights' => 'integer',
        'active' => 'boolean',
        'restrictions' => 'array',
        'cancellation_policy' => 'array',
        'metadata' => 'array',
    ];

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function reservations(): HasMany { return $this->hasMany(AccommodationReservation::class, 'rate_plan_id'); }
    public function reservedSpaces(): HasMany { return $this->hasMany(AccommodationReservationSpace::class, 'rate_plan_id'); }
}
