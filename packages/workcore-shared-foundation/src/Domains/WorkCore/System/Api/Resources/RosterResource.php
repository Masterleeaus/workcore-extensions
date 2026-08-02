<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Api\Resources;

final class RosterResource
{
    public static function make(array|object $row): array
    {
        $record = (array) $row;
        unset($record['internal_id'], $record['company_id']);
        return ['id' => $record['public_id'] ?? null, 'type' => 'roster', 'attributes' => array_diff_key($record, ['public_id' => true])];
    }
}
