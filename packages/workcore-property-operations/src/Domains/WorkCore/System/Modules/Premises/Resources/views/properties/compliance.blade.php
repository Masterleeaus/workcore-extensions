@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Compliance register — {{ $property->name }}</h4>
        <div class="text-muted">Requirements, due dates, recurring occurrences, evidence, provenance, and review status.</div>
    </div>
    <div>
        <a class="btn btn-outline-primary" href="{{ route('managedpremises.compliance.rule-packs.index') }}">Rule packs</a>
        <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a>
    </div>
</div>

<div class="alert alert-warning">
    <strong>Verification boundary:</strong> this register tracks operational obligations and evidence. A template or completed item does not prove legal compliance. Sources, dates, applicability, and interpretations must be checked by the responsible organisation or a qualified adviser.
</div>

<div class="row mb-3">
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Active requirements</small><h3>{{ $summary['active'] }}</h3></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Due</small><h3>{{ $summary['due'] }}</h3></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Overdue</small><h3>{{ $summary['overdue'] }}</h3></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Missing evidence</small><h3>{{ $summary['missing_evidence'] }}</h3></div></div></div>
</div>

<div class="row">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Apply installed rule pack</div>
            <div class="card-body">
                <x-form method="POST" :action="route('managedpremises.properties.compliance.refresh', $property->id)" class="mb-3">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm">Refresh due states</button>
                </x-form>
                @forelse($rulePacks as $pack)
                    <div class="border rounded p-2 mb-2">
                        <strong>{{ $pack->name }}</strong>
                        <div><small>{{ $pack->jurisdiction_country ?: 'Generic' }} {{ $pack->jurisdiction_region }} · v{{ $pack->version }} · {{ str($pack->verification_status)->headline() }}</small></div>
                        <x-form method="POST" :action="route('managedpremises.properties.compliance.rule-packs.apply', [$property->id, $pack->id])" class="mt-2">
                            @csrf
                            @if(!$pack->isVerified())
                                <label class="small"><input type="checkbox" name="acknowledge_unverified" value="1" required> I understand this pack is unverified for this premise.</label>
                            @endif
                            <button class="btn btn-sm btn-outline-primary">Apply pack</button>
                        </x-form>
                    </div>
                @empty
                    <p class="text-muted mb-0">No compatible rule packs are installed.</p>
                @endforelse
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Create requirement</div>
            <div class="card-body">
                <x-form method="POST" :action="route('managedpremises.properties.compliance.requirements.store', $property->id)">
                    @csrf
                    <div class="form-group"><label>Title</label><input class="form-control" name="title" required></div>
                    <div class="form-group"><label>Type</label><select class="form-control" name="requirement_type">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseComplianceRequirement::TYPES as $type)<option value="{{ $type }}">{{ str($type)->headline() }}</option>@endforeach</select></div>
                    <div class="row"><div class="col-md-6 form-group"><label>Severity</label><select class="form-control" name="severity">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseComplianceRequirement::SEVERITIES as $severity)<option value="{{ $severity }}">{{ ucfirst($severity) }}</option>@endforeach</select></div><div class="col-md-6 form-group"><label>Status</label><select class="form-control" name="status"><option value="active">Active</option><option value="paused">Paused</option></select></div></div>
                    <div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
                    <div class="row"><div class="col-md-6 form-group"><label>Space</label><select class="form-control" name="space_id"><option value="">Premise-wide</option>@foreach($spaces as $space)<option value="{{ $space->id }}">{{ $space->name }}</option>@endforeach</select></div><div class="col-md-6 form-group"><label>Responsible party</label><select class="form-control" name="responsible_party_role_id"><option value="">Unassigned</option>@foreach($partyRoles as $party)<option value="{{ $party->id }}">{{ $party->display_name }}</option>@endforeach</select></div></div>
                    <hr>
                    <div class="row"><div class="col-md-4 form-group"><label>Country</label><input class="form-control" name="jurisdiction_country" placeholder="AU"></div><div class="col-md-4 form-group"><label>Region</label><input class="form-control" name="jurisdiction_region" placeholder="VIC"></div><div class="col-md-4 form-group"><label>Locality</label><input class="form-control" name="jurisdiction_locality"></div></div>
                    <div class="form-group"><label>Authority / source title</label><input class="form-control" name="source_title"></div>
                    <div class="form-group"><label>Source URL</label><input class="form-control" type="url" name="source_url"></div>
                    <div class="row"><div class="col-md-6 form-group"><label>Legal/reference identifier</label><input class="form-control" name="legal_reference"></div><div class="col-md-6 form-group"><label>Source status</label><select class="form-control" name="source_verification_status"><option value="unverified">Unverified</option><option value="reviewed">Reviewed</option><option value="verified">Verified</option><option value="rejected">Rejected</option></select></div></div>
                    <hr>
                    <div class="row"><div class="col-md-6 form-group"><label>Initial due</label><input class="form-control" type="datetime-local" name="initial_due_at"></div><div class="col-md-6 form-group"><label>Recurrence</label><select class="form-control" name="recurrence_type"><option value="none">One-off</option><option value="days">Days</option><option value="months">Months</option><option value="years">Years</option><option value="annual">Annual fixed date</option></select></div></div>
                    <div class="row"><div class="col-md-4 form-group"><label>Interval</label><input class="form-control" type="number" min="1" name="recurrence_interval"></div><div class="col-md-4 form-group"><label>Annual month</label><input class="form-control" type="number" min="1" max="12" name="annual_month"></div><div class="col-md-4 form-group"><label>Annual day</label><input class="form-control" type="number" min="1" max="31" name="annual_day"></div></div>
                    <div class="row"><div class="col-md-6 form-group"><label>Warning days</label><input class="form-control" type="number" min="0" name="warning_days" value="30" required></div><div class="col-md-6 form-group"><label>Grace days</label><input class="form-control" type="number" min="0" name="grace_days" value="0" required></div></div>
                    <div class="form-group"><label><input type="checkbox" name="evidence_required" value="1" checked> Evidence required</label></div>
                    <div class="form-group"><label>Accepted evidence types</label><input class="form-control" name="evidence_types" placeholder="certificate, service_report, photo"></div>
                    <button class="btn btn-primary">Create requirement</button>
                </x-form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        @forelse($requirements as $requirement)
            @php($current = $requirement->occurrences->first(fn($item) => in_array($item->status, ['pending','due','overdue'], true)))
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <div><strong>{{ $requirement->title }}</strong><br><small>{{ str($requirement->requirement_type)->headline() }} · {{ ucfirst($requirement->severity) }} · {{ optional($requirement->space)->name ?: 'Premise-wide' }}</small></div>
                    <div class="text-right"><span class="badge badge-{{ $requirement->status === 'active' ? 'success' : ($requirement->status === 'paused' ? 'warning' : 'secondary') }}">{{ ucfirst($requirement->status) }}</span>@if($current)<br><span class="badge badge-{{ $current->status === 'overdue' ? 'danger' : ($current->status === 'due' ? 'warning' : 'secondary') }}">{{ ucfirst($current->status) }} {{ optional($current->due_at)->format('d M Y') }}</span>@endif</div>
                </div>
                <div class="card-body">
                    @if($requirement->description)<p>{{ $requirement->description }}</p>@endif
                    <div class="row small mb-3">
                        <div class="col-md-6"><strong>Source:</strong> {{ $requirement->source_title ?: 'Not recorded' }}<br><strong>Verification:</strong> {{ str($requirement->source_verification_status)->headline() }}@if($requirement->source_url)<br><a href="{{ $requirement->source_url }}" target="_blank" rel="noopener">Open source</a>@endif</div>
                        <div class="col-md-6"><strong>Recurrence:</strong> {{ str($requirement->recurrence_type)->headline() }}@if($requirement->recurrence_interval) every {{ $requirement->recurrence_interval }}@endif<br><strong>Responsible:</strong> {{ optional($requirement->responsiblePartyRole)->display_name ?: 'Unassigned' }}<br><strong>Pack:</strong> {{ optional($requirement->rulePack)->name ?: 'Manual' }}</div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <h6>Requirement state</h6>
                            <x-form method="POST" :action="route('managedpremises.properties.compliance.requirements.transition', [$property->id, $requirement->id])">
                                @csrf
                                <div class="input-group"><select class="form-control" name="status">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Domain\Compliance\ComplianceRequirementState::allowedTransitions($requirement->status) as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select><div class="input-group-append"><button class="btn btn-outline-secondary" @disabled(empty(\App\Domains\WorkCore\System\Modules\Premises\Domain\Compliance\ComplianceRequirementState::allowedTransitions($requirement->status)))>Apply</button></div></div>
                            </x-form>
                        </div>
                        <div class="col-md-8">
                            @if($current)
                                <h6>Current occurrence</h6>
                                <div class="row">
                                    <div class="col-md-7">
                                        <x-form method="POST" :action="route('managedpremises.properties.compliance.occurrences.complete', [$property->id, $current->id])">
                                            @csrf
                                            <select class="form-control form-control-sm mb-1" name="evidence_id"><option value="">Use latest eligible evidence</option>@foreach($requirement->evidence->whereIn('status', ['submitted','verified']) as $evidence)<option value="{{ $evidence->id }}">#{{ $evidence->id }} {{ $evidence->title }} — {{ ucfirst($evidence->status) }}</option>@endforeach</select>
                                            <input class="form-control form-control-sm mb-1" name="notes" placeholder="Completion note">
                                            <button class="btn btn-sm btn-success">Complete occurrence</button>
                                        </x-form>
                                    </div>
                                    <div class="col-md-5">
                                        <x-form method="POST" :action="route('managedpremises.properties.compliance.occurrences.waive', [$property->id, $current->id])">
                                            @csrf
                                            <input class="form-control form-control-sm mb-1" name="waiver_reason" required placeholder="Approved waiver reason">
                                            <button class="btn btn-sm btn-outline-warning">Waive</button>
                                        </x-form>
                                    </div>
                                </div>
                            @else
                                <p class="text-muted">No open occurrence.</p>
                            @endif
                        </div>
                    </div>
                    <hr>
                    <h6>Add evidence</h6>
                    <x-form method="POST" :action="route('managedpremises.properties.compliance.requirements.evidence.store', [$property->id, $requirement->id])">
                        @csrf
                        <div class="row"><div class="col-md-2"><input class="form-control form-control-sm" name="evidence_type" required placeholder="Type"></div><div class="col-md-3"><input class="form-control form-control-sm" name="title" required placeholder="Evidence title"></div><div class="col-md-2"><select class="form-control form-control-sm" name="status"><option value="submitted">Submitted</option><option value="draft">Draft</option></select></div><div class="col-md-3"><select class="form-control form-control-sm" name="document_id"><option value="">No vault document</option>@foreach($documents as $document)<option value="{{ $document->id }}">{{ $document->title ?? $document->name ?? ('Document #'.$document->id) }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-sm btn-outline-primary">Add</button></div></div>
                        @if($current)<input type="hidden" name="occurrence_id" value="{{ $current->id }}">@endif
                    </x-form>

                    @if($requirement->evidence->isNotEmpty())
                        <div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><th>Evidence</th><th>Status</th><th>Dates</th><th>Review</th></tr></thead><tbody>
                        @foreach($requirement->evidence as $evidence)
                            <tr><td>#{{ $evidence->id }} <strong>{{ $evidence->title }}</strong><br><small>{{ $evidence->evidence_type }} @if($evidence->document)· Vault document #{{ $evidence->document_id }}@endif</small></td><td>{{ ucfirst($evidence->status) }}</td><td>{{ optional($evidence->issued_on)->format('d M Y') ?: '—' }} / {{ optional($evidence->expires_on)->format('d M Y') ?: 'No expiry' }}</td><td>@if(!in_array($evidence->status, ['superseded'], true))<x-form method="POST" :action="route('managedpremises.properties.compliance.evidence.verify', [$property->id, $evidence->id])">@csrf<div class="input-group input-group-sm"><select class="form-control" name="status"><option value="verified">Verify</option><option value="rejected">Reject</option></select><input class="form-control" name="rejection_reason" placeholder="Reason if rejected"><div class="input-group-append"><button class="btn btn-outline-secondary">Review</button></div></div></x-form>@endif</td></tr>
                        @endforeach
                        </tbody></table></div>
                    @endif
                </div>
            </div>
        @empty
            <div class="card"><div class="card-body text-center text-muted">No compliance requirements.</div></div>
        @endforelse
    </div>
</div>
@endsection
