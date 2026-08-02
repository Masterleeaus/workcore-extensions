@extends('layouts.app')

@section('content')
    <h4 class="mb-3">@lang('app.edit') @lang('managedpremises::app.labels.property')</h4>

    @include('managedpremises::properties.ajax.form', ['mode' => 'edit'])
@endsection
