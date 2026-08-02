<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Integrations\Core;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyVisit;

class NullHrAdapter implements HrAdapterInterface
{
    public function reflectAssignment(PropertyVisit $visit): void
    {
        // no-op
    }
}
