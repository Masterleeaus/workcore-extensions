<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class ServiceResource
{
    public static function make(array $row): array
    {
        return [
            'id' => (string) ($row['public_id'] ?? ''),
            'type' => 'service',
            'business_line_id' => $row['business_line_public_id'] ?? null,
            'business_line_name' => $row['business_line_name'] ?? null,
            'category_id' => $row['category_public_id'] ?? null,
            'category_name' => $row['category_name'] ?? null,
            'code' => $row['code'] ?? null,
            'name' => $row['name'] ?? null,
            'description' => $row['description'] ?? null,
            'default_duration_minutes' => $row['default_duration_minutes'] ?? null,
            'pricing_model' => $row['pricing_model'] ?? 'fixed',
            'base_price' => $row['base_price'] ?? null,
            'tax_code' => $row['tax_code'] ?? null,
            'status' => $row['status'] ?? 'active',
        ];
    }
}
