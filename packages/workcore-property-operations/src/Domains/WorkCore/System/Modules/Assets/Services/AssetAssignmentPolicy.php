<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Services;

use InvalidArgumentException;

final class AssetAssignmentPolicy
{
    private const TYPE_ALIASES = [
        'installed_at' => 'premise',
        'located_at' => 'premise',
        'issued_to' => 'worker',
        'custodian' => 'worker',
        'mounted_on' => 'asset',
        'stored_in' => 'storage_location',
    ];

    private const TARGET_FIELDS = [
        'worker' => 'worker_public_id',
        'team' => 'team_public_id',
        'vehicle' => 'vehicle_public_id',
        'premise' => 'premises_public_id',
        'premise_zone' => 'premise_zone_public_id',
        'job' => 'job_public_id',
        'storage_location' => 'storage_location_public_id',
        'asset' => 'target_asset_public_id',
    ];

    public function normalizeType(string $type): string
    {
        $normalized = self::TYPE_ALIASES[$type] ?? $type;
        if (! isset(self::TARGET_FIELDS[$normalized])) {
            throw new InvalidArgumentException('Unsupported asset allocation type.');
        }
        return $normalized;
    }

    public function assertTarget(string $type, array $data): string
    {
        $type = $this->normalizeType($type);
        $field = self::TARGET_FIELDS[$type];
        if (trim((string) ($data[$field] ?? '')) === '') {
            throw new InvalidArgumentException("Asset allocation type {$type} requires {$field}.");
        }
        if ($type === 'premise_zone' && trim((string) ($data['premises_public_id'] ?? '')) === '') {
            throw new InvalidArgumentException('Premise-zone allocation requires premises_public_id.');
        }
        return $type;
    }

    public function targetField(string $type): string
    {
        return self::TARGET_FIELDS[$this->normalizeType($type)];
    }
}
