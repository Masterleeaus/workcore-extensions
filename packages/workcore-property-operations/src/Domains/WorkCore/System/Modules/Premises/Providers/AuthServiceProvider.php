<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyVisit;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyInspection;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyServicePlan;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyMeterReading;

use App\Domains\WorkCore\System\Modules\Premises\Policies\PropertyPolicy;
use App\Domains\WorkCore\System\Modules\Premises\Policies\PropertyVisitPolicy;
use App\Domains\WorkCore\System\Modules\Premises\Policies\PropertyInspectionPolicy;
use App\Domains\WorkCore\System\Modules\Premises\Policies\PropertyServicePlanPolicy;
use App\Domains\WorkCore\System\Modules\Premises\Policies\PropertyMeterReadingPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Property::class => PropertyPolicy::class,
        PropertyVisit::class => PropertyVisitPolicy::class,
        PropertyInspection::class => PropertyInspectionPolicy::class,
        PropertyServicePlan::class => PropertyServicePlanPolicy::class,
        PropertyMeterReading::class => PropertyMeterReadingPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
