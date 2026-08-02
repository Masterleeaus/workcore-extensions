<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class AppointmentResource
{
    public static function make(array $row): array
    {
        $resource = [
            'id' => (string) ($row['public_id'] ?? ''),
            'type' => 'appointment',
            'work_order_id' => $row['work_order_public_id'] ?? null,
            'work_order_number' => $row['work_order_number'] ?? null,
            'customer_id' => $row['customer_public_id'] ?? null,
            'customer_name' => $row['customer_name'] ?? null,
            'premises_id' => $row['premises_public_id'] ?? null,
            'premises_name' => $row['premises_name'] ?? null,
            'title' => $row['title'] ?? null,
            'description' => $row['description'] ?? null,
            'appointment_type' => $row['appointment_type'] ?? 'job',
            'status' => $row['status'] ?? 'tentative',
            'starts_at' => $row['starts_at'] ?? null,
            'ends_at' => $row['ends_at'] ?? null,
            'timezone' => $row['timezone'] ?? null,
            'all_day' => (bool) ($row['all_day'] ?? false),
            'access_notes' => $row['access_notes'] ?? null,
            'internal_notes' => $row['internal_notes'] ?? null,
            'source' => $row['source'] ?? 'manual',
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];

        if (array_key_exists('participants', $row)) {
            $resource['participants'] = array_map(static fn (array $participant): array => [
                'id' => (string) ($participant['public_id'] ?? ''),
                'type' => 'appointment_participant',
                'user_id' => $participant['user_id'] ?? null,
                'contact_id' => $participant['contact_public_id'] ?? null,
                'display_name' => $participant['display_name'] ?? null,
                'email' => $participant['email'] ?? null,
                'phone' => $participant['phone'] ?? null,
                'role' => $participant['role'] ?? 'attendee',
                'response_status' => $participant['response_status'] ?? 'needs_action',
                'is_required' => (bool) ($participant['is_required'] ?? false),
            ], (array) $row['participants']);
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
