<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Http\Controllers;


use App\Domains\WorkCore\System\Modules\Premises\Http\Controllers\Concerns\EnsuresManagedPremisesPermissions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyApproval;
use App\Domains\WorkCore\System\Modules\Premises\Services\PremiseApprovalService;

class PropertyApprovalsController extends Controller
{
    
    use EnsuresManagedPremisesPermissions;
public function index(Property $property)
    {
        $this->ensureCanViewManagedPremises();
        $this->authorize('view', $property);
        $approvals = PropertyApproval::company()->where('property_id', $property->id)->latest()->paginate(20);
        return view('managedpremises::approvals.index', compact('property','approvals'));
    }

    public function create(Property $property)
    {
        $this->ensureCanViewManagedPremises();
        $this->authorize('update', $property);
        return view('managedpremises::approvals.create', compact('property'));
    }

    public function store(Request $request, Property $property, PremiseApprovalService $approvalService)
    {
        $this->ensureCanViewManagedPremises();
        $this->authorize('update', $property);

        $data = $request->validate([
            'subject' => ['required','string','max:190'],
            'requested_to' => ['nullable','integer'],
            'request_payload' => ['nullable','array'],
        ]);

        $legacyApproval = PropertyApproval::create([
            'company_id' => company()->id,
            'user_id' => user()->id ?? null,
            'property_id' => $property->id,
            'subject' => $data['subject'],
            'requested_by' => user()->id ?? null,
            'requested_to' => $data['requested_to'] ?? null,
            'status' => 'pending',
            'request_payload' => $data['request_payload'] ?? null,
            'requested_at' => now(),
        ]);
        $approvalService->importLegacy($legacyApproval);

        return redirect()->route('managedpremises.properties.show', $property)->with('success', __('managedpremises::app.saved'));
    }

    public function decide(Request $request, Property $property, PropertyApproval $approval, PremiseApprovalService $approvalService)
    {
        $this->ensureCanViewManagedPremises();
        $this->authorize('update', $property);
        abort_unless($approval->property_id === $property->id, 404);

        $data = $request->validate([
            'decision' => ['required','in:approved,rejected'],
            'note' => ['nullable','string'],
        ]);

        $canonical = $approvalService->importLegacy($approval);
        $approvalService->decide(
            $canonical,
            $data['decision'] === 'approved' ? 'approve' : 'reject',
            null,
            user()->id ?? null,
            $data['note'] ?? null,
            ['source' => 'legacy_property_approval']
        );
        $approval->update(['decision_payload' => ['note' => $data['note'] ?? null, 'by' => user()->id ?? null]]);

        return back()->with('success', __('managedpremises::app.updated'));
    }
}
