<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class ArchivePremiseAction
{
    public function handle(Property $property): void
    {
        if (in_array('status', $property->getFillable(), true) || isset($property->status)) {
            $property->status = 'archived';
            $property->save();
            return;
        }

        $property->delete();
    }
}
