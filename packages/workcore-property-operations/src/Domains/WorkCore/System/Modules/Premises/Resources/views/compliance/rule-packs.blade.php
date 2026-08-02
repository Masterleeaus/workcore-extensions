@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-0">Compliance rule packs</h4><div class="text-muted">Versioned requirement templates with source and review provenance.</div></div>
    <a class="btn btn-outline-secondary" href="{{ route('managedpremises.dashboard') }}">Back to premises</a>
</div>
<div class="alert alert-warning"><strong>Important:</strong> {{ $disclaimer }}</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-header">Install bundled starter pack</div><div class="card-body">
            @forelse($bundled as $key => $definition)
                <div class="border rounded p-2 mb-2"><strong>{{ $definition['name'] }}</strong><p class="small mb-2">{{ $definition['description'] }}</p><x-form method="POST" :action="route('managedpremises.compliance.rule-packs.install')">@csrf<input type="hidden" name="pack_key" value="{{ $key }}"><button class="btn btn-sm btn-outline-primary">Install unverified template</button></x-form></div>
            @empty<p class="text-muted">No bundled packs.</p>@endforelse
        </div></div>

        <div class="card"><div class="card-header">Create custom rule pack</div><div class="card-body">
            <x-form method="POST" :action="route('managedpremises.compliance.rule-packs.store')">@csrf
                <div class="form-group"><label>Name</label><input class="form-control" name="name" required></div>
                <div class="row"><div class="col-md-6 form-group"><label>Key</label><input class="form-control" name="key"></div><div class="col-md-6 form-group"><label>Version</label><input class="form-control" name="version" value="1.0.0" required></div></div>
                <div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                <div class="form-group"><label>Profile key</label><input class="form-control" name="profile_key" placeholder="rooming_house"></div>
                <div class="row"><div class="col-md-4 form-group"><label>Country</label><input class="form-control" name="jurisdiction_country"></div><div class="col-md-4 form-group"><label>Region</label><input class="form-control" name="jurisdiction_region"></div><div class="col-md-4 form-group"><label>Locality</label><input class="form-control" name="jurisdiction_locality"></div></div>
                <div class="form-group"><label>Source title</label><input class="form-control" name="source_title"></div>
                <div class="form-group"><label>Source URL</label><input class="form-control" type="url" name="source_url"></div>
                <div class="form-group"><label>Requirements JSON</label><textarea class="form-control" name="requirements_json" rows="10" required>[{"key":"example_review","requirement_type":"custom","title":"Example review","recurrence_type":"annual","annual_month":7,"annual_day":1,"initial_due_offset_days":30,"severity":"medium","evidence_required":true}]</textarea></div>
                <button class="btn btn-primary">Create draft pack</button>
            </x-form>
        </div></div>
    </div>

    <div class="col-lg-8">
        @forelse($packs as $pack)
            <div class="card mb-3"><div class="card-header d-flex justify-content-between"><div><strong>{{ $pack->name }}</strong><br><small>{{ $pack->key }} · v{{ $pack->version }} · {{ $pack->profile_key ?: 'All profiles' }}</small></div><div><span class="badge badge-{{ $pack->verification_status === 'verified' ? 'success' : ($pack->verification_status === 'rejected' ? 'danger' : 'warning') }}">{{ str($pack->verification_status)->headline() }}</span> <span class="badge badge-secondary">{{ ucfirst($pack->status) }}</span></div></div><div class="card-body">
                <p>{{ $pack->description }}</p>
                <div class="row small mb-3"><div class="col-md-6"><strong>Jurisdiction:</strong> {{ trim(($pack->jurisdiction_country ?: 'Generic').' '.$pack->jurisdiction_region.' '.$pack->jurisdiction_locality) }}<br><strong>Requirements:</strong> {{ count($pack->template_requirements ?? []) }}</div><div class="col-md-6"><strong>Source:</strong> {{ $pack->source_title ?: 'Not recorded' }}@if($pack->source_url)<br><a href="{{ $pack->source_url }}" target="_blank" rel="noopener">Open source</a>@endif<br><strong>Local verification required:</strong> {{ $pack->requires_local_verification ? 'Yes' : 'No' }}</div></div>
                @if($pack->status !== 'retired')
                <x-form method="POST" :action="route('managedpremises.compliance.rule-packs.review', $pack->id)">@csrf
                    <div class="row"><div class="col-md-3 form-group"><label>Review result</label><select class="form-control" name="verification_status"><option value="reviewed">Reviewed</option><option value="verified">Verified</option><option value="rejected">Rejected</option></select></div><div class="col-md-4 form-group"><label>Source title</label><input class="form-control" name="source_title" value="{{ $pack->source_title }}"></div><div class="col-md-5 form-group"><label>Source URL</label><input class="form-control" type="url" name="source_url" value="{{ $pack->source_url }}"></div></div>
                    <div class="form-group"><label>Review notes</label><input class="form-control" name="review_notes"></div>
                    <label><input type="checkbox" name="confirmed_by_reviewer" value="1"> I confirm applicability, currency, source, and interpretation have been reviewed.</label><br>
                    <button class="btn btn-sm btn-outline-success mt-2">Save review</button>
                </x-form>
                <x-form method="POST" :action="route('managedpremises.compliance.rule-packs.retire', $pack->id)" class="mt-2">@csrf<button class="btn btn-sm btn-outline-danger">Retire pack</button></x-form>
                @endif
            </div></div>
        @empty<div class="card"><div class="card-body text-center text-muted">No rule packs installed.</div></div>@endforelse
    </div>
</div>
@endsection
