@extends('layouts.app')

@section('content')
<div class="content-wrapper">
    <div class="d-flex justify-content-between mb-3">
        <h4>@lang('managedpremises::app.photos') - {{ $property->name }}</h4>
        <a href="{{ route('managedpremises.properties.show', $property->id) }}" class="btn btn-secondary btn-sm">@lang('app.back')</a>
    </div>

    <x-card>
        <x-slot name="header">@lang('managedpremises::app.photo_gallery')</x-slot>
        <x-slot name="body">
            <form id="pmPhotoForm" method="POST" enctype="multipart/form-data" action="{{ route('managedpremises.properties.photos.store', $property->id) }}">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <x-forms.file fieldName="photo" fieldId="photo" fieldLabel="@lang('managedpremises::app.photo')" />
                    </div>
                    <div class="col-md-6">
                        <x-forms.text fieldName="caption" fieldId="caption" fieldLabel="@lang('managedpremises::app.caption')" />
                    </div>
                </div>
                <x-forms.button-primary class="mt-3" id="savePhoto">@lang('app.upload')</x-forms.button-primary>
            </form>

            <hr>

            <div class="row">
                @forelse($photos as $p)
                    <div class="col-md-3 mb-3">
                        <div class="border rounded p-2">
                            <img src="{{ asset_url_local_s3(App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyPhoto::FILE_PATH.'/'.$p->path) }}" class="img-fluid" alt="">
                            <div class="small mt-2">{{ $p->caption }}</div>
                            <div class="mt-2 text-right">
                                <x-forms.button-secondary data-url="{{ route('managedpremises.properties.photos.destroy', [$property->id, $p->id]) }}" class="delete-row btn-sm" icon="trash">@lang('app.delete')</x-forms.button-secondary>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center text-muted">@lang('managedpremises::app.no_records')</div>
                @endforelse
            </div>
        </x-slot>
    </x-card>
</div>
@endsection

@push('scripts')
<script>
$(document).on('click', '#savePhoto', function (e) {
    e.preventDefault();
    let fd = new FormData($('#pmPhotoForm')[0]);
    $.easyAjax({
        url: $('#pmPhotoForm').attr('action'),
        container: '#pmPhotoForm',
        type: "POST",
        file: true,
        data: fd
    });
});
</script>
@endpush
