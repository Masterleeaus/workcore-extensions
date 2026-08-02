@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Agreement register — {{ $property->name }}</h4>
        <div class="text-muted">Operational agreement lifecycle, parties, documents and external finance references.</div>
    </div>
    <div>
        @if($profile['features']['occupancy'] ?? false)
            <a class="btn btn-outline-primary" href="{{ route('managedpremises.properties.occupancies.index', $property->id) }}">Occupancy</a>
        @endif
        <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a>
    </div>
</div>

<div class="alert alert-info">
    <strong>Finance boundary:</strong> ManagedPremises stores references only. Titan Money or the selected external provider remains authoritative for recurring charges, deposits, bonds, invoices, payments, refunds and arrears.
</div>

<div class="card mb-3">
    <div class="card-header">Create agreement</div>
    <div class="card-body">
        <x-form method="POST" :action="route('managedpremises.properties.agreements.store', $property->id)">
            @csrf
            <div class="row">
                <div class="col-md-3 form-group">
                    <label>Agreement type</label>
                    <select class="form-control" name="agreement_type" required>
                        @foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreement::TYPES as $type)
                            <option value="{{ $type }}" @selected(($profile['agreement_defaults']['type'] ?? 'custom') === $type)>{{ str($type)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label>Initial status</label>
                    <select class="form-control" name="status" required>
                        @foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreement::INITIAL_STATUSES as $status)
                            <option value="{{ $status }}" @selected($status === 'draft')>{{ str($status)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Reference</label>
                    <input class="form-control" name="reference" maxlength="191" placeholder="Internal or legal reference">
                </div>
                <div class="col-md-4 form-group">
                    <label>Title</label>
                    <input class="form-control" name="title" maxlength="191" placeholder="Generated automatically when blank">
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 form-group">
                    <label>Primary party</label>
                    <select class="form-control" name="party_role_id">
                        <option value="">None</option>
                        @foreach($parties as $party)
                            <option value="{{ $party->id }}">{{ $party->display_name }} — {{ str($party->role)->replace('_', ' ') }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group">
                    <label>Agreement role</label>
                    <select class="form-control" name="agreement_role">
                        @foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreementParty::AGREEMENT_ROLES as $role)
                            <option value="{{ $role }}">{{ str($role)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Space</label>
                    <select class="form-control" name="space_id">
                        <option value="">Premise-wide</option>
                        @foreach($spaces as $space)
                            <option value="{{ $space->id }}">{{ optional($space->parent)->name ? optional($space->parent)->name.' / ' : '' }}{{ $space->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label>Occupancy/allocation</label>
                    <select class="form-control" name="occupancy_id">
                        <option value="">Not linked</option>
                        @foreach($occupancies as $occupancy)
                            <option value="{{ $occupancy->id }}">{{ $occupancy->display_name }} — {{ optional($occupancy->space)->name }} ({{ str($occupancy->status)->replace('_', ' ') }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-2 form-group"><label>Offered</label><input class="form-control" type="date" name="offered_at"></div>
                <div class="col-md-2 form-group"><label>Signed</label><input class="form-control" type="date" name="signed_at"></div>
                <div class="col-md-2 form-group"><label>Starts</label><input class="form-control" type="date" name="start_date"></div>
                <div class="col-md-2 form-group"><label>Ends</label><input class="form-control" type="date" name="end_date"></div>
                <div class="col-md-2 form-group">
                    <label>Renewal</label>
                    <select class="form-control" name="renewal_type">
                        @foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreement::RENEWAL_TYPES as $type)
                            <option value="{{ $type }}">{{ str($type)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group"><label>Notice days</label><input class="form-control" type="number" min="0" max="3650" name="notice_period_days"></div>
            </div>

            <div class="row">
                <div class="col-md-3 form-group"><label>Vault document</label><select class="form-control" name="document_id"><option value="">None</option>@foreach($documents as $document)<option value="{{ $document->id }}">{{ $document->name }}</option>@endforeach</select></div>
                <div class="col-md-3 form-group"><label>External document ref.</label><input class="form-control" name="document_reference" maxlength="191"></div>
                <div class="col-md-2 form-group pt-4"><label><input type="checkbox" name="auto_renew" value="1"> Auto-renew</label></div>
                <div class="col-md-4 form-group"><label>Terms summary</label><textarea class="form-control" name="terms_summary" rows="2"></textarea></div>
            </div>
            <button class="btn btn-primary">Create agreement</button>
        </x-form>
    </div>
</div>

@forelse($currentAgreements as $agreement)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>{{ $agreement->title }}</strong>
            @if($agreement->reference)<span class="text-muted">— {{ $agreement->reference }}</span>@endif
        </div>
        <span class="badge badge-info">{{ str($agreement->status)->replace('_', ' ')->title() }}</span>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3"><strong>Type</strong><br>{{ str($agreement->agreement_type)->replace('_', ' ')->title() }}</div>
            <div class="col-md-3"><strong>Space</strong><br>{{ optional($agreement->space)->name ?: 'Premise-wide' }}</div>
            <div class="col-md-3"><strong>Dates</strong><br>{{ optional($agreement->start_date)->format('d M Y') ?: '—' }} to {{ optional($agreement->end_date)->format('d M Y') ?: 'open' }}</div>
            <div class="col-md-3"><strong>Renewal</strong><br>{{ str($agreement->renewal_type)->replace('_', ' ')->title() }} @if($agreement->auto_renew) · automatic @endif</div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-3">
                <h6>Lifecycle</h6>
                <x-form method="POST" :action="route('managedpremises.properties.agreements.transition', [$property->id, $agreement->id])">
                    @csrf
                    <div class="input-group">
                        <select class="form-control" name="status" required>
                            @foreach(\App\Domains\WorkCore\System\Modules\Premises\Domain\Agreements\AgreementState::allowedTransitions($agreement->status) as $status)
                                <option value="{{ $status }}">{{ str($status)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                        <div class="input-group-append"><button class="btn btn-outline-primary" @disabled(empty(\App\Domains\WorkCore\System\Modules\Premises\Domain\Agreements\AgreementState::allowedTransitions($agreement->status)))>Apply</button></div>
                    </div>
                </x-form>

                @if(\App\Domains\WorkCore\System\Modules\Premises\Domain\Agreements\AgreementState::canRenew($agreement->status))
                    <hr>
                    <h6>Create renewal</h6>
                    <x-form method="POST" :action="route('managedpremises.properties.agreements.renew', [$property->id, $agreement->id])">
                        @csrf
                        <div class="form-group"><input class="form-control form-control-sm" name="reference" placeholder="New reference"></div>
                        <div class="form-group"><input class="form-control form-control-sm" type="date" name="start_date"></div>
                        <div class="form-group"><input class="form-control form-control-sm" type="date" name="end_date"></div>
                        <div class="form-group"><select class="form-control form-control-sm" name="document_id"><option value="">No vault document</option>@foreach($documents as $document)<option value="{{ $document->id }}">{{ $document->name }}</option>@endforeach</select></div>
                        <div class="form-group"><select class="form-control form-control-sm" name="status"><option value="draft">Draft</option><option value="offered">Offered</option><option value="pending_signature">Pending signature</option><option value="active">Active</option></select></div>
                        <button class="btn btn-sm btn-outline-secondary">Create renewal</button>
                    </x-form>
                @endif
            </div>

            <div class="col-lg-4 mb-3">
                <h6>Agreement parties</h6>
                @forelse($agreement->parties as $agreementParty)
                    <div class="border rounded p-2 mb-2">
                        <strong>{{ $agreementParty->display_name }}</strong><br>
                        <small>{{ str($agreementParty->agreement_role)->replace('_', ' ')->title() }} · {{ str($agreementParty->signing_status)->replace('_', ' ')->title() }}</small>
                        @if(in_array($agreement->status, ['draft', 'offered', 'pending_signature'], true) && $agreementParty->signing_status !== 'signed')
                            <x-form method="DELETE" :action="route('managedpremises.properties.agreements.parties.destroy', [$property->id, $agreement->id, $agreementParty->id])" class="mt-1">
                                @csrf
                                <button class="btn btn-sm btn-link text-danger p-0">Remove</button>
                            </x-form>
                        @endif
                    </div>
                @empty
                    <div class="text-muted mb-2">No agreement parties recorded.</div>
                @endforelse

                <x-form method="POST" :action="route('managedpremises.properties.agreements.parties.store', [$property->id, $agreement->id])">
                    @csrf
                    <div class="form-group"><select class="form-control form-control-sm" name="party_role_id" required><option value="">Add party</option>@foreach($parties as $party)<option value="{{ $party->id }}">{{ $party->display_name }}</option>@endforeach</select></div>
                    <div class="form-group"><select class="form-control form-control-sm" name="agreement_role">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreementParty::AGREEMENT_ROLES as $role)<option value="{{ $role }}">{{ str($role)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                    <input type="hidden" name="signing_status" value="pending">
                    <button class="btn btn-sm btn-outline-primary">Add party</button>
                </x-form>
            </div>

            <div class="col-lg-4 mb-3">
                <h6>Titan Money / external links</h6>
                @forelse($agreement->financeLinks as $link)
                    <div class="border rounded p-2 mb-2">
                        <strong>{{ str($link->link_type)->replace('_', ' ')->title() }}</strong><br>
                        <small>{{ $link->provider }} · {{ $link->external_reference ?: $link->external_id }} · {{ $link->status }}</small>
                        @if($link->status === 'linked')
                            <x-form method="POST" :action="route('managedpremises.properties.agreements.finance_links.deactivate', [$property->id, $agreement->id, $link->id])" class="mt-1">
                                @csrf
                                <button class="btn btn-sm btn-link text-danger p-0">Deactivate link</button>
                            </x-form>
                        @endif
                    </div>
                @empty
                    <div class="text-muted mb-2">No finance references linked.</div>
                @endforelse

                <x-form method="POST" :action="route('managedpremises.properties.agreements.finance_links.store', [$property->id, $agreement->id])">
                    @csrf
                    <div class="form-group"><select class="form-control form-control-sm" name="provider"><option value="titan_money">Titan Money</option><option value="external">External</option></select></div>
                    <div class="form-group"><select class="form-control form-control-sm" name="link_type">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Domain\Agreements\AgreementFinanceLinkType::TYPES as $type)<option value="{{ $type }}">{{ str($type)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                    <div class="form-group"><input class="form-control form-control-sm" name="external_reference" placeholder="Reference" required></div>
                    <button class="btn btn-sm btn-outline-primary">Link reference</button>
                </x-form>
            </div>
        </div>

        @if($agreement->document || $agreement->document_reference || $agreement->terms_summary)
            <hr>
            @if($agreement->document)<div><strong>Vault document:</strong> <a href="{{ route('managedpremises.properties.documents.download', [$property->id, $agreement->document->id]) }}">{{ $agreement->document->name }}</a></div>@endif
            @if($agreement->document_reference)<div><strong>External document reference:</strong> {{ $agreement->document_reference }}</div>@endif
            @if($agreement->terms_summary)<div class="mt-1"><strong>Terms summary:</strong> {{ $agreement->terms_summary }}</div>@endif
        @endif
    </div>
</div>
@empty
<div class="card mb-3"><div class="card-body text-center text-muted">No current agreements.</div></div>
@endforelse

<div class="card">
    <div class="card-header">Agreement history</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Agreement</th><th>Type</th><th>Status</th><th>Space</th><th>Dates</th><th>Renewal lineage</th></tr></thead>
            <tbody>
            @forelse($history as $agreement)
                <tr>
                    <td>{{ $agreement->title }}<br><small>{{ $agreement->reference ?: 'No reference' }}</small></td>
                    <td>{{ str($agreement->agreement_type)->replace('_', ' ')->title() }}</td>
                    <td>{{ str($agreement->status)->replace('_', ' ')->title() }}</td>
                    <td>{{ optional($agreement->space)->name ?: 'Premise-wide' }}</td>
                    <td>{{ optional($agreement->start_date)->format('d M Y') ?: '—' }}<br><small>to {{ optional($agreement->end_date)->format('d M Y') ?: optional($agreement->terminated_at)->format('d M Y') ?: 'open' }}</small></td>
                    <td>@if($agreement->renewals->isNotEmpty())Renewed by #{{ $agreement->renewals->first()->id }}@elseif($agreement->previousAgreement)Renewal of #{{ $agreement->previousAgreement->id }}@else—@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No completed agreement history.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
