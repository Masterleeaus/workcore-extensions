<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyAsset;

class AssignPremiseAssetAction
{
    public function handle(Property $property, array $data): PropertyAsset
    {
        $data['property_id'] = $property->id;

        return PropertyAsset::create($data);
    }
}
