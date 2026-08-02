<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pass 35 Stage D — typed reference from a premises property to a canonical
 * WorkCore CRM contact (tz_customer_contacts).
 *
 * The premises domain does not own contact identity. Name, email and phone
 * live in CRM; this model carries only the premises-side role
 * (owner, agent, tenant, emergency, contact) and primacy flag.
 */
class PremisesContactLink extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premises_contact_links';

    protected $guarded = ['id'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
