@extends('layouts.app')

@section('content')
<div class="content-wrapper">
  <div class="d-flex justify-content-between mb-3">
    <h4>@lang('managedpremises::app.rooms')</h4>
    <a href="{{ route('managedpremises.properties.index') }}" class="btn btn-secondary btn-sm">@lang('managedpremises::app.sites')</a>
  </div>

  <x-card>
    <x-slot name="header">
      <div class="d-flex justify-content-between align-items-center">
        <div>@lang('managedpremises::app.rooms')</div>
        <form method="GET" action="{{ route('managedpremises.rooms.index') }}" class="d-flex" style="gap:8px;">
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
      <form id="pmForm" method="POST" action="{{ $storeUrl }}">
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
          <div class="col-md-4"><x-forms.text fieldName="name" fieldId="name" fieldLabel="@lang('managedpremises::app.room_name')" /></div>
          <div class="col-md-4"><x-forms.text fieldName="type" fieldId="type" fieldLabel="@lang('managedpremises::app.room_type')" /></div>
          <div class="col-md-12 mt-2"><x-forms.textarea fieldName="notes" fieldId="notes" fieldLabel="@lang('managedpremises::app.notes')" /></div>
        </div>
        <x-forms.button-primary class="mt-3" id="saveBtn">@lang('app.save')</x-forms.button-primary>
      </form>

      <hr>

      <div class="table-responsive">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Site</th>
              <th>@lang('managedpremises::app.room_name')</th>
              <th>@lang('managedpremises::app.room_type')</th>
              <th>@lang('managedpremises::app.notes')</th>
              <th class="text-right">@lang('app.action')</th>
            </tr>
          </thead>
          <tbody>
            @forelse($rooms as $r)
              <tr>
                <td>{{ $r->property?->name ?? '-' }}</td>
                <td>{{ $r->name }}</td>
                <td>{{ $r->type }}</td>
                <td>{{ $r->notes }}</td>
                <td class="text-right">
                  <a href="{{ route('managedpremises.properties.rooms.index', $r->property_id) }}" class="btn btn-light btn-sm">Open</a>
                  <x-forms.button-secondary data-url="{{ route('managedpremises.properties.rooms.destroy', [$r->property_id, $r->id]) }}" class="delete-row btn-sm" icon="trash">@lang('app.delete')</x-forms.button-secondary>
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
$(document).on('click','#saveBtn',function(e){
  e.preventDefault();
  $.easyAjax({
    url: $('#pmForm').attr('action'),
    container: '#pmForm',
    type: "POST",
    data: $('#pmForm').serialize(),
  });
});
</script>
@endpush
