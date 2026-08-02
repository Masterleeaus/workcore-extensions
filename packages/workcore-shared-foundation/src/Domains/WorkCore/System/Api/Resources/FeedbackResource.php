<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class FeedbackResource
{
    public static function make(array|object $row): array
    {
        $record = (array) $row;
        foreach (['custom_meta', 'metadata'] as $field) {
            if (isset($record[$field]) && is_string($record[$field])) {
                $record[$field] = json_decode($record[$field], true);
            }
        }

        unset($record['id'], $record['company_id'], $record['deleted_at']);

        return [
            'id' => $record['public_id'] ?? null,
            'type' => 'feedback',
            'attributes' => array_diff_key($record, ['public_id' => true]),
        ];
    }
}
