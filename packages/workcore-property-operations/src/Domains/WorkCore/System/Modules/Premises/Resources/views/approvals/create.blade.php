@extends('layouts.app')
@section('content')
<div class="container">
  <h3>{{ __('managedpremises::app.request_approval') }}</h3>
  <form method="POST" action="{{ route('managedpremises.approvals.store', $property) }}">
    @csrf
    <div class="card"><div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('managedpremises::app.subject') }}</label>
        <input class="form-control" name="subject" required>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('managedpremises::app.requested_to') }}</label>
        <input class="form-control" name="requested_to" placeholder="User ID (optional)">
      </div>
      <button class="btn btn-primary">{{ __('managedpremises::app.save') }}</button>
    </div></div>
  </form>
</div>
@endsection
