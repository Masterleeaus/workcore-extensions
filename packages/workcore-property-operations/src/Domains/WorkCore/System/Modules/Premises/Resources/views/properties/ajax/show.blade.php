<div>
    <h4 class="mb-2">{{ $property->name ?? ('Property #' . $property->id) }}</h4>
    <div class="text-muted mb-3">{{ trim(implode(' ', array_filter([$property->address_line1, $property->suburb, $property->state, $property->postcode]))) }}</div>

    <div class="mb-2"><strong>Type:</strong> {{ ucfirst($property->type) }}</div>
    <div class="mb-2"><strong>Status:</strong> {{ ucfirst($property->status) }}</div>

    @if($property->access_notes)
        <div class="mb-2"><strong>Access notes:</strong><br>{!! nl2br(e($property->access_notes)) !!}</div>
    @endif

    @if($property->hazards)
        <div class="mb-2"><strong>Hazards:</strong><br>{!! nl2br(e($property->hazards)) !!}</div>
    @endif
</div>
