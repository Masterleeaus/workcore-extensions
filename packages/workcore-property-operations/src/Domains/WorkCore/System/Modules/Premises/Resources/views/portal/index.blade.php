@extends('layouts.app')
@section('content')
<div class="content-wrapper">
    <h4>Portal access — {{ $property->name }}</h4>
    <p class="text-muted">Issue revocable, time-limited links for residents, tenants, storage customers and authorised representatives. The full link is shown only once.</p>
    @include('managedpremises::partials.property-tabs', ['property' => $property])

    <div class="row mt-3">
        <div class="col-lg-5">
            <x-card>
                <x-slot name="header">Issue portal link</x-slot>
                <x-slot name="body">
                    <x-form method="POST" :action="route('managedpremises.properties.portal.issue', $property->id)">
                        @csrf
                        <div class="form-group"><label>Label</label><input class="form-control" name="label" required maxlength="191" placeholder="Resident portal — Room 4"></div>
                        <div class="form-group"><label>Portfolio scope</label><select class="form-control" name="portfolio_id"><option value="">Single premise only</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}">{{ $portfolio->name }}</option>@endforeach</select><small class="text-muted">Portfolio access requires an owner or manager party and exposes operational summaries only.</small></div>
                        <div class="form-group"><label>Party</label><select class="form-control" name="party_role_id"><option value="">—</option>@foreach($partyRoles as $role)<option value="{{ $role->id }}">{{ $role->display_name }} — {{ str($role->role)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                        <div class="form-group"><label>Occupancy</label><select class="form-control" name="occupancy_id"><option value="">—</option>@foreach($occupancies as $occupancy)<option value="{{ $occupancy->id }}">{{ $occupancy->display_name }} @if($occupancy->space)— {{ $occupancy->space->name }}@endif</option>@endforeach</select></div>
                        <div class="form-group"><label>Agreement</label><select class="form-control" name="agreement_id"><option value="">—</option>@foreach($agreements as $agreement)<option value="{{ $agreement->id }}">{{ $agreement->title }}</option>@endforeach</select></div>
                        <div class="form-group"><label>Expires</label><input class="form-control" type="datetime-local" name="expires_at"></div>
                        <div class="form-group"><label>Capabilities</label>
                            @foreach($capabilities as $capability)
                                <label class="d-block"><input type="checkbox" name="capabilities[]" value="{{ $capability }}" @checked(in_array($capability, config('managedpremises_portal.default_capabilities', []), true))> {{ str($capability)->replace('_',' ')->title() }}</label>
                            @endforeach
                        </div>
                        <button class="btn btn-primary" type="submit">Issue secure link</button>
                    </x-form>
                    <div class="small text-muted mt-3">Portal links never expose access credentials, internal finance identifiers, restricted incidents or personal contact data.</div>
                </x-slot>
            </x-card>
        </div>
        <div class="col-lg-7">
            <x-card>
                <x-slot name="header">Portal grants</x-slot>
                <x-slot name="body">
                    <div class="table-responsive"><table class="table table-sm">
                        <thead><tr><th>Label</th><th>Status</th><th>Token</th><th>Expires</th><th>Last access</th><th></th></tr></thead>
                        <tbody>@forelse($grants as $grant)
                            <tr><td>{{ $grant->label }}<div class="small text-muted">{{ implode(', ', $grant->capabilities ?? []) }}</div></td><td>{{ ucfirst($grant->status) }}</td><td>…{{ $grant->token_hint }}</td><td>{{ optional($grant->expires_at)->format('d M Y H:i') ?: '—' }}</td><td>{{ optional($grant->last_accessed_at)->format('d M Y H:i') ?: 'Never' }}</td><td>@if(in_array($grant->status, ['invited','active'], true))<x-form method="POST" :action="route('managedpremises.properties.portal.revoke', [$property->id, $grant->id])">@csrf<button class="btn btn-sm btn-outline-danger" type="submit">Revoke</button></x-form>@endif</td></tr>
                        @empty<tr><td colspan="6" class="text-muted">No portal grants.</td></tr>@endforelse</tbody>
                    </table></div>
                </x-slot>
            </x-card>
        </div>
    </div>
</div>
@endsection
