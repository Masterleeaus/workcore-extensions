<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Integrations\Core;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyVisit;

interface HrAdapterInterface
{
    /** Optionally map assigned_to to a roster/shift/attendance record. */
    public function reflectAssignment(PropertyVisit $visit): void;
}
