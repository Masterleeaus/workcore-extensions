@extends('layouts.app')
@section('content')
<div class="content-wrapper">
  <div class="d-flex justify-content-between mb-3">
    <h4>@lang('managedpremises::app.assets') - {{ $property->name }}</h4>
    <a href="{{ route('managedpremises.properties.show', $property->id) }}" class="btn btn-secondary btn-sm">@lang('app.back')</a>
  </div>

  @include('managedpremises::partials.property-tabs', ['property'=>$property])

  <x-card>
    <x-slot name="header">@lang('managedpremises::app.assets')</x-slot>
    <x-slot name="body">
      <form id="pmForm" method="POST" action="{{ $storeUrl }}">
        @csrf
        <div class="row">
          <div class="col-md-4"><x-forms.text fieldName="label" fieldId="label" fieldLabel="@lang('managedpremises::app.asset_label')" /></div>
          <div class="col-md-4"><x-forms.text fieldName="category" fieldId="category" fieldLabel="@lang('managedpremises::app.category')" /></div>
          <div class="col-md-4"><x-forms.text fieldName="serial" fieldId="serial" fieldLabel="@lang('managedpremises::app.serial')" /></div>
          <div class="col-md-6 mt-2"><x-forms.text fieldName="location" fieldId="location" fieldLabel="@lang('managedpremises::app.location')" /></div>
          <div class="col-md-12 mt-2"><x-forms.textarea fieldName="notes" fieldId="notes" fieldLabel="@lang('managedpremises::app.notes')" /></div>
        </div>
        <x-forms.button-primary class="mt-3" id="saveBtn">@lang('app.save')</x-forms.button-primary>
      </form>

      <hr>

      <div class="table-responsive">
        <table class="table table-bordered">
          <thead><tr><th>@lang('managedpremises::app.asset_label')</th><th>@lang('managedpremises::app.category')</th><th>@lang('managedpremises::app.serial')</th><th>@lang('managedpremises::app.location')</th><th class="text-right">@lang('app.action')</th></tr></thead>
          <tbody>
            @forelse($assets as $a)
              <tr>
                <td>{{ $a->label }}</td>
                <td>{{ $a->category }}</td>
                <td>{{ $a->serial }}</td>
                <td>{{ $a->location }}</td>
                <td class="text-right">
                  <x-forms.button-secondary data-url="{{ route('managedpremises.properties.assets.destroy', [$property->id, $a->id]) }}" class="delete-row btn-sm" icon="trash">@lang('app.delete')</x-forms.button-secondary>
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
