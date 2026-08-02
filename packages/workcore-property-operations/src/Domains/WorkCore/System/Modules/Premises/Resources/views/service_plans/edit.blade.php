@extends('layouts.app')
@section('content')
<div class="container">
  <h3>{{ __('managedpremises::app.edit_service_plan') }}</h3>
  @include('managedpremises::service_plans.form', ['action' => route('managedpremises.service-plans.update',[$property,$plan]), 'method' => 'PUT', 'plan' => $plan])
</div>
@endsection
