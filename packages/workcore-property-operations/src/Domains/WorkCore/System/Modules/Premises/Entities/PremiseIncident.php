<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;

class PremiseIncident extends Model
{
    use BelongsToCompany;
    protected $table = 'pm_premise_incidents';
    protected $guarded = ['id'];
    protected $casts = [
        'occurred_at' => 'datetime', 'reported_at' => 'datetime', 'resolved_at' => 'datetime',
        'workcore_linked_at' => 'datetime', 'metadata' => 'array',
    ];

    public const TYPES = [
        'damage', 'security', 'lost_key', 'noise', 'access_failure', 'water_leak',
        'electrical', 'welfare', 'pest', 'illegal_dumping', 'fire_safety', 'injury',
        'complaint', 'other',
    ];
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    public const PRIVACY_LEVELS = ['standard', 'sensitive', 'restricted'];

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function occupancy(): BelongsTo { return $this->belongsTo(PremiseOccupancy::class, 'occupancy_id'); }
    public function reportedByPartyRole(): BelongsTo { return $this->belongsTo(PremisePartyRole::class, 'reported_by_party_role_id'); }
}
