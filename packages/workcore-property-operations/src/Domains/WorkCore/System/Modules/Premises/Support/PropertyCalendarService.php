<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Support;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyServicePlan;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyVisit;

class PropertyCalendarService
{
    public function createVisitFromPlan(PropertyServicePlan $plan, array $overrides = []): PropertyVisit
    {
        return PropertyVisit::create(array_merge([
            'company_id' => $plan->company_id,
            'property_id' => $plan->property_id,
            'service_plan_id' => $plan->id,
            'visit_type' => $plan->service_type,
            'status' => 'scheduled',
        ], $overrides));
    }
}
