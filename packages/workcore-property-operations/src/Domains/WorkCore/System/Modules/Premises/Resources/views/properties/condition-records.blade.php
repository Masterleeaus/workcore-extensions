@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-0">Condition records — {{ $property->name }}</h4><div class="text-muted">Entry, exit, routine, damage, cleanliness, safety and vacant-readiness records.</div></div>
    <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a>
</div>
<div class="alert alert-info"><strong>Boundary:</strong> these records describe premise or space condition. QualityControl remains authoritative for service-quality inspections, rework and corrective action.</div>

<div class="card mb-3"><div class="card-header">Create condition record</div><div class="card-body">
<x-form method="POST" :action="route('managedpremises.properties.conditions.store', $property->id)">@csrf
<div class="row">
<div class="col-md-2 form-group"><label>Type</label><select class="form-control" name="condition_type">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseConditionRecord::TYPES as $type)<option value="{{ $type }}">{{ str($type)->replace('_',' ')->title() }}</option>@endforeach</select></div>
<div class="col-md-2 form-group"><label>Status</label><select class="form-control" name="status"><option value="draft">Draft</option><option value="in_progress">In progress</option><option value="completed">Completed</option></select></div>
<div class="col-md-2 form-group"><label>Space</label><select class="form-control" name="space_id"><option value="">Premise-wide</option>@foreach($spaces as $space)<option value="{{ $space->id }}">{{ $space->name }}</option>@endforeach</select></div>
<div class="col-md-2 form-group"><label>Occupancy</label><select class="form-control" name="occupancy_id"><option value="">None</option>@foreach($occupancies as $occupancy)<option value="{{ $occupancy->id }}">{{ $occupancy->display_name }} — {{ optional($occupancy->space)->name }}</option>@endforeach</select></div>
<div class="col-md-2 form-group"><label>Agreement</label><select class="form-control" name="agreement_id"><option value="">None</option>@foreach($agreements as $agreement)<option value="{{ $agreement->id }}">{{ $agreement->title }}</option>@endforeach</select></div>
<div class="col-md-2 form-group"><label>Recorded by</label><select class="form-control" name="party_role_id"><option value="">Current user</option>@foreach($partyRoles as $party)<option value="{{ $party->id }}">{{ $party->display_name }}</option>@endforeach</select></div>
</div>
<div class="row"><div class="col-md-2 form-group"><label>Scheduled</label><input class="form-control" type="datetime-local" name="scheduled_for"></div><div class="col-md-2 form-group"><label>Recorded</label><input class="form-control" type="datetime-local" name="recorded_at"></div><div class="col-md-2 form-group"><label>Score</label><input class="form-control" type="number" step="0.01" min="0" max="100" name="score"></div><div class="col-md-3 form-group"><label>Compare with</label><select class="form-control" name="comparison_record_id"><option value="">None</option>@foreach($records as $record)<option value="{{ $record->id }}">#{{ $record->id }} {{ str($record->condition_type)->replace('_',' ') }}</option>@endforeach</select></div><div class="col-md-3 form-group"><label>External reference</label><input class="form-control" name="external_reference"></div></div>
<div class="form-group"><label>Summary</label><textarea class="form-control" name="summary" rows="2"></textarea></div>
<div class="row"><div class="col-md-6 form-group"><label>Findings — one per line</label><textarea class="form-control" name="findings" rows="3"></textarea></div><div class="col-md-6 form-group"><label>Actions — one per line</label><textarea class="form-control" name="actions" rows="3"></textarea></div></div>
<button class="btn btn-primary">Create condition record</button>
</x-form></div></div>

<div class="card"><div class="card-header">Condition history</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Record</th><th>Scope</th><th>Status</th><th>Score</th><th>Summary</th><th>Lifecycle</th></tr></thead><tbody>
@forelse($records as $record)
<tr><td><strong>#{{ $record->id }} {{ str($record->condition_type)->replace('_',' ')->title() }}</strong><br><small>{{ optional($record->recorded_at)->format('d M Y H:i') ?: 'Not recorded' }}</small></td><td>{{ optional($record->space)->name ?: 'Premise-wide' }}@if($record->occupancy)<br><small>{{ $record->occupancy->display_name }}</small>@endif</td><td>{{ str($record->status)->replace('_',' ')->title() }}</td><td>{{ $record->score ?? '—' }}</td><td>{{ $record->summary ?: '—' }}@if($record->findings)<br><small>{{ implode(' · ', array_slice($record->findings,0,3)) }}</small>@endif</td><td><x-form method="POST" :action="route('managedpremises.properties.conditions.transition', [$property->id, $record->id])">@csrf<select class="form-control form-control-sm mb-1" name="status">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Domain\Condition\ConditionState::allowedTransitions($record->status) as $status)@if($status !== 'approved')<option value="{{ $status }}">{{ str($status)->replace('_',' ')->title() }}</option>@endif @endforeach</select><input class="form-control form-control-sm mb-1" type="number" step="0.01" min="0" max="100" name="score" placeholder="Score"><button class="btn btn-sm btn-outline-primary" @disabled(empty(array_diff(\App\Domains\WorkCore\System\Modules\Premises\Domain\Condition\ConditionState::allowedTransitions($record->status), ['approved'])))>Apply</button></x-form>@if(in_array('approved', \App\Domains\WorkCore\System\Modules\Premises\Domain\Condition\ConditionState::allowedTransitions($record->status), true))<x-form method="POST" :action="route('managedpremises.properties.conditions.approve', [$property->id, $record->id])" class="mt-1">@csrf<button class="btn btn-sm btn-success">Approve</button></x-form>@endif</td></tr>
@empty<tr><td colspan="6" class="text-center text-muted py-4">No condition records.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
