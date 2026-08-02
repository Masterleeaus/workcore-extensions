<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class SalesPipelineResource
{
    public static function make(array $row): array
    {
        $stages = array_map(static function (array $stage): array {
            $stage['id'] = (string) ($stage['public_id'] ?? $stage['id'] ?? '');
            unset($stage['public_id']);
            $stage['position'] = (int) ($stage['position'] ?? 0);
            $stage['probability'] = (int) ($stage['probability'] ?? 0);
            $stage['is_closed'] = (bool) ($stage['is_closed'] ?? false);
            $stage['is_won'] = (bool) ($stage['is_won'] ?? false);
            $stage['is_active'] = (bool) ($stage['is_active'] ?? true);
            if (isset($stage['opportunities'])) {
                $stage['opportunities'] = array_map(OpportunityResource::make(...), (array) $stage['opportunities']);
            }
            $stage['opportunity_count'] = (int) ($stage['opportunity_count'] ?? count((array) ($stage['opportunities'] ?? [])));
            $stage['total_value'] = (float) ($stage['total_value'] ?? 0);
            return $stage;
        }, array_values((array) ($row['stages'] ?? [])));

        return [
            'id' => (string) ($row['public_id'] ?? $row['id'] ?? ''),
            'type' => 'sales_pipeline',
            'key' => $row['key'] ?? null,
            'name' => $row['name'] ?? null,
            'is_default' => (bool) ($row['is_default'] ?? false),
            'is_active' => (bool) ($row['is_active'] ?? true),
            'stage_count' => (int) ($row['stage_count'] ?? count($stages)),
            'stages' => $stages,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
