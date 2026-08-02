@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Jobs — {{ $property->name ?? ('Property #' . $property->id) }}</h4>
        <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a>
    </div>

    <div class="card mb-3">
        <div class="card-header">Add job / task at this property</div>
        <div class="card-body">
            <x-form method="POST" :action="route('managedpremises.properties.jobs.store', $property->id)">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <x-forms.text fieldId="title" fieldName="title" fieldLabel="Title" />
                    </div>
                    <div class="col-md-3">
                        <x-forms.select fieldId="status" fieldName="status" fieldLabel="Status">
                            <option value="open">Open</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="in_progress">In progress</option>
                            <option value="done">Done</option>
                        </x-forms.select>
                    </div>
                    <div class="col-md-5">
                        <x-forms.text fieldId="scheduled_at" fieldName="scheduled_at" fieldLabel="Scheduled at" fieldPlaceholder="YYYY-MM-DD" />
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <x-forms.textarea fieldId="notes" fieldName="notes" fieldLabel="Notes" />
                    </div>
                </div>
                <x-forms.button-primary class="mt-2" icon="check">@lang('app.save')</x-forms.button-primary>
            </x-form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Jobs</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Scheduled</th>
                            <th class="text-right">@lang('app.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($property->jobs as $job)
                            <tr>
                                <td>{{ $job->title }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $job->status)) }}</td>
                                <td>{{ optional($job->scheduled_at)->format('Y-m-d') }}</td>
                                <td class="text-right">
                                    <x-forms.button-secondary class="btn-sm" onclick="event.preventDefault(); document.getElementById('delete-job-{{ $job->id }}').submit();" icon="trash">
                                        @lang('app.delete')
                                    </x-forms.button-secondary>
                                    <form id="delete-job-{{ $job->id }}" method="POST" action="{{ route('managedpremises.properties.jobs.destroy', [$property->id, $job->id]) }}" style="display:none;">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">@lang('app.noRecordFound')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
