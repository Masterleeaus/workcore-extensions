@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Contacts — {{ $property->name ?? ('Property #' . $property->id) }}</h4>
        <a class="btn btn-outline-secondary" href="{{ route('managedpremises.properties.show', $property->id) }}">Back</a>
    </div>

    <div class="card mb-3">
        <div class="card-header">Add contact</div>
        <div class="card-body">
            <x-form method="POST" :action="route('managedpremises.properties.contacts.store', $property->id)">
                @csrf
                <div class="row">
                    <div class="col-md-3">
                        <x-forms.select fieldId="role" fieldName="role" fieldLabel="Role">
                            <option value="agent">Agent</option>
                            <option value="owner">Owner</option>
                            <option value="tenant">Tenant</option>
                            <option value="cleaner">Cleaner</option>
                            <option value="tradie">Tradie</option>
                            <option value="emergency">Emergency</option>
                            <option value="contact" selected>Other</option>
                        </x-forms.select>
                    </div>
                    <div class="col-md-3">
                        <x-forms.text fieldId="name" fieldName="name" fieldLabel="Name" />
                    </div>
                    <div class="col-md-2">
                        <x-forms.text fieldId="phone" fieldName="phone" fieldLabel="Phone" />
                    </div>
                    <div class="col-md-2">
                        <x-forms.text fieldId="email" fieldName="email" fieldLabel="Email" />
                    </div>
                    <div class="col-md-2">
                        <x-forms.text fieldId="company" fieldName="company" fieldLabel="Company" />
                    </div>
                    <div class="col-12 mt-2">
                        <x-forms.textarea fieldId="notes" fieldName="notes" fieldLabel="Notes" />
                    </div>
                </div>
                <x-forms.button-primary class="mt-2" icon="check">@lang('app.save')</x-forms.button-primary>
            </x-form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Contacts</div>
        <div class="card-body table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Role</th><th>Name</th><th>Phone</th><th>Email</th><th class="text-right">@lang('app.action')</th></tr></thead>
                <tbody>
                @forelse($property->contacts as $contact)
                    <tr>
                        <td>{{ ucfirst($contact->role) }}</td>
                        <td>{{ $contact->name }}</td>
                        <td>{{ $contact->phone }}</td>
                        <td>{{ $contact->email }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('managedpremises.properties.contacts.destroy', [$property->id, $contact->id]) }}" onsubmit="return confirm('@lang('app.areYouSure')')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">@lang('app.delete')</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">@lang('app.noRecordFound')</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
