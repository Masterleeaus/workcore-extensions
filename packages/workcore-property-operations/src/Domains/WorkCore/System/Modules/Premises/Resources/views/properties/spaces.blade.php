@extends('layouts.app')

@section('content')
@php($premisesProfile = app(\App\Domains\WorkCore\System\Modules\Premises\Services\PremisesProfileRegistry::class)->forProperty($property))
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Spaces — {{ $property->name ?? ('Premise #' . $property->id) }}</h4>
        <div class="text-muted">Buildings, floors, rooms, suites, sheds, lockers, bays and shared areas.</div>
    </div>
    <div>@if($premisesProfile['features']['occupancy'] ?? false)<a class="btn btn-outline-primary" href="{{ route('managedpremises.properties.occupancies.index', $property->id) }}">Occupancy board</a>@endif <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a></div>
</div>

<div class="card mb-3">
    <div class="card-header">Add space</div>
    <div class="card-body">
        <x-form method="POST" :action="route('managedpremises.properties.spaces.store', $property->id)">
            @csrf
            <div class="row">
                <div class="col-md-3"><x-forms.select fieldId="parent_space_id" fieldName="parent_space_id" fieldLabel="Parent space"><option value="">Top level</option>@foreach($spaces as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->name }}</option>@endforeach</x-forms.select></div>
                <div class="col-md-2"><x-forms.text fieldId="space_type" fieldName="space_type" fieldLabel="Type" fieldValue="space" fieldRequired="true" /></div>
                <div class="col-md-2"><x-forms.text fieldId="code" fieldName="code" fieldLabel="Code" /></div>
                <div class="col-md-5"><x-forms.text fieldId="name" fieldName="name" fieldLabel="Name" fieldRequired="true" /></div>
            </div>
            <div class="row">
                <div class="col-md-2"><x-forms.text fieldId="floor" fieldName="floor" fieldLabel="Floor" /></div>
                <div class="col-md-2"><x-forms.text fieldId="zone" fieldName="zone" fieldLabel="Zone / row" /></div>
                <div class="col-md-2"><x-forms.number fieldId="capacity" fieldName="capacity" fieldLabel="Capacity" /></div>
                <div class="col-md-2"><x-forms.number fieldId="area" fieldName="area" fieldLabel="Area" /></div>
                <div class="col-md-4"><x-forms.select fieldId="availability_status" fieldName="availability_status" fieldLabel="Availability">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseSpace::AVAILABILITY_STATUSES as $status)<option value="{{ $status }}">{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</x-forms.select></div>
            </div>
            <div class="row mt-2">
                @foreach(['is_occupiable' => 'Occupiable', 'is_bookable' => 'Bookable', 'is_billable' => 'Billable', 'is_shared' => 'Shared'] as $field => $label)
                    <div class="col-md-2"><label><input type="checkbox" name="{{ $field }}" value="1"> {{ $label }}</label></div>
                @endforeach
            </div>
            <x-forms.button-primary class="mt-2" icon="check">@lang('app.save')</x-forms.button-primary>
        </x-form>
    </div>
</div>

<div class="card"><div class="card-header">Space hierarchy</div><div class="card-body table-responsive">
<table class="table table-hover"><thead><tr><th>Parent</th><th>Code</th><th>Name</th><th>Type</th><th>Availability</th><th>Capacity used</th><th>Legacy source</th><th></th></tr></thead><tbody>
@forelse($spaces as $space)
<tr><td>{{ optional($space->parent)->name ?: '—' }}</td><td>{{ $space->code }}</td><td>{{ $space->name }}</td><td>{{ $space->space_type }}</td><td>{{ str($space->availability_status)->replace('_',' ')->title() }}</td><td>{{ $space->occupiedCapacity() }} / {{ $space->capacity ?? '∞' }}</td><td>{{ $space->legacy_source_type ?: 'Native' }}</td><td class="text-right">@if(!$space->legacy_source_type && !$space->occupancies_count)<form method="POST" action="{{ route('managedpremises.properties.spaces.destroy', [$property->id, $space->id]) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">@lang('app.delete')</button></form>@endif</td></tr>
@empty<tr><td colspan="8" class="text-center text-muted py-4">@lang('app.noRecordFound')</td></tr>@endforelse
</tbody></table></div></div>
@endsection
