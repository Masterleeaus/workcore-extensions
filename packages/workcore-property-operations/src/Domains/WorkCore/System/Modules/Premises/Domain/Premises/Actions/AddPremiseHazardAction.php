<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyHazard;

class AddPremiseHazardAction
{
    public function handle(Property $property, array $data): PropertyHazard
    {
        $data['property_id'] = $property->id;

        return PropertyHazard::create($data);
    }
}
