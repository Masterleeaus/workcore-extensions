<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class DispatchAssignmentResource
{
    public static function make(array $row): array
    {
        $resource = [
            'id' => (string) ($row['public_id'] ?? ''), 'type' => 'dispatch_assignment',
            'dispatch_number' => $row['dispatch_number'] ?? null,
            'work_order_id' => $row['work_order_public_id'] ?? null, 'work_order_number' => $row['work_order_number'] ?? null,
            'appointment_id' => $row['appointment_public_id'] ?? null, 'appointment_title' => $row['appointment_title'] ?? null,
            'premises_id' => $row['premises_public_id'] ?? null, 'premises_name' => $row['premises_name'] ?? null,
            'assigned_user_id' => $row['assigned_user_id'] ?? null, 'assigned_user_name' => $row['assigned_user_name'] ?? null,
            'assignment_type' => $row['assignment_type'] ?? 'job', 'priority' => $row['priority'] ?? 'normal', 'status' => $row['status'] ?? 'assigned',
            'scheduled_start' => $row['scheduled_start'] ?? null, 'scheduled_end' => $row['scheduled_end'] ?? null, 'timezone' => $row['timezone'] ?? null,
            'route_group' => $row['route_group'] ?? null, 'sequence' => $row['sequence'] ?? null,
            'instructions' => $row['instructions'] ?? null, 'access_notes' => $row['access_notes'] ?? null, 'internal_notes' => $row['internal_notes'] ?? null,
            'travel_distance_meters' => $row['travel_distance_meters'] ?? null, 'travel_duration_seconds' => $row['travel_duration_seconds'] ?? null,
            'source' => $row['source'] ?? 'manual', 'dispatched_at' => $row['dispatched_at'] ?? null, 'en_route_at' => $row['en_route_at'] ?? null,
            'arrived_at' => $row['arrived_at'] ?? null, 'started_at' => $row['started_at'] ?? null, 'completed_at' => $row['completed_at'] ?? null,
            'cancelled_at' => $row['cancelled_at'] ?? null, 'failed_at' => $row['failed_at'] ?? null,
            'created_at' => $row['created_at'] ?? null, 'updated_at' => $row['updated_at'] ?? null,
        ];
        if (array_key_exists('events', $row)) {
            $resource['events'] = array_map(static fn (array $event): array => [
                'id' => (string) ($event['public_id'] ?? ''), 'event_type' => $event['event_type'] ?? null,
                'from_status' => $event['from_status'] ?? null, 'to_status' => $event['to_status'] ?? null,
                'reason' => $event['reason'] ?? null, 'latitude' => $event['latitude'] ?? null, 'longitude' => $event['longitude'] ?? null,
                'metadata' => $event['metadata'] ?? [], 'changed_by_user_id' => $event['changed_by_user_id'] ?? null, 'occurred_at' => $event['occurred_at'] ?? null,
            ], (array) $row['events']);
        }
        return $resource;
    }
}
