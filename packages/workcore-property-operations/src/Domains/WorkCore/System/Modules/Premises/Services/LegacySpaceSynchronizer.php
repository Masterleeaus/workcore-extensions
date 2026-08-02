<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseSpace;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyRoom;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyUnit;

class LegacySpaceSynchronizer
{
    public function syncUnit(PropertyUnit $unit): PremiseSpace
    {
        return PremiseSpace::updateOrCreate(
            ['company_id' => $unit->company_id, 'legacy_source_type' => 'unit', 'legacy_source_id' => $unit->id],
            [
                'user_id' => $unit->user_id,
                'premise_id' => $unit->property_id,
                'space_type' => $unit->type ?: 'unit',
                'code' => $unit->unit_code,
                'name' => $unit->unit_name ?: ('Unit '.$unit->id),
                'floor' => $unit->floor,
                'zone' => $unit->tower,
                'area' => $unit->area,
                'address_override' => $unit->address,
                'is_occupiable' => true,
                'is_billable' => true,
                'metadata' => ['migrated_from' => 'pm_property_units'],
            ]
        );
    }

    public function syncRoom(PropertyRoom $room): PremiseSpace
    {
        return PremiseSpace::updateOrCreate(
            ['company_id' => $room->company_id, 'legacy_source_type' => 'room', 'legacy_source_id' => $room->id],
            [
                'user_id' => $room->user_id,
                'premise_id' => $room->property_id,
                'space_type' => $room->type ?: 'room',
                'name' => $room->name,
                'is_occupiable' => true,
                'metadata' => ['notes' => $room->notes, 'migrated_from' => 'pm_property_rooms'],
            ]
        );
    }

    public function deleteLegacy(string $type, int $companyId, int $legacyId): void
    {
        PremiseSpace::where('company_id', $companyId)
            ->where('legacy_source_type', $type)
            ->where('legacy_source_id', $legacyId)
            ->delete();
    }
}
