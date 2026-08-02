<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyVisit;

class CreatePremiseVisitAction
{
    public function handle(Property $property, array $data): PropertyVisit
    {
        $data['property_id'] = $property->id;

        return PropertyVisit::create($data);
    }
}
