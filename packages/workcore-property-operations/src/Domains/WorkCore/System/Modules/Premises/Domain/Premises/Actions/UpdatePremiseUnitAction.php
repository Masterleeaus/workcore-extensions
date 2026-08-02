<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyUnit;

class UpdatePremiseUnitAction
{
    public function handle(PropertyUnit $unit, array $data): PropertyUnit
    {
        $unit->fill($data);
        $unit->save();

        return $unit;
    }
}
