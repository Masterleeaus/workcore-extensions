<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Integrations\Core;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyVisit;

class NullTaskAdapter implements TaskAdapterInterface
{
    public function upsertTaskForVisit(PropertyVisit $visit): ?int
    {
        return null;
    }
}
