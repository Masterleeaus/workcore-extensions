@extends('layouts.app')
@section('content')
<div class="container py-4">
    <div class="alert alert-warning">
        <strong>{{ __('managedpremises::app.deprecated') }}:</strong>
        {{ __('Inspections for this property are now managed by Quality Control.') }}
        <br>
        <a href="{{ route('qc-records.index', ['property_id' => $property->id]) }}" class="btn btn-sm btn-primary mt-2">
            {{ __('Go to Quality Control') }}
        </a>
    </div>
    <p class="text-muted small">
        {{ __('managedpremises::app.legacy_inspection_note') }}
    </p>
</div>
@endsection
