@extends('layouts.app')

@section('content')
<div class="content-wrapper">
  <div class="d-flex justify-content-between mb-3">
    <h4>Documents</h4>
    <a href="{{ route('managedpremises.properties.index') }}" class="btn btn-secondary btn-sm">Premises</a>
  </div>

  <x-card>
    <x-slot name="header">
      <div class="d-flex justify-content-between align-items-center">
        <div>Documents</div>
        <form method="GET" action="{{ route('managedpremises.documents.index') }}" class="d-flex" style="gap:8px;">
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
      <form id="pmDocForm" method="POST" action="{{ route('managedpremises.documents.store') }}" enctype="multipart/form-data">
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
          </div>
          <div class="col-md-4">
            <label class="f-12 mb-1">Type (optional)</label>
            <input type="text" name="doc_type" class="form-control" maxlength="120" placeholder="e.g. SOP, Access, Permit">
          </div>
          <div class="col-md-6 mt-2">
            <label class="f-12 mb-1">Document Name</label>
            <input type="text" name="name" class="form-control" maxlength="190" required placeholder="e.g. Alarm instructions">
          </div>
          <div class="col-md-6 mt-2">
            <label class="f-12 mb-1">File</label>
            <input type="file" name="file" class="form-control" required>
          </div>
          <div class="col-md-12 mt-2">
            <label class="f-12 mb-1">Notes (optional)</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Any quick context for cleaners/operators"></textarea>
          </div>
        </div>
        <x-forms.button-primary class="mt-3" id="uploadDocBtn">Upload</x-forms.button-primary>
      </form>

      <hr>

      <div class="table-responsive">
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Premise</th>
              <th>Name</th>
              <th>Type</th>
              <th>Job</th>
              <th>Uploaded</th>
              <th class="text-right">@lang('app.action')</th>
            </tr>
          </thead>
          <tbody>
            @forelse($docs as $d)
              <tr>
                <td>{{ $d->property?->name ?? '-' }}</td>
                <td>{{ $d->name }}</td>
                <td>{{ $d->doc_type }}</td>
                <td>{{ $d->property_job_id ?? '-' }}</td>
                <td>{{ optional($d->created_at)->format('Y-m-d') }}</td>
                <td class="text-right">
                  <a href="{{ route('managedpremises.properties.documents.index', $d->property_id) }}" class="btn btn-light btn-sm">Open</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted">No documents yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        {{ $docs->links() }}
      </div>
    </x-slot>
  </x-card>
</div>
@endsection

@push('scripts')
<script>
$(document).on('click','#uploadDocBtn',function(e){
  e.preventDefault();
  const formEl = document.getElementById('pmDocForm');
  const fd = new FormData(formEl);
  $.easyAjax({
    url: $(formEl).attr('action'),
    container: '#pmDocForm',
    type: 'POST',
    data: fd,
    processData: false,
    contentType: false,
  });
});
</script>
@endpush
