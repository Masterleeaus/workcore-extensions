@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Units — {{ $property->name ?? ('Property #' . $property->id) }}</h4>
        <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a>
    </div>

    <div class="card mb-3">
        <div class="card-header">Add unit</div>
        <div class="card-body">
            <x-form method="POST" :action="route('managedpremises.properties.units.store', $property->id)">
                @csrf
                <div class="row">
                    <div class="col-md-3"><x-forms.text fieldId="unit_code" fieldName="unit_code" fieldLabel="Code" /></div>
                    <div class="col-md-5"><x-forms.text fieldId="unit_name" fieldName="unit_name" fieldLabel="Name" fieldRequired="true" /></div>
                    <div class="col-md-2"><x-forms.text fieldId="floor" fieldName="floor" fieldLabel="Floor" /></div>
                    <div class="col-md-2"><x-forms.text fieldId="tower" fieldName="tower" fieldLabel="Tower" /></div>
                </div>
                <div class="row">
                    <div class="col-md-3"><x-forms.text fieldId="type" fieldName="type" fieldLabel="Type" /></div>
                    <div class="col-md-3"><x-forms.number fieldId="area" fieldName="area" fieldLabel="Area" /></div>
                    <div class="col-md-6"><x-forms.text fieldId="address" fieldName="address" fieldLabel="Address" /></div>
                </div>
                <x-forms.button-primary class="mt-2" icon="check">@lang('app.save')</x-forms.button-primary>
            </x-form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Units</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Code</th><th>Name</th><th>Floor</th><th>Tower</th><th>Type</th><th>Area</th><th class="text-right">@lang('app.action')</th></tr></thead>
                    <tbody>
                        @forelse($property->units as $unit)
                            <tr>
                                <td>{{ $unit->unit_code }}</td>
                                <td>{{ $unit->unit_name }}</td>
                                <td>{{ $unit->floor }}</td>
                                <td>{{ $unit->tower }}</td>
                                <td>{{ $unit->type }}</td>
                                <td>{{ $unit->area }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('managedpremises.properties.units.destroy', [$property->id, $unit->id]) }}" onsubmit="return confirm('@lang('app.areYouSure')')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">@lang('app.delete')</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">@lang('app.noRecordFound')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
