@extends('layouts.app')
@section('content')
<div class="content-wrapper">
  <div class="d-flex justify-content-between mb-3">
    <h4>@lang('managedpremises::app.meter_readings') - {{ $property->name }}</h4>
    <a href="{{ route('managedpremises.properties.show', $property->id) }}" class="btn btn-secondary btn-sm">@lang('app.back')</a>
  </div>

  @include('managedpremises::partials.property-tabs', ['property'=>$property])

  @if(isset($insights))
    <div class="row mb-3">
      <div class="col-md-6 mb-3">
        <x-card>
          <x-slot name="header">@lang('managedpremises::app.last_30_days')</x-slot>

          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>@lang('managedpremises::app.meter_type')</th>
                  <th class="text-right">@lang('managedpremises::app.consumed')</th>
                  <th class="text-right">@lang('managedpremises::app.amount')</th>
                </tr>
              </thead>
              <tbody>
              @forelse(($insights['totals30d'] ?? []) as $row)
                <tr>
                  <td>{{ ucfirst($row['meter_type'] ?? '') }}</td>
                  <td class="text-right">{{ $row['consumed'] ?? 0 }}</td>
                  <td class="text-right">
                    @if(isset($row['amount']) && $row['amount'] !== null)
                      {{ number_format($row['amount'], 2) }}
                    @else
                      <span class="text-muted">--</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center text-muted">@lang('app.noRecordFound')</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>
        </x-card>
      </div>

      <div class="col-md-6 mb-3">
        <x-card>
          <x-slot name="header">@lang('managedpremises::app.usage_anomalies')</x-slot>

          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead>
                <tr>
                  <th>@lang('managedpremises::app.meter_type')</th>
                  <th>@lang('managedpremises::app.unit')</th>
                  <th class="text-right">@lang('managedpremises::app.pct_change')</th>
                </tr>
              </thead>
              <tbody>
              @forelse(($insights['anomalies'] ?? []) as $a)
                <tr>
                  <td>{{ ucfirst($a['meter_type'] ?? '') }}</td>
                  <td>
                    @if(!empty($a['unit_id']))
                      #{{ $a['unit_id'] }}
                    @else
                      <span class="text-muted">--</span>
                    @endif
                  </td>
                  <td class="text-right">
                    <span class="{{ ($a['pct_change'] ?? 0) >= 0 ? 'text-danger' : 'text-warning' }}">
                      {{ $a['pct_change'] ?? 0 }}%
                    </span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="3" class="text-center text-muted">@lang('managedpremises::app.no_anomalies')</td></tr>
              @endforelse
              </tbody>
            </table>
          </div>

          @php
            $tzContext = [
              'module' => 'managedpremises',
              'page' => 'property.utilities',
              'property_id' => (int) $property->id,
              'property_name' => (string) ($property->name ?? ''),
              'meter_readings_recent' => [],
              'meter_anomalies' => $insights['anomalies'] ?? [],
            ];

            if (!empty($insights['recent'])) {
              foreach($insights['recent']->take(20) as $r) {
                $tzContext['meter_readings_recent'][] = [
                  'id' => (int) $r->id,
                  'meter_type' => (string) $r->meter_type,
                  'unit_id' => $r->unit_id ? (int) $r->unit_id : null,
                  'reading_date' => (string) $r->reading_date,
                  'current' => (float) ($r->current_reading ?? 0),
                  'previous' => $r->previous_reading !== null ? (float) $r->previous_reading : null,
                  'consumed' => (float) ($r->consumed ?? 0),
                  'rate' => $r->rate !== null ? (float) $r->rate : null,
                  'amount' => $r->amount !== null ? (float) $r->amount : null,
                ];
              }
            }
          @endphp

          @includeIf('titanzero::partials.ask-titan', [
            'context' => $tzContext,
            'suggestions' => [
              [
                'label' => __('managedpremises::app.titan_summarise_anomalies'),
                'intent' => 'property.utilities.anomalies',
                'risk' => 'low',
              ],
              [
                'label' => __('managedpremises::app.titan_reduce_usage'),
                'intent' => 'property.utilities.recommendations',
                'risk' => 'low',
              ],
            ],
          ])
        </x-card>
      </div>
    </div>
  @endif


  <x-card>
    <x-slot name="header">@lang('managedpremises::app.new_reading')</x-slot>

    <form id="pmMeterForm" method="POST" action="{{ route('managedpremises.properties.meters.store', $property->id) }}">
      @csrf
      <div class="row">
        <div class="col-md-3 mb-3">
          <label class="form-label">@lang('managedpremises::app.meter_type')</label>
          <select name="meter_type" class="form-control">
            <option value="water">@lang('managedpremises::app.water')</option>
            <option value="electric">@lang('managedpremises::app.electric')</option>
            <option value="gas">@lang('managedpremises::app.gas')</option>
            <option value="other">@lang('managedpremises::app.other')</option>
          </select>
        </div>

        <div class="col-md-3 mb-3">
          <label class="form-label">@lang('managedpremises::app.reading_date')</label>
          <input type="date" name="reading_date" value="{{ now()->toDateString() }}" class="form-control" required>
        </div>

        <div class="col-md-3 mb-3">
          <label class="form-label">@lang('managedpremises::app.current_reading')</label>
          <input type="number" step="0.01" min="0" name="current_reading" class="form-control" required>
        </div>

        <div class="col-md-3 mb-3">
          <label class="form-label">@lang('managedpremises::app.previous_reading')</label>
          <input type="number" step="0.01" min="0" name="previous_reading" class="form-control">
        </div>

        <div class="col-md-3 mb-3">
          <label class="form-label">@lang('managedpremises::app.rate')</label>
          <input type="number" step="0.0001" min="0" name="rate" class="form-control">
        </div>

        <div class="col-md-3 mb-3">
          <label class="form-label">@lang('managedpremises::app.units')</label>
          <select name="unit_id" class="form-control">
            <option value="">@lang('app.all')</option>
            @foreach($units as $unit)
              <option value="{{ $unit->id }}">{{ $unit->label ?? ('Unit #' . $unit->id) }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-6 mb-3">
          <label class="form-label">@lang('managedpremises::app.notes')</label>
          <input type="text" name="notes" class="form-control">
        </div>
      </div>

      <button type="submit" class="btn btn-primary">@lang('app.save')</button>
    </form>
  </x-card>

  <x-card class="mt-3">
    <x-slot name="header">@lang('managedpremises::app.meter_readings')</x-slot>
    <div class="table-responsive">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>@lang('managedpremises::app.reading_date')</th>
            <th>@lang('managedpremises::app.meter_type')</th>
            <th>@lang('managedpremises::app.current_reading')</th>
            <th>@lang('managedpremises::app.previous_reading')</th>
            <th>@lang('managedpremises::app.consumed')</th>
            <th>@lang('managedpremises::app.rate')</th>
            <th>@lang('managedpremises::app.amount')</th>
            <th>@lang('app.action')</th>
          </tr>
        </thead>
        <tbody>
        @forelse($readings as $r)
          <tr>
            <td>{{ optional($r->reading_date)->format('Y-m-d') }}</td>
            <td>{{ ucfirst($r->meter_type) }}</td>
            <td>{{ $r->current_reading }}</td>
            <td>{{ $r->previous_reading }}</td>
            <td>{{ $r->consumed }}</td>
            <td>{{ $r->rate }}</td>
            <td>{{ $r->amount }}</td>
            <td>
              <form method="POST" action="{{ route('managedpremises.properties.meters.destroy', [$property->id, $r->id]) }}" onsubmit="return confirm('@lang('app.confirmation')');">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-danger" type="submit">@lang('app.delete')</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted">@lang('app.noRecordFound')</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
</div>
@endsection
