<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Integrations\Core;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyVisit;

interface TaskAdapterInterface
{
    /** Create or update a core Task when a visit is scheduled. Return core task id or null. */
    public function upsertTaskForVisit(PropertyVisit $visit): ?int;
}
