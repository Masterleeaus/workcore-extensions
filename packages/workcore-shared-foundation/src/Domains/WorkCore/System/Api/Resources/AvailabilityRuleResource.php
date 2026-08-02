<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class AvailabilityRuleResource
{
    public static function make(array|object $row): array
    {
        $record = (array) $row;
        unset($record['company_id'], $record['worker_id']);
        return ['id' => $record['public_id'] ?? null, 'type' => 'availability_rule', 'attributes' => array_diff_key($record, ['public_id' => true])];
    }
}
