@extends('layouts.app')

@section('content')
    <h4 class="mb-3">@lang('app.add') @lang('managedpremises::app.labels.property')</h4>

    @include('managedpremises::properties.ajax.form', ['mode' => 'create'])
@endsection
