@extends('layouts.app')

@section('content')
<div class="content-wrapper">
  <div class="d-flex justify-content-between mb-3">
    <h4>Photos</h4>
    <a href="{{ route('managedpremises.properties.index') }}" class="btn btn-secondary btn-sm">Premises</a>
  </div>

  <x-card>
    <x-slot name="header">
      <div class="d-flex justify-content-between align-items-center">
        <div>Photos</div>
        <form method="GET" action="{{ route('managedpremises.photos.index') }}" class="d-flex" style="gap:8px;">
          <select name="property_id" class="form-control form-control-sm" onchange="this.form.submit()">
            <option value="0">All Premises</option>
            @foreach($properties as $p)
              <option value="{{ $p->id }}" @selected($propertyId === (int)$p->id)>{{ $p->name }}</option>
            @endforeach
          </select>
        </form>
      </div>
    </x-slot>

    <x-slot name="body">
      <form id="pmPhotoForm" method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data">
        @csrf
        <div class="row">
          <div class="col-md-4">
            <label class="f-12 mb-1">Premise</label>
            <select name="property_id" class="form-control" required>
              <option value="" disabled @selected($propertyId===0)>Select a premise…</option>
              @foreach($properties as $p)
                <option value="{{ $p->id }}" @selected($propertyId === (int)$p->id)>{{ $p->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4">
            <label class="f-12 mb-1">Job (optional)</label>
            <input type="number" name="property_job_id" class="form-control" placeholder="Job ID (optional)">
            <small class="text-muted">Optional link to a premise job.</small>
          </div>
          <div class="col-md-4">
            <label class="f-12 mb-1">Photo</label>
            <input type="file" name="photo" class="form-control" accept="image/*" required>
          </div>
          <div class="col-md-12 mt-2">
            <label class="f-12 mb-1">Caption (optional)</label>
            <input type="text" name="caption" class="form-control" maxlength="190" placeholder="e.g. Before — kitchen floor">
          </div>
        </div>
        <x-forms.button-primary class="mt-3" id="uploadPhotoBtn">Upload</x-forms.button-primary>
      </form>

      <hr>

      <div class="table-responsive">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Premise</th>
              <th>File</th>
              <th>Caption</th>
              <th>Job</th>
              <th class="text-right">@lang('app.action')</th>
            </tr>
          </thead>
          <tbody>
            @forelse($photos as $ph)
              <tr>
                <td>{{ $ph->property?->name ?? '-' }}</td>
                <td>{{ $ph->path }}</td>
                <td>{{ $ph->caption }}</td>
                <td>{{ $ph->property_job_id ?? '-' }}</td>
                <td class="text-right">
                  <a href="{{ route('managedpremises.properties.photos.index', $ph->property_id) }}" class="btn btn-light btn-sm">Open</a>
                  <x-forms.button-secondary data-url="{{ route('managedpremises.photos.destroy', $ph->id) }}" class="delete-row btn-sm" icon="trash">@lang('app.delete')</x-forms.button-secondary>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted">No photos yet.</td></tr>
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
$(document).on('click','#uploadPhotoBtn',function(e){
  e.preventDefault();
  const formEl = document.getElementById('pmPhotoForm');
  const fd = new FormData(formEl);
  $.easyAjax({
    url: $(formEl).attr('action'),
    container: '#pmPhotoForm',
    type: 'POST',
    data: fd,
    processData: false,
    contentType: false,
  });
});
</script>
@endpush
