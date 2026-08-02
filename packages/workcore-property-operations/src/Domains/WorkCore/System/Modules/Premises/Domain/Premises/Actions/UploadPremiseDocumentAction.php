<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyDocument;

class UploadPremiseDocumentAction
{
    public function handle(Property $property, array $data): PropertyDocument
    {
        $data['property_id'] = $property->id;

        return PropertyDocument::create($data);
    }
}
