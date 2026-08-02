@php
// Sidebar must never throw.
// Avoid DB calls or undefined variables here.
@endphp

@if(
    function_exists('module_enabled') && module_enabled('managedpremises') &&
    function_exists('user') && user() &&
    (
        user()->can('managedpremises.view') ||
        user()->can('managedpremises.create') ||
        user()->can('managedpremises.update') ||
        user()->can('managedpremises.delete')
    )
)
<li class="nav-item">
    <a class="nav-link" href="{{ \Illuminate\Support\Facades\Route::has('managedpremises.dashboard') ? route('managedpremises.dashboard') : '#' }}">
        <i class="fa fa-building"></i>
        <span>@lang('managedpremises::app.module_name')</span>
    </a>

    <ul class="nav flex-column ml-3">
        <li class="nav-item">
            <a class="nav-link" href="{{ \Illuminate\Support\Facades\Route::has('managedpremises.properties.index') ? route('managedpremises.properties.index') : '#' }}">@lang('managedpremises::app.properties')</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ \Illuminate\Support\Facades\Route::has('managedpremises.portfolios.index') ? route('managedpremises.portfolios.index') : '#' }}">Portfolios</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ \Illuminate\Support\Facades\Route::has('managedpremises.calendar.index') ? route('managedpremises.calendar.index') : '#' }}"><i class="ti ti-calendar"></i> {{ __('managedpremises::app.calendar') }}</a>
        </li>
        @if(user()->can('managedpremises.settings'))
        <li class="nav-item">
            <a class="nav-link" href="{{ \Illuminate\Support\Facades\Route::has('managedpremises.settings.index') ? route('managedpremises.settings.index') : '#' }}">@lang('managedpremises::app.settings')</a>
        </li>
        @endif
    </ul>
</li>
@endif

