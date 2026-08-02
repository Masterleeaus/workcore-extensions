@extends('layouts.app')
@section('content')
<div class="container">
  <h3>{{ __('managedpremises::app.service_plans') }}</h3>
  <a class="btn btn-primary" href="{{ route('managedpremises.service-plans.create', $property) }}">{{ __('managedpremises::app.add') }}</a>
  <div class="card mt-3"><div class="card-body">
    <table class="table">
      <thead><tr><th>{{ __('managedpremises::app.name') }}</th><th>{{ __('managedpremises::app.type') }}</th><th>{{ __('managedpremises::app.active') }}</th><th></th></tr></thead>
      <tbody>
      @foreach($plans as $p)
        <tr>
          <td>{{ $p->name }}</td>
          <td>{{ $p->service_type }}</td>
          <td>{{ $p->is_active ? __('managedpremises::app.yes') : __('managedpremises::app.no') }}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('managedpremises.service-plans.edit', [$property,$p]) }}">{{ __('managedpremises::app.edit') }}</a>
            <form class="d-inline" method="POST" action="{{ route('managedpremises.service-plans.destroy', [$property,$p]) }}">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">{{ __('managedpremises::app.delete') }}</button>
            </form>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
    {{ $plans->links() }}
  </div></div>
</div>
@endsection
