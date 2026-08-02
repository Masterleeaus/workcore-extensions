<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyAsset;

class AssetService
{
    public function list(Property $property)
    {
        return PropertyAsset::where('company_id', $property->company_id)
            ->where('property_id', $property->id)
            ->orderByDesc('id')
            ->get();
    }
}
