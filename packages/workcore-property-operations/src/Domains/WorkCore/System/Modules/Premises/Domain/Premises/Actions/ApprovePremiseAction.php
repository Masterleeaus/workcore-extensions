<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyApproval;

class ApprovePremiseAction
{
    public function handle(Property $property, array $data): PropertyApproval
    {
        $data['property_id'] = $property->id;

        return PropertyApproval::create($data);
    }
}
