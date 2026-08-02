@extends('layouts.app')
@section('content')
<div class="container">
  <h3>{{ __('managedpremises::app.add_visit') }}</h3>
  @include('managedpremises::visits.form', ['action' => route('managedpremises.visits.store',$property), 'method' => 'POST'])
</div>
@endsection
