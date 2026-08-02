@extends('layouts.app')

@section('content')
<div class="content-wrapper">
    <h4 class="mb-3">@lang('managedpremises::app.settings')</h4>

    <x-card>
        <x-slot name="header">@lang('managedpremises::app.settings')</x-slot>
        <x-slot name="body">
            <form id="pmSettingsForm" method="POST" action="{{ route('managedpremises.settings.store') }}">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <x-forms.text fieldName="default_job_status" fieldId="default_job_status" fieldLabel="@lang('managedpremises::app.default_job_status')" :fieldValue="($settings['default_job_status'] ?? '')" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.checkbox fieldName="require_access_notes" fieldId="require_access_notes" fieldLabel="@lang('managedpremises::app.require_access_notes')" fieldValue="1" :checked="(bool)($settings['require_access_notes'] ?? false)" />
                    </div>
                    <div class="col-md-4">
                        <x-forms.checkbox fieldName="enable_unit_tracking" fieldId="enable_unit_tracking" fieldLabel="@lang('managedpremises::app.enable_unit_tracking')" fieldValue="1" :checked="(bool)($settings['enable_unit_tracking'] ?? true)" />
                    </div>
                </div>

                <x-forms.button-primary class="mt-3" id="saveSettings">@lang('app.save')</x-forms.button-primary>
            </form>
        </x-slot>
    </x-card>
</div>
@endsection

@push('scripts')
<script>
$(document).on('click', '#saveSettings', function (e) {
    e.preventDefault();
    $.easyAjax({
        url: $('#pmSettingsForm').attr('action'),
        container: '#pmSettingsForm',
        type: "POST",
        data: $('#pmSettingsForm').serialize()
    });
});
</script>
@endpush
