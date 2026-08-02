<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Premises\Actions;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyServicePlan;

class UpdatePremiseServicePlanAction
{
    public function handle(PropertyServicePlan $plan, array $data): PropertyServicePlan
    {
        $plan->fill($data);
        $plan->save();

        return $plan;
    }
}
