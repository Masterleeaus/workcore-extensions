@extends('layouts.app')
@section('content')
<div class="content-wrapper">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1">Premises portfolios</h4><p class="text-muted mb-0">Group premises for operations and reporting. Portfolios do not own accounting balances.</p></div></div>
    <div class="row">
        <div class="col-lg-4"><x-card><x-slot name="header">Create portfolio</x-slot><x-slot name="body">
            <x-form method="POST" :action="route('managedpremises.portfolios.store')">@csrf
                <div class="form-group"><label>Name</label><input class="form-control" name="name" required maxlength="191"></div>
                <div class="form-group"><label>Code</label><input class="form-control" name="code" maxlength="80"></div>
                <div class="form-group"><label>Parent portfolio</label><select class="form-control" name="parent_portfolio_id"><option value="">—</option>@foreach($portfolios->where('status','active') as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->name }}</option>@endforeach</select></div>
                <div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="4"></textarea></div>
                <button class="btn btn-primary" type="submit">Create portfolio</button>
            </x-form>
        </x-slot></x-card></div>
        <div class="col-lg-8">@forelse($portfolios as $portfolio)<x-card class="mb-3"><x-slot name="header"><div class="d-flex justify-content-between"><span>{{ $portfolio->name }} @if($portfolio->code)<small class="text-muted">{{ $portfolio->code }}</small>@endif</span><span class="badge badge-{{ $portfolio->status==='active'?'success':'secondary' }}">{{ ucfirst($portfolio->status) }}</span></div></x-slot><x-slot name="body">
            @if($portfolio->parent)<div class="small text-muted mb-2">Parent: {{ $portfolio->parent->name }}</div>@endif
            <div class="mb-3">{{ $portfolio->description }}</div>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Premise</th><th>Type</th><th>From</th><th>Until</th><th></th></tr></thead><tbody>
                @forelse($portfolio->memberships as $membership)<tr><td>{{ $membership->premise?->name ?? 'Removed premise' }}</td><td>{{ str($membership->membership_type)->replace('_',' ')->title() }}</td><td>{{ optional($membership->valid_from)->format('d M Y') }}</td><td>{{ optional($membership->valid_until)->format('d M Y') ?: 'Current' }}</td><td>@if(!$membership->valid_until)<x-form method="POST" :action="route('managedpremises.portfolios.memberships.end',[$portfolio->id,$membership->id])">@csrf<button class="btn btn-sm btn-outline-danger" type="submit">End</button></x-form>@endif</td></tr>@empty<tr><td colspan="5" class="text-muted">No premises assigned.</td></tr>@endforelse
            </tbody></table></div>
            @if($portfolio->status==='active')<x-form method="POST" :action="route('managedpremises.portfolios.premises.add',$portfolio->id)">@csrf<div class="form-row"><div class="col-md-6"><select class="form-control" name="premise_id" required><option value="">Add premise…</option>@foreach($premises as $premise)<option value="{{ $premise->id }}">{{ $premise->name }}</option>@endforeach</select></div><div class="col-md-3"><select class="form-control" name="membership_type">@foreach(\App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortfolioMembership::TYPES as $type)<option value="{{ $type }}">{{ str($type)->title() }}</option>@endforeach</select></div><div class="col-md-3"><button class="btn btn-outline-primary btn-block" type="submit">Assign</button></div></div></x-form>@endif
        </x-slot></x-card>@empty<div class="alert alert-info">No portfolios yet.</div>@endforelse</div>
    </div>
</div>
@endsection
