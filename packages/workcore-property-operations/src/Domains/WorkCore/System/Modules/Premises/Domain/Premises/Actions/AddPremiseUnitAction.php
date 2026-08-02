<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyUnit;

class AddPremiseUnitAction
{
    public function handle(Property $property, array $data): PropertyUnit
    {
        $data['property_id'] = $property->id;

        return PropertyUnit::create($data);
    }
}
