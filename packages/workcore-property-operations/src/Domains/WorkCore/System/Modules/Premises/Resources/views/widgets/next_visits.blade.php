<div class="card">
    <div class="card-header">{{ __('managedpremises::pm.next_visits') }}</div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th>{{ __('managedpremises::pm.when') }}</th><th>{{ __('managedpremises::pm.property') }}</th><th>{{ __('managedpremises::pm.status') }}</th></tr></thead>
            <tbody>
            @forelse($pm_next_visits as $v)
                <tr>
                    <td>{{ optional($v->scheduled_for)->format('d M Y H:i') }}</td>
                    <td><a href="{{ route('managedpremises.properties.show', $v->property_id) }}">{{ $v->property?->name ?? ('#'.$v->property_id) }}</a></td>
                    <td><span class="badge bg-secondary">{{ $v->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted p-4">{{ __('managedpremises::pm.none') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
