<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities\Accommodation;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreement;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AccommodationCharge extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_accommodation_charges';
    protected $guarded = ['id'];
    protected $casts = [
        'quantity' => 'decimal:4', 'unit_amount' => 'decimal:4', 'tax_amount' => 'decimal:4',
        'total_amount' => 'decimal:4', 'occurred_at' => 'datetime', 'posted_at' => 'datetime',
        'voided_at' => 'datetime', 'metadata' => 'array',
    ];

    public const STATUSES = ['pending','posted','void','refunded'];
    public const TYPES = ['accommodation','cleaning','service','damage','deposit','tax','discount','refund','other'];

    public function reservation(): BelongsTo { return $this->belongsTo(AccommodationReservation::class, 'reservation_id'); }
    public function stay(): BelongsTo { return $this->belongsTo(AccommodationStay::class, 'stay_id'); }
    public function agreement(): BelongsTo { return $this->belongsTo(PremiseAgreement::class, 'agreement_id'); }
}
