<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class CreatePremiseAction
{
    public function handle(array $data): Property
    {
        return Property::create($data);
    }
}
