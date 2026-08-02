<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAccessItem;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyKey;

class LegacyAccessSynchronizer
{
    public function syncPropertyKey(PropertyKey $key): PremiseAccessItem
    {
        $item = PremiseAccessItem::withoutGlobalScopes()->firstOrNew([
            'company_id' => $key->company_id,
            'legacy_property_key_id' => $key->id,
        ]);
        $item->fill([
            'user_id' => $key->user_id,
            'premise_id' => $key->property_id,
            'item_type' => $this->normaliseType((string) $key->type),
            'label' => $key->type ?: 'Legacy access item',
            'secret_value' => $key->code ?: null,
            'status' => $item->exists ? $item->status : 'available',
            'location' => $key->location,
            'notes' => $key->notes,
            'metadata' => array_replace($item->metadata ?? [], ['legacy_source' => 'pm_property_keys']),
        ]);
        $item->save();
        return $item;
    }

    public function retirePropertyKey(PropertyKey $key): void
    {
        PremiseAccessItem::withoutGlobalScopes()
            ->where('company_id', $key->company_id)
            ->where('legacy_property_key_id', $key->id)
            ->whereNotIn('status', ['revoked', 'decommissioned'])
            ->update(['status' => 'decommissioned', 'current_party_role_id' => null, 'current_holder_name' => null]);
    }

    private function normaliseType(string $type): string
    {
        $value = strtolower(trim($type));
        return match (true) {
            str_contains($value, 'card') => 'access_card',
            str_contains($value, 'remote') => 'gate_remote',
            str_contains($value, 'pin'), str_contains($value, 'code') => 'pin_code',
            str_contains($value, 'master') => 'master_key',
            str_contains($value, 'lockbox') => 'lockbox',
            default => 'physical_key',
        };
    }
}
