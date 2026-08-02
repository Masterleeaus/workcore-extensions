<div class="card">
    <div class="card-header">{{ __('managedpremises::pm.overdue_inspections') }}</div>
    <div class="card-body p-0">
        <table class="table mb-0">
            <thead><tr><th>{{ __('managedpremises::pm.when') }}</th><th>{{ __('managedpremises::pm.property') }}</th><th>{{ __('managedpremises::pm.status') }}</th></tr></thead>
            <tbody>
            @forelse($pm_overdue_inspections as $i)
                <tr>
                    <td>{{ optional($i->inspected_at)->format('d M Y H:i') ?? optional($i->created_at)->format('d M Y H:i') }}</td>
                    <td><a href="{{ route('managedpremises.properties.show', $i->property_id) }}">{{ '#'.$i->property_id }}</a></td>
                    <td><span class="badge bg-warning text-dark">{{ $i->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted p-4">{{ __('managedpremises::pm.none') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
