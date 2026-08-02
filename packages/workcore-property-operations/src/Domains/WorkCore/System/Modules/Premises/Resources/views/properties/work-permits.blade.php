@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Premises work permits — {{ $property->name }}</h4>
        <div class="text-muted">Contractor scope, risk controls, approvals, final validation and WorkCore handoff.</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a>
</div>

<div class="alert alert-info">
    A work permit authorises defined work at a premise. WorkCore remains responsible for the job, work order, scheduling, dispatch and completion.
</div>

<div class="card mb-3">
    <div class="card-header">Create work-permit draft</div>
    <div class="card-body">
        <x-form method="POST" :action="route('managedpremises.properties.work-permits.store', $property->id)">
            @csrf
            <div class="row">
                <div class="col-md-3 form-group"><label>Title</label><input class="form-control" name="title" required></div>
                <div class="col-md-3 form-group"><label>Contractor / company</label><input class="form-control" name="contractor_name" required></div>
                <div class="col-md-2 form-group"><label>Permit type</label><select class="form-control" name="permit_type">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseWorkPermit::TYPES as $type)<option value="{{ $type }}">{{ str($type)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><label>Risk</label><select class="form-control" name="risk_level">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseWorkPermit::RISK_LEVELS as $risk)<option value="{{ $risk }}">{{ str($risk)->title() }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><label>Space</label><select class="form-control" name="space_id"><option value="">Premise-wide</option>@foreach($spaces as $space)<option value="{{ $space->id }}">{{ $space->name }}</option>@endforeach</select></div>
            </div>
            <div class="row">
                <div class="col-md-3 form-group"><label>Applicant party</label><select class="form-control" name="applicant_party_role_id"><option value="">External contractor</option>@foreach($partyRoles as $party)<option value="{{ $party->id }}">{{ $party->display_name }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><label>Project manager</label><input class="form-control" name="project_manager_name"></div>
                <div class="col-md-2 form-group"><label>Site coordinator</label><input class="form-control" name="site_coordinator_name"></div>
                <div class="col-md-2 form-group"><label>Phone</label><input class="form-control" name="contact_phone"></div>
                <div class="col-md-1 form-group"><label>Starts</label><input class="form-control" type="datetime-local" name="valid_from" required></div>
                <div class="col-md-1 form-group"><label>Ends</label><input class="form-control" type="datetime-local" name="valid_until" required></div>
                <div class="col-md-1 form-group"><label>Induction</label><div><input type="checkbox" name="induction_required" value="1"> Required</div></div>
            </div>
            <div class="form-group"><label>Work description and scope</label><textarea class="form-control" name="work_description" rows="3" required></textarea></div>
            <div class="form-group"><label>Work categories</label><div class="d-flex flex-wrap">@foreach(['interior','mechanical','electrical','plumbing','structural','fire_systems','security_systems','cleaning','landscaping','other'] as $category)<label class="mr-3"><input type="checkbox" name="work_categories[]" value="{{ $category }}"> {{ str($category)->replace('_',' ')->title() }}</label>@endforeach</div></div>
            <div class="row">
                <div class="col-md-4 form-group"><label>Hazards — one per line</label><textarea class="form-control" name="hazards_text" rows="3"></textarea></div>
                <div class="col-md-4 form-group"><label>Controls — one per line</label><textarea class="form-control" name="controls_text" rows="3"></textarea></div>
                <div class="col-md-2 form-group"><label>Insurance reference</label><input class="form-control" name="insurance_reference"></div>
                <div class="col-md-2 form-group"><label>Licence reference</label><input class="form-control" name="licence_reference"></div>
            </div>
            <button class="btn btn-primary">Create draft permit</button>
        </x-form>
    </div>
</div>

@forelse($permits as $workPermit)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
        <div><strong>{{ $workPermit->permit_number }}</strong> · {{ $workPermit->title }} @if($workPermit->space)· {{ $workPermit->space->name }}@endif</div>
        <span class="badge badge-info">{{ str($workPermit->status)->replace('_',' ')->title() }}</span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-5"><strong>{{ $workPermit->contractor_name }}</strong><br>{{ $workPermit->work_description }}<br><small>{{ $workPermit->valid_from->format('d M Y H:i') }} — {{ $workPermit->valid_until->format('d M Y H:i') }}</small></div>
            <div class="col-md-3"><strong>Risk:</strong> {{ str($workPermit->risk_level)->title() }}<br><strong>Type:</strong> {{ str($workPermit->permit_type)->replace('_',' ')->title() }}<br><small>WorkCore: {{ $workPermit->workcore_reference ?: 'Not linked' }}</small></div>
            <div class="col-md-4">
                @if($workPermit->status === 'draft')
                <x-form method="POST" :action="route('managedpremises.properties.work-permits.submit', [$property->id, $workPermit->id])">@csrf<button class="btn btn-sm btn-primary">Submit</button></x-form>
                @elseif($workPermit->status === 'submitted')
                <x-form method="POST" :action="route('managedpremises.properties.work-permits.approve-manager', [$property->id, $workPermit->id])">@csrf<input class="form-control form-control-sm mb-1" name="remarks" placeholder="Manager remarks"><button class="btn btn-sm btn-success">Manager approve</button></x-form>
                @elseif($workPermit->status === 'manager_approved')
                <x-form method="POST" :action="route('managedpremises.properties.work-permits.approve-site', [$property->id, $workPermit->id])">@csrf<input class="form-control form-control-sm mb-1" name="remarks" placeholder="Premises approval remarks"><button class="btn btn-sm btn-success">Premises approve</button></x-form>
                @elseif($workPermit->status === 'site_approved')
                <x-form method="POST" :action="route('managedpremises.properties.work-permits.validate', [$property->id, $workPermit->id])">@csrf<input class="form-control form-control-sm mb-1" name="remarks" placeholder="Validation remarks"><button class="btn btn-sm btn-success">Final validation</button></x-form>
                @elseif($workPermit->status === 'validated')
                <x-form method="POST" :action="route('managedpremises.properties.work-permits.transition', [$property->id, $workPermit->id])">@csrf<input type="hidden" name="status" value="active">@if($workPermit->induction_required)<input class="form-control form-control-sm mb-1" type="datetime-local" name="induction_completed_at">@endif<button class="btn btn-sm btn-primary">Activate permit</button></x-form>
                @elseif($workPermit->status === 'active')
                <div class="d-inline-block"><x-form method="POST" :action="route('managedpremises.properties.work-permits.transition', [$property->id, $workPermit->id])">@csrf<input type="hidden" name="status" value="suspended"><button class="btn btn-sm btn-outline-warning">Suspend</button></x-form></div>
                <div class="d-inline-block"><x-form method="POST" :action="route('managedpremises.properties.work-permits.transition', [$property->id, $workPermit->id])">@csrf<input type="hidden" name="status" value="completed"><button class="btn btn-sm btn-outline-success">Complete</button></x-form></div>
                @elseif($workPermit->status === 'suspended')
                <x-form method="POST" :action="route('managedpremises.properties.work-permits.transition', [$property->id, $workPermit->id])">@csrf<input type="hidden" name="status" value="active"><button class="btn btn-sm btn-outline-primary">Resume</button></x-form>
                @endif
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-md-6"><strong>Approvals:</strong> @forelse($workPermit->approvals as $approval)<span class="badge badge-light">{{ str($approval->stage)->replace('_',' ')->title() }} · {{ $approval->decision }}</span> @empty None @endforelse</div>
            <div class="col-md-6">
                @unless(\App\Domains\WorkCore\System\Modules\Premises\Domain\Permits\WorkPermitState::isTerminal($workPermit->status))
                <x-form method="POST" :action="route('managedpremises.properties.work-permits.workcore-link', [$property->id, $workPermit->id])">@csrf<div class="d-flex"><input class="form-control form-control-sm mr-1" name="workcore_linked_module" value="work_order" required><input class="form-control form-control-sm mr-1" name="workcore_linked_id" placeholder="Record ID" required><input class="form-control form-control-sm mr-1" name="workcore_reference" placeholder="Display reference"><button class="btn btn-sm btn-outline-primary">Link WorkCore</button></div></x-form>
                @endunless
            </div>
        </div>
    </div>
</div>
@empty
<div class="text-center text-muted py-5">No premises work permits.</div>
@endforelse
@endsection
