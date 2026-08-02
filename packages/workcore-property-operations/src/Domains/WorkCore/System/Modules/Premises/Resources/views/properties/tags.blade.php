@extends('layouts.app')
@section('content')
<div class="content-wrapper">
  <div class="d-flex justify-content-between mb-3">
    <h4>@lang('managedpremises::app.tags') - {{ $property->name }}</h4>
    <a href="{{ route('managedpremises.properties.show', $property->id) }}" class="btn btn-secondary btn-sm">@lang('app.back')</a>
  </div>

  @include('managedpremises::partials.property-tabs', ['property'=>$property])

  <x-card>
    <x-slot name="header">@lang('managedpremises::app.tags')</x-slot>
    <x-slot name="body">
      <form id="pmTagForm" method="POST" action="{{ $storeUrl }}">
        @csrf
        <x-forms.text fieldName="tag" fieldId="tag" fieldLabel="@lang('managedpremises::app.tag')" />
        <x-forms.button-primary class="mt-3" id="saveTag">@lang('app.save')</x-forms.button-primary>
      </form>

      <hr>

      <div class="table-responsive">
        <table class="table table-bordered">
          <thead><tr><th>@lang('managedpremises::app.tag')</th><th class="text-right">@lang('app.action')</th></tr></thead>
          <tbody>
            @forelse($tags as $t)
              <tr>
                <td>{{ $t->tag }}</td>
                <td class="text-right">
                  <x-forms.button-secondary data-url="{{ route('managedpremises.properties.tags.destroy', [$property->id, $t->id]) }}" class="delete-row btn-sm" icon="trash">@lang('app.delete')</x-forms.button-secondary>
                </td>
              </tr>
            @empty
              <tr><td colspan="2" class="text-center text-muted">@lang('managedpremises::app.no_records')</td></tr>
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
$(document).on('click','#saveTag',function(e){
  e.preventDefault();
  $.easyAjax({
    url: $('#pmTagForm').attr('action'),
    container: '#pmTagForm',
    type: "POST",
    data: $('#pmTagForm').serialize()
  });
});
</script>
@endpush
