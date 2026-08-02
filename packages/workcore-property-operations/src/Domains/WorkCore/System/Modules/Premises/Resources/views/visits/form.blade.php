<form method="POST" action="{{ $action }}">
  @csrf
  @if(isset($method) && $method !== 'POST') @method($method) @endif
  <div class="card"><div class="card-body">
    <div class="mb-3">
      <label class="form-label">{{ __('managedpremises::app.type') }}</label>
      <input class="form-control" name="visit_type" value="{{ old('visit_type', $visit->visit_type ?? '') }}">
    </div>
    <div class="mb-3">
      <label class="form-label">{{ __('managedpremises::app.scheduled_for') }}</label>
      <input class="form-control" name="scheduled_for" value="{{ old('scheduled_for', $visit->scheduled_for ?? '') }}" placeholder="YYYY-MM-DD HH:MM">
    </div>
    <div class="mb-3">
      <label class="form-label">{{ __('managedpremises::app.status') }}</label>
      <input class="form-control" name="status" value="{{ old('status', $visit->status ?? 'scheduled') }}">
    </div>
    <div class="mb-3">
      <label class="form-label">{{ __('managedpremises::app.notes') }}</label>
      <textarea class="form-control" name="notes" rows="3">{{ old('notes', $visit->notes ?? '') }}</textarea>
    </div>
    <button class="btn btn-primary">{{ __('managedpremises::app.save') }}</button>
  </div></div>
</form>
