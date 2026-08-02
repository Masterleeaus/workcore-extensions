@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h4 class="mb-0">{{ __('managedpremises::pm.properties') }}</h4><a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('managedpremises.portfolios.index') }}">Manage portfolios</a></div>
        @if(function_exists('user_can') ? user_can('managedpremises.create') : true)
<x-forms.button-primary :link="route('managedpremises.properties.create')" icon="plus">
                @lang('app.add')
            </x-forms.button-primary>
        @endif
    </div>
    <div class="row mb-3">
        @foreach([
            ['label' => 'Premises', 'value' => $portfolioSummary['total_premises'] ?? 0],
            ['label' => 'Occupied spaces', 'value' => $portfolioSummary['occupied_spaces'] ?? 0],
            ['label' => 'Available spaces', 'value' => $portfolioSummary['available_spaces'] ?? 0],
            ['label' => 'Spaces in turnover', 'value' => $portfolioSummary['turnover_spaces'] ?? 0],
            ['label' => 'Open incidents', 'value' => $portfolioSummary['open_standard_incidents'] ?? 0],
            ['label' => 'Overdue compliance', 'value' => $portfolioSummary['overdue_compliance'] ?? 0],
            ['label' => 'Active workflows', 'value' => $portfolioSummary['active_workflows'] ?? 0],
        ] as $card)
            <div class="col-xl-3 col-md-4 col-sm-6 mb-3">
                <div class="card h-100"><div class="card-body">
                    <div class="small text-muted">{{ $card['label'] }}</div>
                    <div class="h3 mb-0">{{ $card['value'] }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="form-inline">
                <label class="mr-2">Premises profile</label>
                <select class="form-control mr-2" name="profile_key">
                    <option value="">All profiles</option>
                    @foreach($profileLabels as $key => $label)
                        <option value="{{ $key }}" {{ $selectedProfile === $key ? 'selected' : '' }}>
                            {{ $label }} ({{ $portfolioSummary['profile_counts'][$key] ?? 0 }})
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-outline-primary" type="submit">Filter</button>
                @if($selectedProfile)<a class="btn btn-link" href="{{ route('managedpremises.properties.index') }}">Clear</a>@endif
            </form>
        </div>
    </div>

    @if(($portfolioSummary['profile_counts']['service_site'] ?? 0) > 0)
        <div class="row mb-3">
            <div class="col-lg-6 mb-3">@include('managedpremises::widgets.next_visits')</div>
            <div class="col-lg-6 mb-3">@include('managedpremises::widgets.overdue_inspections')</div>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>@lang('managedpremises::app.labels.name')</th>
                            <th>Profile</th>
                            <th>@lang('managedpremises::app.labels.type')</th>
                            <th>@lang('managedpremises::app.labels.status')</th>
                            <th>@lang('managedpremises::app.labels.address')</th>
                            <th class="text-right">@lang('app.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($properties as $property)
                            <tr>
                                <td>
                                    <a href="{{ route('managedpremises.properties.show', $property->id) }}">
                                        {{ $property->name ?? ('#' . $property->id) }}
                                    </a>
                                    @if ($property->property_code)
                                        <div class="text-muted f-12">{{ $property->property_code }}</div>
                                    @endif
                                </td>
                                <td>{{ $profileLabels[$property->profileKey()] ?? str($property->profileKey())->replace('_', ' ')->title() }}</td>
                                <td>{{ ucfirst($property->type) }}</td>
                                <td>{{ ucfirst($property->status) }}</td>
                                <td>{{ trim(implode(' ', array_filter([$property->address_line1, $property->suburb, $property->state, $property->postcode]))) }}</td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('managedpremises.properties.operations.index', $property->id) }}">Operations</a>
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('managedpremises.properties.edit', $property->id) }}">@lang('app.edit')</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">@lang('app.noRecordFound')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $properties->links() }}
            </div>
        </div>
    </div>
@endsection
