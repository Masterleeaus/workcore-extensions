@extends('layouts.app')

@section('title', __('managedpremises::pm.calendar'))

@section('content')
<div class="container-fluid">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0">{{ __('managedpremises::pm.calendar') }}</h3>
        <form method="get" class="d-flex gap-2">
            <input type="month" name="month" value="{{ $month }}" class="form-control" style="max-width: 220px;">
            <button class="btn btn-primary">{{ __('managedpremises::pm.view') }}</button>
        </form>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-0 text-muted">
                {{ __('managedpremises::pm.showing') }} {{ $start->format('d M Y') }} → {{ $end->format('d M Y') }}
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('managedpremises::pm.visits') }}</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('managedpremises::pm.when') }}</th>
                                <th>{{ __('managedpremises::pm.property') }}</th>
                                <th>{{ __('managedpremises::pm.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($visits as $v)
                            <tr>
                                <td>{{ optional($v->scheduled_for)->format('d M Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('managedpremises.properties.show', $v->property_id) }}">
                                        {{ $v->property?->name ?? ('#'.$v->property_id) }}
                                    </a>
                                </td>
                                <td><span class="badge bg-secondary">{{ $v->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted p-4">{{ __('managedpremises::pm.none') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">{{ __('managedpremises::pm.inspections') }}</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('managedpremises::pm.when') }}</th>
                                <th>{{ __('managedpremises::pm.property') }}</th>
                                <th>{{ __('managedpremises::pm.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($inspections as $i)
                            <tr>
                                <td>{{ optional($i->inspected_at)->format('d M Y H:i') ?? optional($i->created_at)->format('d M Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('managedpremises.properties.show', $i->property_id) }}">
                                        {{ '#'.$i->property_id }}
                                    </a>
                                </td>
                                <td><span class="badge bg-secondary">{{ $i->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted p-4">{{ __('managedpremises::pm.none') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mt-3">
            <div class="card">
                <div class="card-header">Compliance due dates</div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead><tr><th>Due</th><th>Premise</th><th>Requirement</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($complianceOccurrences as $occurrence)
                            <tr>
                                <td>{{ optional($occurrence->due_at)->format('d M Y H:i') }}</td>
                                <td><a href="{{ route('managedpremises.properties.compliance.index', $occurrence->premise_id) }}">{{ optional($occurrence->premise)->name ?: ('#'.$occurrence->premise_id) }}</a></td>
                                <td>{{ optional($occurrence->requirement)->title ?: ('Requirement #'.$occurrence->requirement_id) }}</td>
                                <td><span class="badge bg-{{ $occurrence->status === 'overdue' ? 'danger' : ($occurrence->status === 'due' ? 'warning' : 'secondary') }}">{{ ucfirst($occurrence->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted p-4">No compliance due dates this month.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
