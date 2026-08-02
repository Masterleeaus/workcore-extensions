@extends('layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="d-flex justify-content-between mb-3">
        <h4>@lang('managedpremises::app.keys_access') - {{ $property->name }}</h4>
        <a href="{{ route('managedpremises.properties.show', $property->id) }}" class="btn btn-secondary btn-sm">@lang('app.back')</a>
    </div>

    <x-card>
        <x-slot name="header">
            <div class="d-flex justify-content-between">
                <div>@lang('managedpremises::app.keys_register')</div>
            </div>
        </x-slot>

        <x-slot name="body">
            <form id="pmKeyForm" method="POST" action="{{ route('managedpremises.properties.keys.store', $property->id) }}">
                @csrf
                <div class="row">
                    <div class="col-md-3">
                        <x-forms.select fieldName="type" fieldId="type" fieldLabel="Type">
                            <option value="key">@lang('managedpremises::app.key')</option>
                            <option value="lockbox">@lang('managedpremises::app.lockbox')</option>
                            <option value="code">@lang('managedpremises::app.access_code')</option>
                        </x-forms.select>
                    </div>
                    <div class="col-md-4">
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
                            <td>{{ $k->type }}</td>
                            <td>{{ $k->location }}</td>
                            <td>{{ $k->maskedCode() ?: '—' }}</td>
                            <td>{{ $k->notes }}</td>
                            <td class="text-right">
                                <x-forms.button-secondary data-url="{{ route('managedpremises.properties.keys.destroy', [$property->id, $k->id]) }}" class="delete-row" icon="trash">@lang('app.delete')</x-forms.button-secondary>
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
$(document).on('click', '#saveKey', function (e) {
    e.preventDefault();
    $.easyAjax({
        url: $('#pmKeyForm').attr('action'),
        container: '#pmKeyForm',
        type: "POST",
        data: $('#pmKeyForm').serialize()
    });
});
</script>
@endpush
