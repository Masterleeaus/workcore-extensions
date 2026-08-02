@extends('layouts.app')
@section('content')
<div class="container">
  <h3>{{ __('managedpremises::app.documents') }}</h3>
  <a class="btn btn-primary" href="{{ route('managedpremises.documents.create', $property) }}">{{ __('managedpremises::app.add') }}</a>
  <div class="card mt-3"><div class="card-body">
    <table class="table">
      <thead><tr><th>{{ __('managedpremises::app.name') }}</th><th>{{ __('managedpremises::app.type') }}</th><th></th></tr></thead>
      <tbody>
      @foreach($docs as $d)
        <tr>
          <td>{{ $d->name }}</td>
          <td>{{ $d->doc_type }}</td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('managedpremises.documents.download', [$property,$d]) }}">{{ __('managedpremises::app.download') }}</a>
            <form class="d-inline" method="POST" action="{{ route('managedpremises.documents.destroy', [$property,$d]) }}">
              @csrf @method('DELETE')
              <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">{{ __('managedpremises::app.delete') }}</button>
            </form>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
    {{ $docs->links() }}
  </div></div>
</div>
@endsection
