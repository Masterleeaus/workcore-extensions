@extends('layouts.app')
@section('content')
<div class="content-wrapper">
  <div class="d-flex justify-content-between mb-3">
    <h4>@lang('managedpremises::app.service_windows') - {{ $property->name }}</h4>
    <a href="{{ route('managedpremises.properties.show', $property->id) }}" class="btn btn-secondary btn-sm">@lang('app.back')</a>
  </div>

  @include('managedpremises::partials.property-tabs', ['property'=>$property])

  <x-card>
    <x-slot name="header">@lang('managedpremises::app.service_windows')</x-slot>
    <x-slot name="body">
      <form id="pmForm" method="POST" action="{{ $storeUrl }}">
        @csrf
        <div class="row">
          <div class="col-md-4"><x-forms.text fieldName="days" fieldId="days" fieldLabel="@lang('managedpremises::app.days')" fieldPlaceholder="Mon,Tue,Wed" /></div>
          <div class="col-md-3"><x-forms.text fieldName="time_from" fieldId="time_from" fieldLabel="@lang('managedpremises::app.from')" fieldPlaceholder="09:00" /></div>
          <div class="col-md-3"><x-forms.text fieldName="time_to" fieldId="time_to" fieldLabel="@lang('managedpremises::app.to')" fieldPlaceholder="17:00" /></div>
          <div class="col-md-12 mt-2"><x-forms.textarea fieldName="notes" fieldId="notes" fieldLabel="@lang('managedpremises::app.notes')" /></div>
        </div>
        <x-forms.button-primary class="mt-3" id="saveBtn">@lang('app.save')</x-forms.button-primary>
      </form>

      <hr>

      <div class="table-responsive">
        <table class="table table-bordered">
          <thead><tr><th>@lang('managedpremises::app.days')</th><th>@lang('managedpremises::app.from')</th><th>@lang('managedpremises::app.to')</th><th>@lang('managedpremises::app.notes')</th><th class="text-right">@lang('app.action')</th></tr></thead>
          <tbody>
            @forelse($windows as $w)
              <tr>
                <td>{{ $w->days }}</td>
                <td>{{ $w->time_from }}</td>
                <td>{{ $w->time_to }}</td>
                <td>{{ $w->notes }}</td>
                <td class="text-right">
                  <x-forms.button-secondary data-url="{{ route('managedpremises.properties.servicewindows.destroy', [$property->id, $w->id]) }}" class="delete-row btn-sm" icon="trash">@lang('app.delete')</x-forms.button-secondary>
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
    data: $('#pmForm').serialize()
  });
});
</script>
@endpush
