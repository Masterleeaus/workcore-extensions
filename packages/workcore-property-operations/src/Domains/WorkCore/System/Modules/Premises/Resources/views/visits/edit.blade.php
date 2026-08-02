@extends('layouts.app')
@section('content')
<div class="container">
  <h3>{{ __('managedpremises::app.edit_visit') }}</h3>
  @include('managedpremises::visits.form', ['action' => route('managedpremises.visits.update',[$property,$visit]), 'method' => 'PUT', 'visit' => $visit])
</div>
@endsection
