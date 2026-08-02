@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">{{ str($profile['terminology']['occupant'] ?? 'Occupant')->plural() }} and allocations — {{ $property->name }}</h4>
        <div class="text-muted">Reservations, active occupancy, notice, departure and transfer history.</div>
    </div>
    <div><a class="btn btn-outline-primary" href="{{ route('managedpremises.properties.parties.index', $property->id) }}">Manage parties</a> @if($profile['features']['agreements'] ?? false)<a class="btn btn-outline-primary" href="{{ route('managedpremises.properties.agreements.index', $property->id) }}">Agreements</a>@endif <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a></div>
</div>

<div class="card mb-3">
    <div class="card-header">Create reservation or occupancy</div>
    <div class="card-body">
        <x-form method="POST" :action="route('managedpremises.properties.occupancies.store', $property->id)">
            @csrf
            <div class="row">
                <div class="col-md-3 form-group"><label>Party</label><select class="form-control" name="party_role_id" required><option value="">Choose</option>@foreach($parties as $party)<option value="{{ $party->id }}">{{ $party->display_name }} — {{ str($party->role)->replace('_',' ') }}</option>@endforeach</select></div>
                <div class="col-md-3 form-group"><label>Space</label><select class="form-control" name="space_id" required><option value="">Choose</option>@foreach($spaces as $space)<option value="{{ $space->id }}">{{ optional($space->parent)->name ? optional($space->parent)->name.' / ' : '' }}{{ $space->name }} ({{ $space->availability_status }}, {{ $space->occupiedCapacity() }} / {{ $space->capacity ?? '∞' }})</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><label>Occupancy type</label><select class="form-control" name="occupancy_type">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseOccupancy::OCCUPANCY_TYPES as $type)<option value="{{ $type }}">{{ str($type)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><label>Initial status</label><select class="form-control" name="status"><option value="applicant">Applicant</option><option value="reserved" selected>Reserved</option><option value="pending">Pending</option><option value="active">Active</option></select></div>
                <div class="col-md-2 form-group"><label>Capacity used</label><input class="form-control" type="number" min="1" name="capacity_used" value="1" required></div>
            </div>
            <div class="row">
                <div class="col-md-2 form-group"><label>Start</label><input class="form-control" type="date" name="start_date"></div>
                <div class="col-md-2 form-group"><label>Expected end</label><input class="form-control" type="date" name="end_date"></div>
                <div class="col-md-2 form-group"><label>Legacy agreement ref.</label><input class="form-control" name="agreement_reference"></div>
                <div class="col-md-2 form-group"><label>Legacy billing ref.</label><input class="form-control" name="billing_account_reference"></div>
                <div class="col-md-2 form-group"><label>Legacy deposit/bond ref.</label><input class="form-control" name="deposit_reference"></div>
                <div class="col-md-2 form-group"><label>Move-in state</label><input class="form-control" name="move_in_status"></div>
            </div>
            <div class="form-group"><label>Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
            <button class="btn btn-primary">Create allocation</button>
        </x-form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Applications, reservations and current occupancies</div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Party</th><th>Space</th><th>Type</th><th>Status</th><th>Dates</th><th>Capacity</th><th>Change state</th><th>Transfer</th></tr></thead>
        <tbody>
        @forelse($currentOccupancies as $occupancy)
            <tr>
                <td><strong>{{ $occupancy->display_name }}</strong><br><small>{{ optional($occupancy->partyRole)->role }}</small></td>
                <td>{{ optional($occupancy->space)->name }}</td>
                <td>{{ str($occupancy->occupancy_type)->replace('_',' ')->title() }}</td>
                <td><span class="badge badge-info">{{ str($occupancy->status)->replace('_',' ')->title() }}</span></td>
                <td>{{ optional($occupancy->start_date)->format('d M Y') ?: '—' }}<br><small>to {{ optional($occupancy->end_date)->format('d M Y') ?: 'open' }}</small></td>
                <td>{{ $occupancy->capacity_used }}</td>
                <td>
                    <x-form method="POST" :action="route('managedpremises.properties.occupancies.transition', [$property->id, $occupancy->id])" class="d-flex">
                        @csrf
                        <select class="form-control form-control-sm mr-1" name="status">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Domain\Occupancy\OccupancyState::allowedTransitions($occupancy->status) as $status)<option value="{{ $status }}">{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select>
                        <button class="btn btn-sm btn-outline-primary" @disabled(empty(\App\Domains\WorkCore\System\Modules\Premises\Domain\Occupancy\OccupancyState::allowedTransitions($occupancy->status)))>Apply</button>
                    </x-form>
                </td>
                <td>
                    <x-form method="POST" :action="route('managedpremises.properties.occupancies.transfer', [$property->id, $occupancy->id])">
                        @csrf
                        <select class="form-control form-control-sm mb-1" name="destination_space_id" required><option value="">Destination</option>@foreach($spaces as $space)@if($space->id !== $occupancy->space_id)<option value="{{ $space->id }}">{{ $space->name }}</option>@endif @endforeach</select>
                        <input class="form-control form-control-sm mb-1" name="transfer_reason" placeholder="Reason">
                        <button class="btn btn-sm btn-outline-secondary">Transfer</button>
                    </x-form>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No current occupancy records.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header">Occupancy history</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Party</th><th>Space</th><th>Status</th><th>Started</th><th>Ended</th><th>Transfer lineage</th></tr></thead>
        <tbody>
        @forelse($history as $occupancy)
            <tr><td>{{ $occupancy->display_name }}</td><td>{{ optional($occupancy->space)->name }}</td><td>{{ str($occupancy->status)->title() }}</td><td>{{ optional($occupancy->start_date)->format('d M Y') ?: '—' }}</td><td>{{ optional($occupancy->actual_end_date)->format('d M Y') ?: '—' }}</td><td>@if($occupancy->transfers->isNotEmpty())To {{ optional($occupancy->transfers->first()->space)->name }}@elseif($occupancy->previousOccupancy)From {{ optional($occupancy->previousOccupancy->space)->name }}@else—@endif</td></tr>
        @empty<tr><td colspan="6" class="text-center text-muted py-4">No completed occupancy history.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
