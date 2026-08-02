@extends('layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="d-flex justify-content-between mb-3">
        <h4>@lang('managedpremises::app.checklists') - {{ $property->name }}</h4>
        <a href="{{ route('managedpremises.properties.show', $property->id) }}" class="btn btn-secondary btn-sm">@lang('app.back')</a>
    </div>

    <x-card>
        <x-slot name="header">@lang('managedpremises::app.checklists')</x-slot>
        <x-slot name="body">
            <form id="pmChecklistForm" method="POST" action="{{ route('managedpremises.properties.checklists.store', $property->id) }}">
                @csrf
                <div class="row">
                    <div class="col-md-3">
                        <x-forms.select fieldName="type" fieldId="type" fieldLabel="@lang('managedpremises::app.type')">
                            <option value="inspection">@lang('managedpremises::app.inspection')</option>
                            <option value="cleaning">@lang('managedpremises::app.cleaning')</option>
                            <option value="handover">@lang('managedpremises::app.handover')</option>
                        </x-forms.select>
                    </div>
                    <div class="col-md-9">
                        <x-forms.text fieldName="title" fieldId="title" fieldLabel="@lang('managedpremises::app.title')" />
                    </div>
                </div>

                <div class="mt-2">
                    <div class="small text-muted">@lang('managedpremises::app.checklist_items_hint')</div>
                    <textarea class="form-control" name="items_raw" id="items_raw" rows="5" placeholder="One item per line"></textarea>
                </div>

                <x-forms.button-primary class="mt-3" id="saveChecklist">@lang('app.save')</x-forms.button-primary>
            </form>

            <hr>

            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead><tr><th>@lang('managedpremises::app.type')</th><th>@lang('managedpremises::app.title')</th><th>@lang('app.date')</th><th class="text-right">@lang('app.action')</th></tr></thead>
                    <tbody>
                    @forelse($checklists as $c)
                        <tr>
                            <td>{{ $c->type }}</td>
                            <td>{{ $c->title }}</td>
                            <td>{{ $c->created_at?->format(company()->date_format) }}</td>
                            <td class="text-right">
                                <x-forms.button-secondary data-url="{{ route('managedpremises.properties.checklists.destroy', [$property->id, $c->id]) }}" class="delete-row btn-sm" icon="trash">@lang('app.delete')</x-forms.button-secondary>
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
$(document).on('click', '#saveChecklist', function (e) {
    e.preventDefault();
    const raw = ($('#items_raw').val() || '').split(/\r?\n/).map(x => x.trim()).filter(Boolean);
    const items = raw.map(label => ({label}));
    const payload = $('#pmChecklistForm').serializeArray();
    payload.push({name:'items', value: JSON.stringify(items)});
    $.easyAjax({
        url: $('#pmChecklistForm').attr('action'),
        container: '#pmChecklistForm',
        type: "POST",
        data: payload
    });
});
</script>
@endpush
