@extends('layouts.app')
@section('content')
<div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">{{ $dashboard['profile']['label'] ?? 'Premises Operations' }} — {{ $property->name }}</h4>
            <div class="text-muted">Profile-specific operating view. Financial balances remain in Titan Money and maintenance execution remains in WorkCore.</div>
        </div>
    </div>

    @include('managedpremises::partials.property-tabs', ['property' => $property])

    <div class="row mt-3">
        @foreach($dashboard['cards'] as $card)
            <div class="col-xl-3 col-md-4 col-sm-6 mb-3">
                <x-card>
                    <x-slot name="body">
                        <div class="small text-muted">{{ $card['label'] }}</div>
                        <div class="h3 mb-0">
                            {{ $card['value'] }}@if(($card['metric'] ?? '') === 'occupancy_rate')%@endif
                        </div>
                    </x-slot>
                </x-card>
            </div>
        @endforeach
    </div>

    @if(!empty($dashboard['alerts']))
        <x-card class="mb-3">
            <x-slot name="header">Operational alerts</x-slot>
            <x-slot name="body">
                <div class="row">
                    @foreach($dashboard['alerts'] as $alert)
                        <div class="col-md-6 mb-2">
                            <div class="alert alert-{{ $alert['level'] === 'danger' ? 'danger' : 'warning' }} mb-0 py-2">
                                <strong>{{ $alert['count'] }}</strong> — {{ $alert['label'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-slot>
        </x-card>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <x-card class="mb-3">
                <x-slot name="header">Active workflows</x-slot>
                <x-slot name="body">
                    @forelse($dashboard['active_workflows'] as $workflow)
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>{{ $workflow->title }}</strong>
                                    <div class="small text-muted">
                                        {{ ucfirst($workflow->status) }} · Current stage: {{ $workflow->currentStageLabel() ?: '—' }}
                                        @if($workflow->due_at) · Due {{ $workflow->due_at->format('d M Y') }} @endif
                                    </div>
                                </div>
                                <span class="badge badge-{{ $workflow->status === 'paused' ? 'warning' : 'primary' }}">{{ $workflow->completionPercent() }}%</span>
                            </div>
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $workflow->completionPercent() }}%"></div>
                            </div>
                            <div class="small mt-2">
                                @if($workflow->space)Space: {{ $workflow->space->name }} · @endif
                                @if($workflow->occupancy)Occupancy: {{ $workflow->occupancy->display_name }} · @endif
                                @if($workflow->agreement)Agreement: {{ $workflow->agreement->title }} · @endif
                                @if($workflow->incident)Incident: {{ $workflow->incident->title }}@endif
                            </div>
                            <div class="d-flex flex-wrap mt-3">
                                @if($workflow->status === 'active')
                                    <x-form method="POST" :action="route('managedpremises.properties.operations.workflows.advance', [$property->id, $workflow->id])" class="mr-2 mb-2">
                                        @csrf
                                        <button class="btn btn-sm btn-primary" type="submit">Complete current stage</button>
                                    </x-form>
                                    <x-form method="POST" :action="route('managedpremises.properties.operations.workflows.transition', [$property->id, $workflow->id])" class="mr-2 mb-2">
                                        @csrf
                                        <input type="hidden" name="status" value="paused">
                                        <button class="btn btn-sm btn-outline-warning" type="submit">Pause</button>
                                    </x-form>
                                @elseif($workflow->status === 'paused')
                                    <x-form method="POST" :action="route('managedpremises.properties.operations.workflows.transition', [$property->id, $workflow->id])" class="mr-2 mb-2">
                                        @csrf
                                        <input type="hidden" name="status" value="active">
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Resume</button>
                                    </x-form>
                                @endif
                                <x-form method="POST" :action="route('managedpremises.properties.operations.workflows.transition', [$property->id, $workflow->id])" class="mb-2">
                                    @csrf
                                    <input type="hidden" name="status" value="cancelled">
                                    <input type="hidden" name="reason" value="Cancelled from operations dashboard">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                </x-form>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted">No active workflows.</div>
                    @endforelse
                </x-slot>
            </x-card>

            <x-card>
                <x-slot name="header">Recent workflow history</x-slot>
                <x-slot name="body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Workflow</th><th>Status</th><th>Updated</th></tr></thead>
                            <tbody>
                            @forelse($dashboard['recent_workflows'] as $workflow)
                                <tr>
                                    <td>{{ $workflow->title }}</td>
                                    <td>{{ ucfirst($workflow->status) }}</td>
                                    <td>{{ optional($workflow->updated_at)->format('d M Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted">No completed or cancelled workflows.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-slot>
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card>
                <x-slot name="header">Start workflow</x-slot>
                <x-slot name="body">
                    <x-form method="POST" :action="route('managedpremises.properties.operations.workflows.store', $property->id)">
                        @csrf
                        <div class="form-group">
                            <label>Workflow template</label>
                            <select class="form-control" name="template_key" required>
                                @foreach($dashboard['templates'] as $key => $template)
                                    <option value="{{ $key }}">{{ $template['label'] ?? $key }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Title override</label>
                            <input class="form-control" name="title" maxlength="191">
                        </div>
                        <div class="form-group">
                            <label>Space</label>
                            <select class="form-control" name="space_id">
                                <option value="">—</option>
                                @foreach($dashboard['spaces'] as $space)
                                    <option value="{{ $space->id }}">{{ $space->name }} ({{ str_replace('_', ' ', $space->availability_status) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Occupancy</label>
                            <select class="form-control" name="occupancy_id">
                                <option value="">—</option>
                                @foreach($dashboard['occupancies'] as $occupancy)
                                    <option value="{{ $occupancy->id }}">{{ $occupancy->display_name }} — {{ $occupancy->status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Agreement</label>
                            <select class="form-control" name="agreement_id">
                                <option value="">—</option>
                                @foreach($dashboard['agreements'] as $agreement)
                                    <option value="{{ $agreement->id }}">{{ $agreement->title }} — {{ $agreement->status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Standard incident</label>
                            <select class="form-control" name="incident_id">
                                <option value="">—</option>
                                @foreach($dashboard['incidents'] as $incident)
                                    <option value="{{ $incident->id }}">{{ $incident->title }} — {{ $incident->status }}</option>
                                @endforeach
                            </select>
                            <div class="small text-muted">Sensitive and restricted incidents are not exposed in this generic selector.</div>
                        </div>
                        <div class="form-group">
                            <label>Start status</label>
                            <select class="form-control" name="status">
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Due date</label>
                            <input class="form-control" type="date" name="due_at">
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit">Start workflow</button>
                    </x-form>
                    <div class="small text-muted mt-3">
                        Workflows coordinate existing records. They do not create financial transactions, perform legal interpretation, or replace WorkCore jobs.
                    </div>
                </x-slot>
            </x-card>
        </div>
    </div>
</div>
@endsection
