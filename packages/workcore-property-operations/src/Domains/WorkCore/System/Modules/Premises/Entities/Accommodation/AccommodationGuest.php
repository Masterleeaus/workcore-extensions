<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation;

use App\Domains\WorkCore\System\Modules\Premises\Entities\{PremiseOccupancy,PremisePartyRole};
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AccommodationGuest extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $table = 'pm_accommodation_guests';
    protected $guarded = ['id'];
    protected $casts = [
        'date_of_birth' => 'date', 'is_primary' => 'boolean',
        'emergency_contact' => 'array', 'metadata' => 'array',
    ];

    public const TYPES = ['guest','student','resident','participant','support_person','child','staff','other'];

    public function reservation(): BelongsTo { return $this->belongsTo(AccommodationReservation::class, 'reservation_id'); }
    public function partyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'party_role_id'); }
    public function occupancy(): BelongsTo { return $this->belongsTo(PremiseOccupancy::class, 'occupancy_id'); }
}
