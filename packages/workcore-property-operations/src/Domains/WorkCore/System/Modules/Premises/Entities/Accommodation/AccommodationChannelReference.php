<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation;

use App\Domains\WorkCore\System\Modules\Premises\Entities\{PremiseSpace,Property};
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class AccommodationChannelReference extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $table = 'pm_accommodation_channel_references';
    protected $guarded = ['id'];
    protected $casts = ['last_synced_at' => 'datetime', 'metadata' => 'array'];

    public function premise(): BelongsTo { return $this->belongsTo(Property::class, 'premise_id'); }
    public function space(): BelongsTo { return $this->belongsTo(PremiseSpace::class, 'space_id'); }
    public function reservation(): BelongsTo { return $this->belongsTo(AccommodationReservation::class, 'reservation_id'); }
}
