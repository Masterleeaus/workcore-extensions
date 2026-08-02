<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Services\PropertyProfileService;
use App\Domains\WorkCore\System\Modules\Premises\Http\Controllers\Concerns\EnsuresManagedPremisesPermissions;

class PropertyProfileController extends Controller
{
    
    use EnsuresManagedPremisesPermissions;

    public function show(Property $property, PropertyProfileService $service)
    {
        $this->ensureCanViewManagedPremises();
        abort_unless(auth()->check(), 401);
        abort_unless(auth()->user()->can('managedpremises.view'), 403);

        return response()->json($service->summary($property));
    }
}