<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class OpportunityResource
{
    public static function make(array $row): array
    {
        return [
            'id' => (string) ($row['public_id'] ?? $row['id'] ?? ''),
            'type' => 'opportunity',
            'title' => $row['title'] ?? null,
            'description' => $row['description'] ?? null,
            'amount' => (float) ($row['amount'] ?? 0),
            'currency_code' => $row['currency_code'] ?? null,
            'status' => $row['status'] ?? null,
            'probability' => (int) ($row['probability'] ?? 0),
            'position' => (int) ($row['position'] ?? 0),
            'expected_close_date' => $row['expected_close_date'] ?? null,
            'closed_at' => $row['closed_at'] ?? null,
            'lost_reason' => $row['lost_reason'] ?? null,
            'owner_user_id' => $row['owner_user_id'] ?? null,
            'pipeline' => isset($row['pipeline_public_id']) ? ['id' => $row['pipeline_public_id'], 'name' => $row['pipeline_name'] ?? null] : null,
            'stage' => isset($row['stage_public_id']) ? ['id' => $row['stage_public_id'], 'name' => $row['stage_name'] ?? null, 'color' => $row['stage_color'] ?? null] : null,
            'lead' => isset($row['lead_public_id']) ? ['id' => $row['lead_public_id'], 'name' => $row['lead_name'] ?? null] : null,
            'customer' => isset($row['customer_public_id']) ? ['id' => $row['customer_public_id'], 'name' => $row['customer_name'] ?? null] : null,
            'contact' => isset($row['contact_public_id']) ? ['id' => $row['contact_public_id'], 'name' => $row['contact_name'] ?? null] : null,
            'metadata' => self::json($row['metadata'] ?? null),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private static function json(mixed $value): array
    {
        if (is_array($value)) { return $value; }
        if (! is_string($value) || trim($value) === '') { return []; }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
