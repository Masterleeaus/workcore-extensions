@extends('layouts.app')
@section('content')
<div class="content-wrapper">
    <h4>Vacancy board — {{ $property->name }}</h4>
    <p class="text-muted">Draft listings, request approval, then publish to the public website or an external channel adapter. External credentials are never stored here.</p>
    @include('managedpremises::partials.property-tabs', ['property' => $property])
    <div class="row mt-3">
        <div class="col-lg-5"><x-card><x-slot name="header">Create vacancy draft</x-slot><x-slot name="body">
            <x-form method="POST" :action="route('managedpremises.properties.vacancies.store', $property->id)">@csrf
                <div class="form-group"><label>Space</label><select class="form-control" name="space_id" required>@foreach($spaces as $space)<option value="{{ $space->id }}">{{ $space->name }} — {{ str($space->availability_status)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                <div class="form-group"><label>Title</label><input class="form-control" name="title" required maxlength="191"></div>
                <div class="form-group"><label>Short summary</label><textarea class="form-control" name="summary" rows="2" maxlength="1000"></textarea></div>
                <div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="5" maxlength="10000"></textarea></div>
                <div class="form-group"><label>Public location</label><input class="form-control" name="public_location" placeholder="Suburb or general area"><label class="small mt-2"><input type="checkbox" name="show_exact_address" value="1"> Explicitly show the exact premise address</label></div>
                <div class="row"><div class="col"><label>Available from</label><input class="form-control" type="date" name="available_from"></div><div class="col"><label>Applications close</label><input class="form-control" type="date" name="applications_close_at"></div></div>
                <div class="form-group mt-3"><label>Features</label><textarea class="form-control" name="features" rows="3" placeholder="One per line"></textarea></div>
                <div class="form-group"><label>Eligibility or operational notes</label><textarea class="form-control" name="eligibility_notes" rows="3" placeholder="Public-facing only; do not include sensitive screening data"></textarea></div>
                <div class="form-group"><label>Contact mode</label><select class="form-control" name="contact_mode"><option value="portal">Platform enquiry</option><option value="phone">Phone</option><option value="email">Email</option><option value="external">External listing</option></select></div>
                <button class="btn btn-primary" type="submit">Save draft</button>
            </x-form>
        </x-slot></x-card></div>
        <div class="col-lg-7"><x-card><x-slot name="header">Listings</x-slot><x-slot name="body">
            @forelse($listings as $listing)<div class="border rounded p-3 mb-3"><div class="d-flex justify-content-between"><div><strong>{{ $listing->title }}</strong><div class="small text-muted">{{ $listing->space?->name }} · {{ str($listing->status)->replace('_',' ')->title() }}</div></div><span class="badge badge-secondary">{{ $listing->public_slug }}</span></div><p class="mt-2">{{ $listing->summary }}</p>
                <div class="d-flex flex-wrap">
                    @if($listing->status === 'draft')<x-form method="POST" :action="route('managedpremises.properties.vacancies.transition', [$property->id,$listing->id])" class="mr-2 mb-2">@csrf<input type="hidden" name="status" value="pending_approval"><button class="btn btn-sm btn-primary" type="submit">Request approval</button></x-form>@endif
                    @if($listing->status === 'pending_approval')<x-form method="POST" :action="route('managedpremises.properties.vacancies.approve', [$property->id,$listing->id])" class="mr-2 mb-2">@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-sm btn-success" type="submit">Approve</button></x-form><x-form method="POST" :action="route('managedpremises.properties.vacancies.approve', [$property->id,$listing->id])" class="mr-2 mb-2">@csrf<input type="hidden" name="decision" value="rejected"><input type="hidden" name="rejection_reason" value="Rejected from vacancy board"><button class="btn btn-sm btn-outline-danger" type="submit">Reject</button></x-form>@endif
                    @if(in_array($listing->status,['approved','published','paused'],true))<x-form method="POST" :action="route('managedpremises.properties.vacancies.publish', [$property->id,$listing->id])" class="mr-2 mb-2">@csrf<select name="channel" class="form-control form-control-sm d-inline-block" style="width:auto">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseVacancyListing::CHANNELS as $channel)<option value="{{ $channel }}">{{ str($channel)->replace('_',' ')->title() }}</option>@endforeach</select><button class="btn btn-sm btn-outline-primary" type="submit">Publish/request</button></x-form>@endif
                    @if(!in_array($listing->status,['closed','rejected'],true))<x-form method="POST" :action="route('managedpremises.properties.vacancies.transition', [$property->id,$listing->id])" class="mb-2">@csrf<input type="hidden" name="status" value="closed"><button class="btn btn-sm btn-outline-secondary" type="submit">Close</button></x-form>@endif
                </div>
                @if($listing->publications->isNotEmpty())<div class="small text-muted">Publications: @foreach($listing->publications as $publication)<span class="pill">{{ $publication->channel }}: {{ $publication->status }}</span>@endforeach</div>@endif
            </div>@empty<div class="text-muted">No vacancy listings.</div>@endforelse
        </x-slot></x-card></div>
    </div>
</div>
@endsection
