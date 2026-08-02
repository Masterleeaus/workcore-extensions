<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pass 35 Stage D — typed reference from a premises property to a canonical
 * WorkCore work order (tz_work_orders).
 *
 * The premises domain does not own job identity. This model carries only the
 * link and its premises-side role/notes; all job state lives in Operations.
 */
class PremisesWorkOrderLink extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premises_work_order_links';

    protected $guarded = ['id'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
