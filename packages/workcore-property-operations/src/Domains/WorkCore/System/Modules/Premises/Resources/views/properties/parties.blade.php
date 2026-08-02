@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Parties — {{ $property->name ?? ('Premise #' . $property->id) }}</h4>
        <div class="text-muted">Owners, managers, residents, tenants, customers, advocates, contractors and other premise roles.</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a>
</div>

<div class="card mb-3">
    <div class="card-header">Add party role</div>
    <div class="card-body">
        <x-form method="POST" :action="route('managedpremises.properties.parties.store', $property->id)">
            @csrf
            <div class="row">
                <div class="col-md-3 form-group"><label>Name</label><input class="form-control" name="display_name" required></div>
                <div class="col-md-2 form-group"><label>Role</label><input class="form-control" name="role" placeholder="resident" required></div>
                <div class="col-md-2 form-group"><label>Party source</label><select class="form-control" name="party_type"><option value="snapshot">Local record</option><option value="crm_contact">CRM contact</option><option value="customer">Customer</option><option value="user">User</option><option value="company">Organisation</option></select></div>
                <div class="col-md-2 form-group"><label>External ID</label><input class="form-control" type="number" min="1" name="party_id"></div>
                <div class="col-md-3 form-group"><label>Space scope</label><select class="form-control" name="space_id"><option value="">Whole premise</option>@foreach($spaces as $space)<option value="{{ $space->id }}">{{ $space->name }}</option>@endforeach</select></div>
            </div>
            <div class="row">
                <div class="col-md-3 form-group"><label>Email</label><input class="form-control" type="email" name="email"></div>
                <div class="col-md-2 form-group"><label>Phone</label><input class="form-control" name="phone"></div>
                <div class="col-md-3 form-group"><label>Organisation</label><input class="form-control" name="organisation_name"></div>
                <div class="col-md-2 form-group"><label>Valid from</label><input class="form-control" type="date" name="valid_from"></div>
                <div class="col-md-2 form-group"><label>Valid until</label><input class="form-control" type="date" name="valid_until"></div>
            </div>
            <label class="mr-3"><input type="checkbox" name="is_primary" value="1"> Primary for this role</label>
            <button class="btn btn-primary">Add party</button>
        </x-form>
    </div>
</div>

<div class="card">
    <div class="card-header">Premise parties</div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Role</th><th>Scope</th><th>Contact</th><th>Source</th><th>Validity</th><th>Occupancies</th><th>Agreements</th><th></th></tr></thead>
        <tbody>
        @forelse($parties as $party)
            <tr>
                <td><strong>{{ $party->display_name }}</strong>@if($party->is_primary) <span class="badge badge-primary">Primary</span>@endif<br><small class="text-muted">{{ $party->organisation_name }}</small></td>
                <td>{{ str($party->role)->replace('_', ' ')->title() }}</td>
                <td>{{ optional($party->space)->name ?: 'Whole premise' }}</td>
                <td>{{ $party->phone }}<br><small>{{ $party->email }}</small></td>
                <td>{{ $party->source_type === 'property_contact' ? 'Legacy contact' : str($party->party_type)->replace('_', ' ')->title() }}</td>
                <td>{{ optional($party->valid_from)->format('d M Y') ?: '—' }} – {{ optional($party->valid_until)->format('d M Y') ?: 'Open' }}</td>
                <td>{{ $party->occupancies_count }}</td>
                <td>{{ $party->agreement_parties_count }}</td>
                <td class="text-right">
                    @if(!$party->valid_until)<form method="POST" action="{{ route('managedpremises.properties.parties.retire', [$property->id, $party->id]) }}" class="d-inline">@csrf <button class="btn btn-sm btn-outline-secondary">Retire</button></form>@endif
                    @if(!$party->occupancies_count && !$party->agreement_parties_count && !$party->source_type)<form method="POST" action="{{ route('managedpremises.properties.parties.destroy', [$property->id, $party->id]) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted py-4">No parties recorded.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
