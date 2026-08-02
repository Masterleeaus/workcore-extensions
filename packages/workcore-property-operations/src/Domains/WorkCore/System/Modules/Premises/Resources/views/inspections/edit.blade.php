@extends('layouts.app')
@section('content')
<div class="container py-4">
    <div class="alert alert-warning">
        <strong>{{ __('managedpremises::app.deprecated') }}:</strong>
        {{ __('Editing inspections from Managed Premises is no longer supported.') }}
        <br>
        <a href="{{ route('qc-records.index', ['property_id' => $property->id]) }}" class="btn btn-sm btn-primary mt-2">
            {{ __('Go to Quality Control Inspections') }}
        </a>
    </div>
</div>
@endsection
