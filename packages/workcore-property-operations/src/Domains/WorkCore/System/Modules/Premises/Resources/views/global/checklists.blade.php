@extends('layouts.app')

@section('content')
<div class="content-wrapper">
  <div class="d-flex justify-content-between mb-3">
    <h4>@lang('managedpremises::app.checklists')</h4>
    <a href="{{ route('managedpremises.properties.index') }}" class="btn btn-secondary btn-sm">@lang('managedpremises::app.sites')</a>
  </div>

  <x-card>
    <x-slot name="header">
      <div class="d-flex justify-content-between align-items-center">
        <div>@lang('managedpremises::app.checklists')</div>
        <form method="GET" action="{{ route('managedpremises.checklists.index') }}" class="d-flex" style="gap:8px;">
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
      <form id="pmChecklistForm" method="POST" action="{{ $storeUrl }}">
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
          <div class="col-md-4">
            <x-forms.select fieldName="type" fieldId="type" fieldLabel="Type">
              <option value="cleaning" selected>Cleaning</option>
              <option value="inspection">Inspection</option>
              <option value="handover">Handover</option>
            </x-forms.select>
          </div>
          <div class="col-md-4"><x-forms.text fieldName="title" fieldId="title" fieldLabel="Title" /></div>
          <div class="col-md-12 mt-2">
            <label class="f-12 mb-1">Items (one per line)</label>
            <textarea name="items" id="items" class="form-control" rows="4" placeholder="Kitchen benches\nBathrooms\nFloors\nBins"></textarea>
          </div>
        </div>
        <x-forms.button-primary class="mt-3" id="saveChecklist">@lang('app.save')</x-forms.button-primary>
      </form>

      <hr>

      <div class="table-responsive">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Site</th>
              <th>Type</th>
              <th>Title</th>
              <th class="text-right">@lang('app.action')</th>
            </tr>
          </thead>
          <tbody>
            @forelse($checklists as $c)
              <tr>
                <td>{{ $c->property?->name ?? '-' }}</td>
                <td>{{ $c->type }}</td>
                <td>{{ $c->title }}</td>
                <td class="text-right">
                  <a href="{{ route('managedpremises.properties.checklists.index', $c->property_id) }}" class="btn btn-light btn-sm">Open</a>
                  <x-forms.button-secondary data-url="{{ route('managedpremises.properties.checklists.destroy', [$c->property_id, $c->id]) }}" class="delete-row btn-sm" icon="trash">@lang('app.delete')</x-forms.button-secondary>
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center text-muted">@lang('managedpremises::app.no_records')</td></tr>
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
$(document).on('click','#saveChecklist',function(e){
  e.preventDefault();
  $.easyAjax({
    url: $('#pmChecklistForm').attr('action'),
    container: '#pmChecklistForm',
    type: "POST",
    data: $('#pmChecklistForm').serialize(),
  });
});
</script>
@endpush
