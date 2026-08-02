@extends('layouts.app')
@section('content')
<div class="container">
  <h3>{{ __('managedpremises::app.add_document') }}</h3>
  <form method="POST" action="{{ route('managedpremises.documents.store', $property) }}" enctype="multipart/form-data">
    @csrf
    <div class="card"><div class="card-body">
      <div class="mb-3">
        <label class="form-label">{{ __('managedpremises::app.name') }}</label>
        <input class="form-control" name="name" required>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('managedpremises::app.type') }}</label>
        <input class="form-control" name="doc_type">
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('managedpremises::app.file') }}</label>
        <input type="file" class="form-control" name="file" required>
      </div>
      <div class="mb-3">
        <label class="form-label">{{ __('managedpremises::app.notes') }}</label>
        <textarea class="form-control" name="notes" rows="3"></textarea>
      </div>
      <button class="btn btn-primary">{{ __('managedpremises::app.save') }}</button>
    </div></div>
  </form>
</div>
@endsection
