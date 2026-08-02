<form method="POST" action="{{ $action }}">
  @csrf
  @if(isset($method) && $method !== 'POST') @method($method) @endif
  <div class="card"><div class="card-body">
    <div class="mb-3">
      <label class="form-label">{{ __('managedpremises::app.name') }}</label>
      <input class="form-control" name="name" value="{{ old('name', $plan->name ?? '') }}" required>
    </div>
    <div class="mb-3">
      <label class="form-label">{{ __('managedpremises::app.type') }}</label>
      <input class="form-control" name="service_type" value="{{ old('service_type', $plan->service_type ?? '') }}">
    </div>
    <div class="mb-3">
      <label class="form-label">RRULE</label>
      <input class="form-control" name="rrule" value="{{ old('rrule', $plan->rrule ?? '') }}" placeholder="FREQ=WEEKLY;INTERVAL=2">
      <small class="text-muted">RFC5545 RRULE (optional). Keep simple.</small>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">{{ __('managedpremises::app.starts_on') }}</label>
        <input type="date" class="form-control" name="starts_on" value="{{ old('starts_on', optional($plan->starts_on ?? null)->format('Y-m-d')) }}">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">{{ __('managedpremises::app.ends_on') }}</label>
        <input type="date" class="form-control" name="ends_on" value="{{ old('ends_on', optional($plan->ends_on ?? null)->format('Y-m-d')) }}">
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">{{ __('managedpremises::app.notes') }}</label>
      <textarea class="form-control" name="notes" rows="3">{{ old('notes', $plan->notes ?? '') }}</textarea>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
      <label class="form-check-label">{{ __('managedpremises::app.active') }}</label>
    </div>
    <button class="btn btn-primary">{{ __('managedpremises::app.save') }}</button>
  </div></div>
</form>
