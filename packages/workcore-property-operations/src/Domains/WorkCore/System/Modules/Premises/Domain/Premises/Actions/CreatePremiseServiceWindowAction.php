<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyServiceWindow;

class CreatePremiseServiceWindowAction
{
    public function handle(Property $property, array $data): PropertyServiceWindow
    {
        $data['property_id'] = $property->id;

        return PropertyServiceWindow::create($data);
    }
}
