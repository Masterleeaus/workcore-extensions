<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Api\Resources;
final class LeadResource
{
    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function make(array $row): array
    {
        return [
            'id' => (string) ($row['public_id'] ?? ''),
            'type' => 'lead',
            'name' => $row['name'] ?? null,
            'company_name' => $row['company_name'] ?? null,
            'email' => $row['email'] ?? null,
            'phone' => $row['phone'] ?? null,
            'notes' => $row['notes'] ?? $row['note'] ?? null,
            'owner_user_id' => $row['owner_user_id'] ?? null,
            'priority' => (int) ($row['priority'] ?? 0),
            'next_follow_up' => (bool) ($row['next_follow_up'] ?? false),
            'follow_up_at' => $row['follow_up_at'] ?? null,
            'source' => isset($row['source_public_id']) ? ['id' => $row['source_public_id'], 'name' => $row['source_name'] ?? null] : null,
            'status' => isset($row['status_public_id']) ? ['id' => $row['status_public_id'], 'name' => $row['status_name'] ?? null] : null,
            'converted_at' => $row['converted_at'] ?? null,
            'customer_id' => $row['customer_public_id'] ?? null,
            'duplicate_candidates' => $row['duplicate_candidates'] ?? [],
        ];
    }
}
