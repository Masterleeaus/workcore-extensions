@extends('layouts.app')
@section('content')
<div class="content-wrapper">
  <div class="d-flex justify-content-between mb-3">
    <h4>@lang('managedpremises::app.rooms') - {{ $property->name }}</h4>
    <a href="{{ route('managedpremises.properties.show', $property->id) }}" class="btn btn-secondary btn-sm">@lang('app.back')</a>
  </div>

  @include('managedpremises::partials.property-tabs', ['property'=>$property])

  <x-card>
    <x-slot name="header">@lang('managedpremises::app.rooms')</x-slot>
    <x-slot name="body">
      <form id="pmForm" method="POST" action="{{ $storeUrl }}">
        @csrf
        <div class="row">
          <div class="col-md-4"><x-forms.text fieldName="name" fieldId="name" fieldLabel="@lang('managedpremises::app.room_name')" /></div>
          <div class="col-md-4"><x-forms.text fieldName="type" fieldId="type" fieldLabel="@lang('managedpremises::app.room_type')" /></div>
          <div class="col-md-12 mt-2"><x-forms.textarea fieldName="notes" fieldId="notes" fieldLabel="@lang('managedpremises::app.notes')" /></div>
        </div>
        <x-forms.button-primary class="mt-3" id="saveBtn">@lang('app.save')</x-forms.button-primary>
      </form>

      <hr>

      <div class="table-responsive">
        <table class="table table-bordered">
          <thead><tr><th>@lang('managedpremises::app.room_name')</th><th>@lang('managedpremises::app.room_type')</th><th>@lang('managedpremises::app.notes')</th><th class="text-right">@lang('app.action')</th></tr></thead>
          <tbody>
            @forelse($rooms as $r)
              <tr>
                <td>{{ $r->name }}</td>
                <td>{{ $r->type }}</td>
                <td>{{ $r->notes }}</td>
                <td class="text-right">
                  <x-forms.button-secondary data-url="{{ route('managedpremises.properties.rooms.destroy', [$property->id, $r->id]) }}" class="delete-row btn-sm" icon="trash">@lang('app.delete')</x-forms.button-secondary>
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
$(document).on('click','#saveBtn',function(e){
  e.preventDefault();
  $.easyAjax({
    url: $('#pmForm').attr('action'),
    container: '#pmForm',
    type: "POST",
    data: $('#pmForm').serialize()
  });
});
</script>
@endpush
