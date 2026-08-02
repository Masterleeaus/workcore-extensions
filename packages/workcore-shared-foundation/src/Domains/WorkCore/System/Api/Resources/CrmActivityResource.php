<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class CrmActivityResource
{
    public static function make(array $row): array
    {
        $metadata = $row['metadata'] ?? null;
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }
        return [
            'id' => (string) ($row['public_id'] ?? $row['id'] ?? ''),
            'type' => 'crm_activity',
            'subject' => ['type' => $row['subject_type'] ?? null, 'id' => $row['subject_public_id'] ?? null],
            'activity_type' => $row['type'] ?? null,
            'title' => $row['title'] ?? null,
            'body' => $row['body'] ?? null,
            'direction' => $row['direction'] ?? null,
            'scheduled_at' => $row['scheduled_at'] ?? null,
            'completed_at' => $row['completed_at'] ?? null,
            'actor_user_id' => $row['actor_user_id'] ?? null,
            'metadata' => is_array($metadata) ? $metadata : [],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
