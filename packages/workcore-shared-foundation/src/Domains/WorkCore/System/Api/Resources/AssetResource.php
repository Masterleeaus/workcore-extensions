<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class AssetResource
{
    public static function make(array|object $row): array
    {
        $record = (array) $row;
        foreach (['specifications', 'metadata', 'evidence', 'condition_out', 'condition_in', 'measurements', 'evidence_file_references', 'parts_references', 'terms', 'ai_suggestion'] as $field) {
            if (isset($record[$field]) && is_string($record[$field])) {
                $record[$field] = json_decode($record[$field], true);
            }
        }
        return [
            'id' => $record['public_id'] ?? null,
            'type' => 'asset',
            'attributes' => array_diff_key($record, ['public_id' => true, 'company_id' => true, 'id' => true]),
        ];
    }
}
