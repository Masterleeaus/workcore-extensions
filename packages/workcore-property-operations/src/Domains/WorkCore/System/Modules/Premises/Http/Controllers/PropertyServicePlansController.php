<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Controllers;


use App\Domains\WorkCore\System\Modules\Premises\Http\Controllers\Concerns\EnsuresManagedPremisesPermissions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyServicePlan;
use App\Domains\WorkCore\System\Modules\Premises\Http\Requests\StorePropertyServicePlanRequest;
use App\Domains\WorkCore\System\Modules\Premises\Http\Requests\UpdatePropertyServicePlanRequest;

class PropertyServicePlansController extends Controller
{
    
    use EnsuresManagedPremisesPermissions;
public function index(Property $property)
    {
        $this->ensureCanViewManagedPremises();
        $this->authorize('view', $property);
        $plans = PropertyServicePlan::company()->where('property_id', $property->id)->latest()->paginate(20);
        return view('managedpremises::service_plans.index', compact('property','plans'));
    }

    public function create(Property $property)
    {
        $this->ensureCanViewManagedPremises();
        $this->authorize('update', $property);
        return view('managedpremises::service_plans.create', compact('property'));
    }

    public function store(StorePropertyServicePlanRequest $request, Property $property)
    {
        $this->ensureCanViewManagedPremises();
        $this->authorize('update', $property);
        $data = $request->validated();
        $data['company_id'] = company()->id;
        $data['property_id'] = $property->id;
        PropertyServicePlan::create($data);
        return redirect()->route('managedpremises.properties.show', $property)->with('success', __('managedpremises::app.saved'));
    }

    public function edit(Property $property, PropertyServicePlan $plan)
    {
        $this->ensureCanViewManagedPremises();
        $this->authorize('update', $property);
        abort_unless($plan->property_id === $property->id, 404);
        return view('managedpremises::service_plans.edit', compact('property','plan'));
    }

    public function update(UpdatePropertyServicePlanRequest $request, Property $property, PropertyServicePlan $plan)
    {
        $this->ensureCanViewManagedPremises();
        $this->authorize('update', $property);
        abort_unless($plan->property_id === $property->id, 404);
        $plan->update($request->validated());
        return redirect()->route('managedpremises.properties.show', $property)->with('success', __('managedpremises::app.updated'));
    }

    public function destroy(Property $property, PropertyServicePlan $plan)
    {
        $this->ensureCanViewManagedPremises();
        $this->authorize('update', $property);
        abort_unless($plan->property_id === $property->id, 404);
        $plan->delete();
        return back()->with('success', __('managedpremises::app.deleted'));
    }
}
