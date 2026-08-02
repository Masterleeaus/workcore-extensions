@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Access register — {{ $property->name }}</h4>
        <div class="text-muted">Keys, cards, remotes, credentials, custody history and authorised access periods.</div>
    </div>
    <div>
        <a class="btn btn-outline-primary" href="{{ route('managedpremises.properties.access-card-requests.index', $property->id) }}">Physical access cards</a>
        <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a>
    </div>
</div>

<div class="alert alert-warning">
    Access secrets are encrypted at rest and displayed only in masked form. AI capabilities must never receive unmasked codes, PINs or credentials.
</div>

<div class="card mb-3">
    <div class="card-header">Add access item</div>
    <div class="card-body">
        <x-form method="POST" :action="route('managedpremises.properties.access.store', $property->id)">
            @csrf
            <div class="row">
                <div class="col-md-2 form-group"><label>Type</label><select class="form-control" name="item_type" required>@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAccessItem::TYPES as $type)<option value="{{ $type }}">{{ str($type)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                <div class="col-md-3 form-group"><label>Label</label><input class="form-control" name="label" required maxlength="191" placeholder="Front door key, Gate PIN"></div>
                <div class="col-md-2 form-group"><label>Identifier</label><input class="form-control" name="identifier" maxlength="191" placeholder="Key tag / card no."></div>
                <div class="col-md-2 form-group"><label>Space</label><select class="form-control" name="space_id"><option value="">Premise-wide</option>@foreach($spaces as $space)<option value="{{ $space->id }}">{{ $space->name }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><label>Status</label><select class="form-control" name="status"><option value="available">Available</option><option value="issued">Issued</option></select></div>
                <div class="col-md-1 form-group"><label>Expires</label><input class="form-control" type="date" name="expires_at"></div>
            </div>
            <div class="row">
                <div class="col-md-3 form-group"><label>Secret / code</label><input class="form-control" type="password" name="secret_value" autocomplete="new-password" placeholder="Stored encrypted"></div>
                <div class="col-md-3 form-group"><label>Holder from parties</label><select class="form-control" name="current_party_role_id"><option value="">None</option>@foreach($partyRoles as $party)<option value="{{ $party->id }}">{{ $party->display_name }} — {{ str($party->role)->replace('_',' ') }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><label>Other holder</label><input class="form-control" name="current_holder_name"></div>
                <div class="col-md-2 form-group"><label>Expected return</label><input class="form-control" type="datetime-local" name="expected_return_at"></div>
                <div class="col-md-2 form-group"><label>Location</label><input class="form-control" name="location" placeholder="Office key safe"></div>
            </div>
            <div class="form-group"><label>Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
            <button class="btn btn-primary">Add access item</button>
        </x-form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">Access items and custody</div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Item</th><th>Scope</th><th>Status</th><th>Holder</th><th>Secret</th><th>Issue</th><th>Transition</th><th>Recent history</th></tr></thead>
        <tbody>
        @forelse($items as $item)
            <tr>
                <td><strong>{{ $item->label }}</strong><br><small>{{ str($item->item_type)->replace('_',' ')->title() }} @if($item->identifier)· {{ $item->identifier }}@endif</small></td>
                <td>{{ optional($item->space)->name ?: 'Premise-wide' }}<br><small>{{ $item->location }}</small></td>
                <td><span class="badge badge-info">{{ str($item->status)->replace('_',' ')->title() }}</span></td>
                <td>{{ $item->current_holder_name ?: '—' }}@if($item->expected_return_at)<br><small>Due {{ $item->expected_return_at->format('d M Y H:i') }}</small>@endif</td>
                <td>{{ $item->maskedSecret() ?: '—' }}</td>
                <td>
                    @if(in_array('issued', \App\Domains\WorkCore\System\Modules\Premises\Domain\Access\AccessItemState::allowedTransitions($item->status), true))
                    <x-form method="POST" :action="route('managedpremises.properties.access.issue', [$property->id, $item->id])">
                        @csrf
                        <select class="form-control form-control-sm mb-1" name="party_role_id"><option value="">Other holder</option>@foreach($partyRoles as $party)<option value="{{ $party->id }}">{{ $party->display_name }}</option>@endforeach</select>
                        <input class="form-control form-control-sm mb-1" name="holder_display_name" placeholder="Other holder">
                        <input class="form-control form-control-sm mb-1" type="datetime-local" name="expected_return_at">
                        <button class="btn btn-sm btn-outline-primary">Issue</button>
                    </x-form>
                    @else—@endif
                </td>
                <td>
                    <x-form method="POST" :action="route('managedpremises.properties.access.transition', [$property->id, $item->id])">
                        @csrf
                        <select class="form-control form-control-sm mb-1" name="status" required>@foreach(\App\Domains\WorkCore\System\Modules\Premises\Domain\Access\AccessItemState::allowedTransitions($item->status) as $status)@if($status !== 'issued')<option value="{{ $status }}">{{ str($status)->replace('_',' ')->title() }}</option>@endif @endforeach</select>
                        <input class="form-control form-control-sm mb-1" name="notes" placeholder="Reason / note">
                        <button class="btn btn-sm btn-outline-secondary" @disabled(empty(array_diff(\App\Domains\WorkCore\System\Modules\Premises\Domain\Access\AccessItemState::allowedTransitions($item->status), ['issued'])))>Apply</button>
                    </x-form>
                </td>
                <td>@forelse($item->events->take(4) as $event)<div><small>{{ $event->occurred_at->format('d M Y') }} · {{ str($event->event_type)->title() }} @if($event->holder_display_name)· {{ $event->holder_display_name }}@endif</small></div>@empty—@endforelse</td>
            </tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-4">No access items.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header">Authorised access periods</div>
    <div class="card-body border-bottom">
        <x-form method="POST" :action="route('managedpremises.properties.access.authorisations.store', $property->id)">
            @csrf
            <div class="row">
                <div class="col-md-3 form-group"><label>Party</label><select class="form-control" name="party_role_id"><option value="">Other person / organisation</option>@foreach($partyRoles as $party)<option value="{{ $party->id }}">{{ $party->display_name }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><label>Other subject</label><input class="form-control" name="subject_name"></div>
                <div class="col-md-2 form-group"><label>Scope</label><select class="form-control" name="access_scope">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAccessAuthorisation::SCOPES as $scope)<option value="{{ $scope }}">{{ str($scope)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><label>Space</label><select class="form-control" name="space_id"><option value="">Premise-wide</option>@foreach($spaces as $space)<option value="{{ $space->id }}">{{ $space->name }}</option>@endforeach</select></div>
                <div class="col-md-1 form-group"><label>Starts</label><input class="form-control" type="datetime-local" name="starts_at"></div>
                <div class="col-md-1 form-group"><label>Ends</label><input class="form-control" type="datetime-local" name="ends_at"></div>
                <div class="col-md-1 form-group"><label>Purpose</label><input class="form-control" name="purpose"></div>
            </div>
            <button class="btn btn-primary">Authorise access</button>
        </x-form>
    </div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Subject</th><th>Scope</th><th>Period</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($authorisations as $auth)
        <tr><td>{{ $auth->subject_name }}</td><td>{{ str($auth->access_scope)->replace('_',' ')->title() }} @if($auth->space)· {{ $auth->space->name }}@endif</td><td>{{ optional($auth->starts_at)->format('d M Y H:i') ?: 'Immediately' }} — {{ optional($auth->ends_at)->format('d M Y H:i') ?: 'Open' }}</td><td>{{ str($auth->status)->title() }}</td><td>@if($auth->status === 'active')<x-form method="POST" :action="route('managedpremises.properties.access.authorisations.revoke', [$property->id, $auth->id])">@csrf<button class="btn btn-sm btn-outline-danger">Revoke</button></x-form>@endif</td></tr>
        @empty<tr><td colspan="5" class="text-center text-muted py-3">No access authorisations.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection
