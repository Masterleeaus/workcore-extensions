<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class WorkOrderResource
{
    public static function make(array $row): array
    {
        $resource = [
            'id' => (string) ($row['public_id'] ?? ''),
            'type' => 'work_order',
            'number' => $row['number'] ?? null,
            'business_line_id' => $row['business_line_public_id'] ?? null,
            'business_line_name' => $row['business_line_name'] ?? null,
            'customer_id' => $row['customer_public_id'] ?? null,
            'customer_name' => $row['customer_name'] ?? null,
            'premises_id' => $row['premises_public_id'] ?? null,
            'premises_name' => $row['premises_name'] ?? null,
            'title' => $row['title'] ?? null,
            'description' => $row['description'] ?? null,
            'priority' => $row['priority'] ?? 'normal',
            'status' => $row['status'] ?? 'draft',
            'source' => $row['source'] ?? 'manual',
            'due_at' => $row['due_at'] ?? null,
            'started_at' => $row['started_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'cancelled_at' => $row['cancelled_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];

        if (array_key_exists('services', $row)) {
            $resource['services'] = array_map(static fn (array $service): array => [
                'id' => (string) ($service['public_id'] ?? ''),
                'type' => 'work_order_service',
                'service_code' => $service['service_code'] ?? null,
                'service_name' => $service['service_name'] ?? null,
                'description' => $service['description'] ?? null,
                'quantity' => isset($service['quantity']) ? (float) $service['quantity'] : 1.0,
                'unit_price' => $service['unit_price'] ?? null,
                'line_total' => isset($service['unit_price']) ? round((float) $service['unit_price'] * (float) ($service['quantity'] ?? 1), 2) : null,
                'estimated_duration_minutes' => $service['estimated_duration_minutes'] ?? null,
                'sort_order' => $service['sort_order'] ?? 0,
                'status' => $service['status'] ?? 'pending',
            ], (array) $row['services']);
        }

        if (array_key_exists('tasks', $row)) {
            $resource['tasks'] = array_map(static fn (array $task): array => [
                'id' => (string) ($task['public_id'] ?? ''),
                'type' => 'work_order_task',
                'name' => $task['name'] ?? null,
                'description' => $task['description'] ?? null,
                'sort_order' => $task['sort_order'] ?? 0,
                'estimated_minutes' => $task['estimated_minutes'] ?? null,
                'is_required' => (bool) ($task['is_required'] ?? true),
                'status' => $task['status'] ?? 'pending',
                'completed_at' => $task['completed_at'] ?? null,
                'completed_by_user_id' => $task['completed_by_user_id'] ?? null,
            ], (array) $row['tasks']);
        }

        if (array_key_exists('time_entries', $row)) {
            $resource['time_entries'] = array_map(static fn (array $entry): array => [
                'id' => (string) ($entry['public_id'] ?? ''),
                'type' => 'time_entry',
                'task_id' => $entry['task_public_id'] ?? null,
                'user_id' => $entry['user_id'] ?? null,
                'started_at' => $entry['started_at'] ?? null,
                'ended_at' => $entry['ended_at'] ?? null,
                'duration_minutes' => $entry['duration_minutes'] ?? null,
                'notes' => $entry['notes'] ?? null,
                'is_billable' => (bool) ($entry['is_billable'] ?? true),
                'status' => $entry['status'] ?? 'recorded',
            ], (array) $row['time_entries']);
        }

        if (array_key_exists('status_history', $row)) {
            $resource['status_history'] = array_map(static fn (array $history): array => [
                'id' => (string) ($history['public_id'] ?? ''),
                'from_status' => $history['from_status'] ?? null,
                'to_status' => $history['to_status'] ?? null,
                'reason' => $history['reason'] ?? null,
                'changed_by_user_id' => $history['changed_by_user_id'] ?? null,
                'changed_at' => $history['changed_at'] ?? null,
            ], (array) $row['status_history']);
        }

        return $resource;
    }
}
