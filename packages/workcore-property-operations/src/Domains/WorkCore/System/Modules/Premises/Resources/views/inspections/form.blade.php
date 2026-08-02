<form method="POST" action="{{ $action }}">
  @csrf
  @if(isset($method) && $method !== 'POST') @method($method) @endif
  <div class="card"><div class="card-body">
    <div class="mb-3">
      <label class="form-label">{{ __('managedpremises::app.type') }}</label>
      <input class="form-control" name="inspection_type" value="{{ old('inspection_type', $inspection->inspection_type ?? '') }}">
    </div>
    <div class="mb-3">
      <label class="form-label">{{ __('managedpremises::app.scheduled_for') }}</label>
      <input class="form-control" name="scheduled_for" value="{{ old('scheduled_for', $inspection->scheduled_for ?? '') }}" placeholder="YYYY-MM-DD HH:MM">
    </div>
    <div class="mb-3">
      <label class="form-label">{{ __('managedpremises::app.status') }}</label>
      <input class="form-control" name="status" value="{{ old('status', $inspection->status ?? 'scheduled') }}">
    </div>
    <div class="mb-3">
      <label class="form-label">{{ __('managedpremises::app.notes') }}</label>
      <textarea class="form-control" name="findings" rows="3" placeholder="JSON or short text">{{ old('findings', is_array($inspection->findings ?? null) ? json_encode($inspection->findings) : '') }}</textarea>
    </div>
    <button class="btn btn-primary">{{ __('managedpremises::app.save') }}</button>
  </div></div>
</form>
