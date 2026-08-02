<x-card>
    <x-slot name="header">@lang('managedpremises::app.module_name')</x-slot>
    <x-slot name="body">
        <div class="small text-muted">@lang('managedpremises::app.address')</div>
        <div>{{ $property->address }}</div>
        <div class="mt-2 small text-muted">@lang('managedpremises::app.access_notes')</div>
        <div>{{ $property->access_notes }}</div>
        <div class="mt-2 small text-muted">@lang('managedpremises::app.hazards')</div>
        <div>{{ $property->hazards }}</div>
    </x-slot>
</x-card>
