<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyKey;

class AddPremiseKeyAction
{
    public function handle(Property $property, array $data): PropertyKey
    {
        $data['property_id'] = $property->id;

        return PropertyKey::create($data);
    }
}
