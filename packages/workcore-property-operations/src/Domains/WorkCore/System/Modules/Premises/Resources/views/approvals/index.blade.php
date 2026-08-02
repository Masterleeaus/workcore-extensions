@extends('layouts.app')
@section('content')
<div class="container">
  <h3>{{ __('managedpremises::app.approvals') }}</h3>
  <a class="btn btn-primary" href="{{ route('managedpremises.approvals.create', $property) }}">{{ __('managedpremises::app.add') }}</a>
  <div class="card mt-3"><div class="card-body">
    <table class="table">
      <thead><tr><th>{{ __('managedpremises::app.subject') }}</th><th>{{ __('managedpremises::app.status') }}</th><th></th></tr></thead>
      <tbody>
      @foreach($approvals as $a)
        <tr>
          <td>{{ $a->subject }}</td>
          <td>{{ $a->status }}</td>
          <td class="text-end">
            @if($a->status === 'pending')
              <form class="d-inline" method="POST" action="{{ route('managedpremises.approvals.decide', [$property,$a]) }}">
                @csrf
                <input type="hidden" name="decision" value="approved">
                <button class="btn btn-sm btn-outline-success">{{ __('managedpremises::app.approve') }}</button>
              </form>
              <form class="d-inline" method="POST" action="{{ route('managedpremises.approvals.decide', [$property,$a]) }}">
                @csrf
                <input type="hidden" name="decision" value="rejected">
                <button class="btn btn-sm btn-outline-danger">{{ __('managedpremises::app.reject') }}</button>
              </form>
            @endif
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
    {{ $approvals->links() }}
  </div></div>
</div>
@endsection
