@php($premisesProfile = app(\App\Domains\WorkCore\System\Modules\Premises\Services\PremisesProfileRegistry::class)->forProperty($property))
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.show', $property->id) }}">@lang('managedpremises::app.overview')</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.operations.index', $property->id) }}">Operations</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.vacancies.index', $property->id) }}">Vacancies</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.portal.index', $property->id) }}">Portal</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.ownership.index', $property->id) }}">Ownership</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.management-authorities.index', $property->id) }}">Authorities</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.approval-requests.index', $property->id) }}">Approvals</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.applications.index', $property->id) }}">Applications</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.communications.index', $property->id) }}">Messages & notices</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.requests.index', $property->id) }}">Requests</a>
    <a class="btn btn-primary btn-sm mr-2" href="{{ route('managedpremises.properties.spaces.index', $property->id) }}">Spaces</a>
    @if($premisesProfile['features']['agreements'] ?? false)<a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.agreements.index', $property->id) }}">Agreements</a>@endif
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.units.index', $property->id) }}">@lang('managedpremises::app.units')</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.contacts.index', $property->id) }}">@lang('managedpremises::app.contacts')</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.jobs.index', $property->id) }}">@lang('managedpremises::app.jobs')</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.keys.index', $property->id) }}">@lang('managedpremises::app.keys_access')</a>
    @if($premisesProfile['features']['access_register'] ?? true)<a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.access.index', $property->id) }}">Access register</a>@endif
    @if($premisesProfile['features']['condition_records'] ?? true)<a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.conditions.index', $property->id) }}">Condition</a>@endif
    @if($premisesProfile['features']['incidents'] ?? true)<a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.incidents.index', $property->id) }}">Incidents</a>@endif
    @if($premisesProfile['features']['compliance'] ?? true)<a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.compliance.index', $property->id) }}">Compliance</a>@endif
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.photos.index', $property->id) }}">@lang('managedpremises::app.photos')</a>
    <a class="btn btn-outline-primary btn-sm" href="{{ route('managedpremises.properties.checklists.index', $property->id) }}">@lang('managedpremises::app.checklists')</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.tags.index', $property->id) }}">@lang('managedpremises::app.tags')</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.rooms.index', $property->id) }}">@lang('managedpremises::app.rooms')</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.hazards.index', $property->id) }}">@lang('managedpremises::app.hazards')</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.servicewindows.index', $property->id) }}">@lang('managedpremises::app.service_windows')</a>
    <a class="btn btn-outline-primary btn-sm mr-2" href="{{ route('managedpremises.properties.meters.index', $property->id) }}">@lang('managedpremises::app.meter_readings')</a>
    <a class="btn btn-outline-primary btn-sm" href="{{ route('managedpremises.properties.assets.index', $property->id) }}">@lang('managedpremises::app.assets')</a>
</div>