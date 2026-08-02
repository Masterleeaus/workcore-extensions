@extends('layouts.app')

@section('content')
<div class="content-wrapper">
  <div class="d-flex justify-content-between mb-3">
    <h4>@lang('managedpremises::app.keys_access')</h4>
    <a href="{{ route('managedpremises.properties.index') }}" class="btn btn-secondary btn-sm">@lang('managedpremises::app.sites')</a>
  </div>

  <x-card>
    <x-slot name="header">
      <div class="d-flex justify-content-between align-items-center">
        <div>@lang('managedpremises::app.keys_register')</div>
        <form method="GET" action="{{ route('managedpremises.keys.index') }}" class="d-flex" style="gap:8px;">
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
      <form id="pmKeyForm" method="POST" action="{{ $storeUrl }}">
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
          <div class="col-md-2">
            <x-forms.select fieldName="type" fieldId="type" fieldLabel="Type">
              <option value="key">@lang('managedpremises::app.key')</option>
              <option value="lockbox">@lang('managedpremises::app.lockbox')</option>
              <option value="code">@lang('managedpremises::app.access_code')</option>
            </x-forms.select>
          </div>
          <div class="col-md-3">
            <x-forms.text fieldName="location" fieldId="location" fieldLabel="@lang('managedpremises::app.location')" />
          </div>
          <div class="col-md-3">
            <label>@lang('managedpremises::app.code')</label><input class="form-control" type="password" name="code" id="code" autocomplete="new-password">
          </div>
          <div class="col-md-12 mt-2">
            <x-forms.textarea fieldName="notes" fieldId="notes" fieldLabel="@lang('managedpremises::app.notes')" />
          </div>
        </div>
        <x-forms.button-primary class="mt-3" id="saveKey">@lang('app.save')</x-forms.button-primary>
      </form>

      <hr>

      <div class="table-responsive">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Site</th>
              <th>@lang('managedpremises::app.type')</th>
              <th>@lang('managedpremises::app.location')</th>
              <th>@lang('managedpremises::app.code')</th>
              <th>@lang('managedpremises::app.notes')</th>
              <th class="text-right">@lang('app.action')</th>
            </tr>
          </thead>
          <tbody>
            @forelse($keys as $k)
              <tr>
                <td>{{ $k->property?->name ?? '-' }}</td>
                <td>{{ $k->type }}</td>
                <td>{{ $k->location }}</td>
                <td>{{ $k->maskedCode() ?: '—' }}</td>
                <td>{{ $k->notes }}</td>
                <td class="text-right">
                  <a href="{{ route('managedpremises.properties.keys.index', $k->property_id) }}" class="btn btn-light btn-sm">Open</a>
                  <x-forms.button-secondary data-url="{{ route('managedpremises.properties.keys.destroy', [$k->property_id, $k->id]) }}" class="delete-row btn-sm" icon="trash">@lang('app.delete')</x-forms.button-secondary>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted">@lang('managedpremises::app.no_records')</td></tr>
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
$(document).on('click','#saveKey',function(e){
  e.preventDefault();
  $.easyAjax({
    url: $('#pmKeyForm').attr('action'),
    container: '#pmKeyForm',
    type: "POST",
    data: $('#pmKeyForm').serialize(),
  });
});
</script>
@endpush
