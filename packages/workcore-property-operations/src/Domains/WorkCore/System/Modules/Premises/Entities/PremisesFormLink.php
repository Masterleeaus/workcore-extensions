<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Premises\Entities;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pass 35 Stage E — typed reference from a premises property to a canonical
 * WorkCore form/checklist (tz_form_templates).
 *
 * The premises domain does not own form/checklist identity. This model carries
 * only the link and its premises-side role (e.g., 'inspection', 'maintenance').
 */
class PremisesFormLink extends Model
{
    use BelongsToCompany;

    protected $table = 'pm_premises_form_links';

    protected $guarded = ['id'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
