@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $action = $isEdit ? route('managedpremises.properties.update', $property->id) : route('managedpremises.properties.store');
@endphp

<x-form id="property-form" method="POST" :action="$action">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="row">
        <div class="col-md-6">
            <x-forms.text fieldId="name" fieldName="name" :fieldLabel="__('managedpremises::app.labels.name')" :fieldValue="$property->name" />
        </div>
        <div class="col-md-3">
            <x-forms.text fieldId="property_code" fieldName="property_code" :fieldLabel="__('managedpremises::app.labels.code')" :fieldValue="$property->property_code" />
        </div>
        <div class="col-md-3">
            <x-forms.select fieldId="profile_key" fieldName="profile_key" :fieldLabel="__('managedpremises::app.labels.profile')">
                @foreach (($premisesProfiles ?? []) as $profileKey => $profileDefinition)
                    <option value="{{ $profileKey }}" @selected(($property->profile_key ?? 'service_site') === $profileKey)>
                        {{ $profileDefinition['label'] ?? $profileKey }}
                    </option>
                @endforeach
            </x-forms.select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <x-forms.select fieldId="type" fieldName="type" :fieldLabel="__('managedpremises::app.labels.type')">
                @foreach (($premiseTypes ?? \App\Domains\WorkCore\System\Modules\Premises\Entities\Property::PROPERTY_TYPES) as $typeKey => $typeLabel)
                    <option value="{{ $typeKey }}" @selected(($property->type ?? 'house') === $typeKey)>{{ $typeLabel }}</option>
                @endforeach
            </x-forms.select>
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <p class="text-muted mb-3">@lang('managedpremises::app.labels.profileHelp')</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <x-forms.text fieldId="address_line1" fieldName="address_line1" :fieldLabel="__('managedpremises::app.labels.address')" :fieldValue="$property->address_line1" />
        </div>
        <div class="col-md-4">
            <x-forms.text fieldId="suburb" fieldName="suburb" fieldLabel="Suburb" :fieldValue="$property->suburb" />
        </div>
        <div class="col-md-4">
            <x-forms.text fieldId="state" fieldName="state" fieldLabel="State" :fieldValue="$property->state" />
        </div>
        <div class="col-md-4">
            <x-forms.text fieldId="postcode" fieldName="postcode" fieldLabel="Postcode" :fieldValue="$property->postcode" />
        </div>
        <div class="col-md-4">
            <x-forms.text fieldId="country" fieldName="country" fieldLabel="Country" :fieldValue="$property->country" />
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <x-forms.textarea fieldId="access_notes" fieldName="access_notes" :fieldLabel="__('managedpremises::app.labels.accessNotes')" :fieldValue="$property->access_notes" />
        </div>
        <div class="col-md-6">
            <x-forms.textarea fieldId="hazards" fieldName="hazards" :fieldLabel="__('managedpremises::app.labels.hazards')" :fieldValue="$property->hazards" />
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <x-forms.text fieldId="lockbox_code" fieldName="lockbox_code" fieldLabel="Lockbox code" :fieldValue="$property->lockbox_code" />
        </div>
        <div class="col-md-4">
            <x-forms.text fieldId="keys_location" fieldName="keys_location" fieldLabel="Keys location" :fieldValue="$property->keys_location" />
        </div>
        <div class="col-md-4">
            <x-forms.select fieldId="status" fieldName="status" :fieldLabel="__('managedpremises::app.labels.status')">
                @php($status = $property->status ?? 'active')
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                <option value="archived" @selected($status === 'archived')>Archived</option>
            </x-forms.select>
        </div>
    </div>

    <h5 class="mt-3 mb-2">@lang('managedpremises::app.labels.primaryContact')</h5>
    <div class="row">
        <div class="col-md-4">
            <x-forms.text fieldId="primary_contact_name" fieldName="primary_contact_name" fieldLabel="Name" :fieldValue="$property->primary_contact_name" />
        </div>
        <div class="col-md-4">
            <x-forms.text fieldId="primary_contact_phone" fieldName="primary_contact_phone" fieldLabel="Phone" :fieldValue="$property->primary_contact_phone" />
        </div>
        <div class="col-md-4">
            <x-forms.text fieldId="primary_contact_email" fieldName="primary_contact_email" fieldLabel="Email" :fieldValue="$property->primary_contact_email" />
        </div>
    </div>

    <x-forms.button-primary id="save-property" class="mt-3" icon="check">@lang('app.save')</x-forms.button-primary>
</x-form>
