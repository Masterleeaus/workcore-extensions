@extends('layouts.app')

@section('content')
<div class="content-wrapper">
  <div class="d-flex justify-content-between mb-3">
    <h4>@lang('managedpremises::app.hazards')</h4>
    <a href="{{ route('managedpremises.properties.index') }}" class="btn btn-secondary btn-sm">@lang('managedpremises::app.sites')</a>
  </div>

  <x-card>
    <x-slot name="header">
      <div class="d-flex justify-content-between align-items-center">
        <div>@lang('managedpremises::app.hazards')</div>
        <form method="GET" action="{{ route('managedpremises.hazards.index') }}" class="d-flex" style="gap:8px;">
          <select name="property_id" class="form-control form-control-sm" onchange="this.form.submit()">
            <option value="0">All Sites</option>
            @foreach($properties as $p)
              <option value="{{ $p->id }}" @selected($propertyId === (int)$p->id)>{{ $p->name }}</option>
            @endforeach
          </select>
        </form>
      </div>
    </x-slot>

    <x-slot name="body">
      <form id="pmHazardForm" method="POST" action="{{ $storeUrl }}">
        @csrf
        <div class="row">
          <div class="col-md-4">
            <label class="f-12 mb-1">Site</label>
            <select name="property_id" class="form-control" required>
              <option value="" disabled @selected($propertyId===0)>Select a site…</option>
              @foreach($properties as $p)
                <option value="{{ $p->id }}" @selected($propertyId === (int)$p->id)>{{ $p->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4"><x-forms.text fieldName="hazard" fieldId="hazard" fieldLabel="Hazard" /></div>
          <div class="col-md-4"><x-forms.select fieldName="risk_level" fieldId="risk_level" fieldLabel="Risk">
              <option value="low">Low</option>
              <option value="medium" selected>Medium</option>
              <option value="high">High</option>
            </x-forms.select>
          </div>
          <div class="col-md-12 mt-2"><x-forms.textarea fieldName="controls" fieldId="controls" fieldLabel="Controls / Instructions" /></div>
        </div>
        <x-forms.button-primary class="mt-3" id="saveHazard">@lang('app.save')</x-forms.button-primary>
      </form>

      <hr>

      <div class="table-responsive">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Site</th>
              <th>Hazard</th>
              <th>Risk</th>
              <th>Controls</th>
              <th class="text-right">@lang('app.action')</th>
            </tr>
          </thead>
          <tbody>
            @forelse($hazards as $h)
              <tr>
                <td>{{ $h->property?->name ?? '-' }}</td>
                <td>{{ $h->hazard }}</td>
                <td>{{ $h->risk_level }}</td>
                <td>{{ $h->controls }}</td>
                <td class="text-right">
                  <a href="{{ route('managedpremises.properties.hazards.index', $h->property_id) }}" class="btn btn-light btn-sm">Open</a>
                  <x-forms.button-secondary data-url="{{ route('managedpremises.properties.hazards.destroy', [$h->property_id, $h->id]) }}" class="delete-row btn-sm" icon="trash">@lang('app.delete')</x-forms.button-secondary>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted">@lang('managedpremises::app.no_records')</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </x-slot>
  </x-card>
</div>
@endsection

@push('scripts')
<script>
$(document).on('click','#saveHazard',function(e){
  e.preventDefault();
  $.easyAjax({
    url: $('#pmHazardForm').attr('action'),
    container: '#pmHazardForm',
    type: "POST",
    data: $('#pmHazardForm').serialize(),
  });
});
</script>
@endpush
