<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyMeterReading;

class RecordMeterReadingAction
{
    public function handle(Property $property, array $data): PropertyMeterReading
    {
        $data['property_id'] = $property->id;

        return PropertyMeterReading::create($data);
    }
}
