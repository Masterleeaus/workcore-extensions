@extends('layouts.app')
@section('content')
<div class="content-wrapper">
  <h4 class="mb-3">@lang('managedpremises::app.overview') - {{ $property->name }}</h4>
  @include('managedpremises::partials.property-tabs', ['property'=>$property])
  @include('managedpremises::widgets.property-profile', ['property'=>$property])
  <a class="btn btn-sm btn-primary mt-2" href="{{ route('managedpremises.properties.operations.index', $property->id) }}">Open operations dashboard</a>

  <div class="row mt-3">
    <div class="col-md-4">
      <x-card>
        <x-slot name="header">Compliance Overview</x-slot>
        <x-slot name="body">
          <div class="mb-1">Active requirements: <strong>{{ $compliance['active_requirements'] ?? 0 }}</strong></div>
          <div class="mb-1">Due: <strong>{{ $compliance['due_requirements'] ?? 0 }}</strong></div>
          <div class="mb-1">Overdue: <strong>{{ $compliance['overdue_requirements'] ?? 0 }}</strong></div>
          <div class="mb-1">Missing evidence: <strong>{{ $compliance['missing_evidence'] ?? 0 }}</strong></div>
          <div class="mb-1">Open hazards: <strong>{{ $compliance['open_hazards'] ?? 0 }}</strong></div>
          <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('managedpremises.properties.compliance.index', $property->id) }}">Open compliance register</a>
          <div class="small text-muted mt-2">Register status does not itself prove legal compliance.</div>
        </x-slot>
      </x-card>
    </div>
    <div class="col-md-4">
      <x-card>
        <x-slot name="header">Service Readiness</x-slot>
        <x-slot name="body">
          <div class="mb-1">Access ready: <strong>{{ !empty($readiness['access_ready']) ? 'Yes' : 'No' }}</strong></div>
          <div class="mb-1">Active windows: <strong>{{ $readiness['active_service_windows'] ?? 0 }}</strong></div>
          <div class="mb-1">Active plans: <strong>{{ $readiness['active_service_plans'] ?? 0 }}</strong></div>
          <div>Keys/access records: <strong>{{ $readiness['keys_count'] ?? 0 }}</strong></div>
          @if(!empty($readiness['blockers']))
            <ul class="mt-2 mb-0 pl-3">
              @foreach($readiness['blockers'] as $blocker)
                <li>{{ $blocker }}</li>
              @endforeach
            </ul>
          @endif
        </x-slot>
      </x-card>
    </div>
    <div class="col-md-4">
      <x-card>
        <x-slot name="header">Premise Timeline</x-slot>
        <x-slot name="body">
          <ul class="mb-0 pl-3">
            @forelse(($timeline ?? collect()) as $item)
              <li>{{ ucfirst($item->type) }} · {{ $item->status ?? '-' }} · {{ $item->happened_at ? \Carbon\Carbon::parse($item->happened_at)->format('d M Y H:i') : '-' }}</li>
            @empty
              <li class="text-muted">No recent activity</li>
            @endforelse
          </ul>
        </x-slot>
      </x-card>
    </div>
  </div>
</div>
@endsection
