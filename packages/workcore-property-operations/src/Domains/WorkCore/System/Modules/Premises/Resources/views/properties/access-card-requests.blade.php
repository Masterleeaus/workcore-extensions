@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Physical access-card requests — {{ $property->name }}</h4>
        <div class="text-muted">Batch requests, item-level approvals, issue, return and canonical card custody.</div>
    </div>
    <div>
        <a class="btn btn-outline-primary" href="{{ route('managedpremises.properties.access.index', $property->id) }}">Access register</a>
        <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a>
    </div>
</div>

<div class="alert alert-warning">
    Card identifiers are stored on the canonical access item after issue. They are excluded from untrusted AI and public portal context.
</div>

<div class="card mb-3">
    <div class="card-header">Create access-card request</div>
    <div class="card-body">
        <x-form method="POST" :action="route('managedpremises.properties.access-card-requests.store', $property->id)">
            @csrf
            <div class="row">
                <div class="col-md-3 form-group"><label>Requester</label><select class="form-control" name="requester_party_role_id"><option value="">Snapshot requester</option>@foreach($partyRoles as $party)<option value="{{ $party->id }}">{{ $party->display_name }} — {{ str($party->role)->replace('_',' ') }}</option>@endforeach</select></div>
                <div class="col-md-3 form-group"><label>Requested for</label><input class="form-control" name="requested_for_name" maxlength="191"></div>
                <div class="col-md-2 form-group"><label>Contact</label><input class="form-control" name="requested_for_contact" maxlength="191"></div>
                <div class="col-md-2 form-group"><label>Space</label><select class="form-control" name="space_id"><option value="">Premise-wide</option>@foreach($spaces as $space)<option value="{{ $space->id }}">{{ $space->name }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><label>Expected return</label><input class="form-control" type="datetime-local" name="expected_return_at"></div>
            </div>
            <div class="row">
                <div class="col-md-6 form-group"><label>Purpose</label><input class="form-control" name="purpose" maxlength="191" placeholder="Resident card, contractor access, replacement"></div>
                <div class="col-md-6 form-group"><label>External fee/invoice reference</label><input class="form-control" name="fee_reference" maxlength="191" placeholder="Titan Money reference only"></div>
            </div>
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2"><strong>Cards requested</strong><button class="btn btn-sm btn-outline-primary" type="button" id="add-card-request-item">Add another card</button></div>
                <div id="card-request-items">
                    <div class="row card-request-item">
                        <div class="col-md-4 form-group"><label>Card label</label><input class="form-control" name="items[0][label]" required value="Physical access card"></div>
                        <div class="col-md-4 form-group"><label>Holder from parties</label><select class="form-control" name="items[0][holder_party_role_id]"><option value="">Use name</option>@foreach($partyRoles as $party)<option value="{{ $party->id }}">{{ $party->display_name }}</option>@endforeach</select></div>
                        <div class="col-md-4 form-group"><label>Other holder</label><input class="form-control" name="items[0][holder_display_name]" placeholder="Required when no party is selected"></div>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary">Create draft request</button>
        </x-form>
    </div>
</div>

@forelse($requests as $cardRequest)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
        <div><strong>{{ $cardRequest->request_number }}</strong> · {{ $cardRequest->requested_for_name ?: optional($cardRequest->requesterPartyRole)->display_name ?: 'Unspecified requester' }} @if($cardRequest->space)· {{ $cardRequest->space->name }}@endif</div>
        <span class="badge badge-info">{{ str($cardRequest->status)->replace('_',' ')->title() }}</span>
    </div>
    <div class="card-body border-bottom">
        <div class="row">
            <div class="col-md-6"><strong>Purpose:</strong> {{ $cardRequest->purpose ?: '—' }}<br><small>Fee reference: {{ $cardRequest->fee_reference ?: 'None' }}</small></div>
            <div class="col-md-6 text-right">
                @if($cardRequest->status === 'draft')
                <x-form method="POST" :action="route('managedpremises.properties.access-card-requests.transition', [$property->id, $cardRequest->id])">@csrf<input type="hidden" name="status" value="submitted"><button class="btn btn-sm btn-primary">Submit request</button></x-form>
                @elseif($cardRequest->status === 'submitted')
                <div class="d-inline-block"><x-form method="POST" :action="route('managedpremises.properties.access-card-requests.transition', [$property->id, $cardRequest->id])">@csrf<input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-success">Approve request</button></x-form></div>
                <div class="d-inline-block"><x-form method="POST" :action="route('managedpremises.properties.access-card-requests.transition', [$property->id, $cardRequest->id])">@csrf<input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-outline-danger">Reject</button></x-form></div>
                @endif
            </div>
        </div>
    </div>
    <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Card / holder</th><th>Status</th><th>Canonical access item</th><th>Actions</th></tr></thead><tbody>
        @foreach($cardRequest->items as $requestItem)
        <tr>
            <td><strong>{{ $requestItem->label }}</strong><br><small>{{ $requestItem->holder_display_name }}</small></td>
            <td>{{ str($requestItem->status)->replace('_',' ')->title() }}</td>
            <td>@if($requestItem->accessItem){{ $requestItem->accessItem->label }} · {{ $requestItem->accessItem->status }}@else—@endif</td>
            <td>
                @if(in_array($cardRequest->status, ['approved','partially_issued']) && $requestItem->status === 'requested')
                <div class="d-inline-block"><x-form method="POST" :action="route('managedpremises.properties.access-card-requests.items.approve', [$property->id, $cardRequest->id, $requestItem->id])">@csrf<button class="btn btn-sm btn-outline-success">Approve card</button></x-form></div>
                <div class="d-inline-block"><x-form method="POST" :action="route('managedpremises.properties.access-card-requests.items.reject', [$property->id, $cardRequest->id, $requestItem->id])">@csrf<button class="btn btn-sm btn-outline-danger">Reject card</button></x-form></div>
                @elseif($requestItem->status === 'approved')
                <x-form method="POST" :action="route('managedpremises.properties.access-card-requests.items.issue', [$property->id, $cardRequest->id, $requestItem->id])">
                    @csrf
                    <div class="d-flex"><input class="form-control form-control-sm mr-1" name="identifier" required placeholder="Physical card number"><input class="form-control form-control-sm mr-1" type="datetime-local" name="expires_at"><button class="btn btn-sm btn-primary">Issue</button></div>
                </x-form>
                @elseif($requestItem->status === 'issued')
                <x-form method="POST" :action="route('managedpremises.properties.access-card-requests.items.return', [$property->id, $cardRequest->id, $requestItem->id])">@csrf<button class="btn btn-sm btn-outline-primary">Record return</button></x-form>
                @else—@endif
            </td>
        </tr>
        @endforeach
    </tbody></table></div>
</div>
@empty
<div class="text-center text-muted py-5">No physical access-card requests.</div>
@endforelse

<template id="card-request-item-template">
    <div class="row card-request-item border-top pt-2 mt-2">
        <div class="col-md-4 form-group"><label>Card label</label><input class="form-control" name="items[__INDEX__][label]" required value="Physical access card"></div>
        <div class="col-md-4 form-group"><label>Holder from parties</label><select class="form-control" name="items[__INDEX__][holder_party_role_id]"><option value="">Use name</option>@foreach($partyRoles as $party)<option value="{{ $party->id }}">{{ $party->display_name }}</option>@endforeach</select></div>
        <div class="col-md-3 form-group"><label>Other holder</label><input class="form-control" name="items[__INDEX__][holder_display_name]"></div>
        <div class="col-md-1 form-group d-flex align-items-end"><button type="button" class="btn btn-sm btn-outline-danger remove-card-request-item">Remove</button></div>
    </div>
</template>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('card-request-items');
        const template = document.getElementById('card-request-item-template');
        const addButton = document.getElementById('add-card-request-item');
        let index = 1;
        addButton?.addEventListener('click', function () {
            container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index++)));
        });
        container?.addEventListener('click', function (event) {
            if (event.target.classList.contains('remove-card-request-item')) event.target.closest('.card-request-item')?.remove();
        });
    });
</script>
@endsection
