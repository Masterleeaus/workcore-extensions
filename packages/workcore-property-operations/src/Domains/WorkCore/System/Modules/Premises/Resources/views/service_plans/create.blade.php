@extends('layouts.app')
@section('content')
<div class="container">
  <h3>{{ __('managedpremises::app.add_service_plan') }}</h3>
  @include('managedpremises::service_plans.form', ['action' => route('managedpremises.service-plans.store',$property), 'method' => 'POST'])
</div>
@endsection
