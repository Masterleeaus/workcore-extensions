<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation;

use App\Domains\WorkCore\System\Modules\Premises\Domain\Accommodation\HousekeepingState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\{PremiseSpace,Property};
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AccommodationHousekeepingTask extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $table = 'pm_accommodation_housekeeping_tasks';
    protected $guarded = ['id'];
    protected $casts = [
        'due_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        'inspected_at' => 'datetime', 'metadata' => 'array',
    ];

    public const STATUSES = HousekeepingState::STATUSES;
    public const TYPES = ['turnover','stayover','deep_clean','inspection','linen','maintenance_hold','other'];

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function reservation(): BelongsTo { return $this->belongsTo(AccommodationReservation::class, 'reservation_id'); }
    public function stay(): BelongsTo { return $this->belongsTo(AccommodationStay::class, 'stay_id'); }
}
