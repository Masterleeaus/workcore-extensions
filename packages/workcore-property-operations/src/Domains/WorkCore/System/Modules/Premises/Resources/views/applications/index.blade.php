@extends('layouts.app')
@section('content')
<div class="content-wrapper">
    <h4>Enquiries and applications — {{ $property->name }}</h4>
    <p class="text-muted">Applications remain separate from occupancy. Only an accepted application can be explicitly converted by an authorised operator.</p>
    @include('managedpremises::partials.property-tabs', ['property' => $property])
    <div class="row mt-3">
        <div class="col-lg-4"><x-card><x-slot name="header">Enquiries</x-slot><x-slot name="body">
            @forelse($enquiries as $enquiry)<div class="border rounded p-2 mb-2"><strong>{{ $enquiry->display_name }}</strong><div class="small text-muted">{{ $enquiry->listing?->title }} · {{ str($enquiry->status)->replace('_',' ')->title() }}</div><div>{{ $enquiry->message }}</div>@if(!in_array($enquiry->status,['disqualified','converted','closed'],true))<x-form method="POST" :action="route('managedpremises.properties.enquiries.transition',[$property->id,$enquiry->id])" class="mt-2">@csrf<select class="form-control form-control-sm" name="status"><option value="contacted">Contacted</option><option value="qualified">Qualified</option><option value="disqualified">Disqualified</option><option value="closed">Closed</option></select><button class="btn btn-sm btn-outline-primary mt-1">Update enquiry</button></x-form>@endif</div>@empty<div class="text-muted">No enquiries.</div>@endforelse
        </x-slot></x-card></div>
        <div class="col-lg-8"><x-card><x-slot name="header">Applicant pipeline</x-slot><x-slot name="body">
            @forelse($applications as $application)<div class="border rounded p-3 mb-3"><div class="d-flex justify-content-between"><div><strong>{{ $application->applicant_name }}</strong><div class="small text-muted">{{ $application->application_number }} · {{ $application->listing?->title }} · {{ $application->space?->name }}</div></div><span class="badge badge-secondary">{{ str($application->status)->replace('_',' ')->title() }}</span></div>
                @if(!in_array($application->status,['rejected','withdrawn','converted','cancelled'],true))<x-form method="POST" :action="route('managedpremises.properties.applications.transition', [$property->id,$application->id])" class="mt-2">@csrf<div class="form-row"><div class="col"><select class="form-control form-control-sm" name="status">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseApplication::STATUSES as $status)<option value="{{ $status }}">{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></div><div class="col"><input class="form-control form-control-sm" name="summary" placeholder="Applicant-visible summary"></div><div class="col-auto"><button class="btn btn-sm btn-outline-primary">Move stage</button></div></div><textarea class="form-control form-control-sm mt-2" name="screening_notes" placeholder="Private operator assessment" rows="2"></textarea></x-form>@endif
                @if($application->status === 'accepted')<x-form method="POST" :action="route('managedpremises.properties.applications.convert', [$property->id,$application->id])" class="mt-2">@csrf<div class="form-row"><div class="col"><select class="form-control form-control-sm" name="occupancy_status"><option value="reserved">Reserved</option><option value="pending">Pending</option><option value="active">Active</option></select></div><div class="col"><input class="form-control form-control-sm" type="date" name="start_date"></div><div class="col-auto"><button class="btn btn-sm btn-success">Create party and occupancy</button></div></div></x-form>@endif
                @if($application->convertedOccupancy)<div class="small text-success mt-2">Converted to occupancy #{{ $application->convertedOccupancy->id }}</div>@endif
            </div>@empty<div class="text-muted">No applications.</div>@endforelse
        </x-slot></x-card></div>
    </div>
</div>
@endsection
