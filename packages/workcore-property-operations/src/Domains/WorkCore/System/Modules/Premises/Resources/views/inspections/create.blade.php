@extends('layouts.app')
@section('content')
<div class="container py-4">
    <div class="alert alert-warning">
        <strong>{{ __('managedpremises::app.deprecated') }}:</strong>
        {{ __('Creating inspections from Managed Premises is no longer supported.') }}
        <br>
        <a href="{{ route('qc-records.create', ['property_id' => $property->id]) }}" class="btn btn-sm btn-primary mt-2">
            {{ __('Create QC Inspection in Quality Control') }}
        </a>
    </div>
</div>
@endsection
