<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Support\Exports;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class PropertyCsvExporter
{
    public function toArray(Property $property): array
    {
        return [
            'id' => $property->id,
            'name' => $property->name,
            'address' => $property->address,
            'access_notes' => $property->access_notes,
            'hazards' => $property->hazards,
        ];
    }
}
