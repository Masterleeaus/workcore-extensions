<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class UpdatePremiseAction
{
    public function handle(Property $property, array $data): Property
    {
        $property->fill($data);
        $property->save();

        return $property;
    }
}
