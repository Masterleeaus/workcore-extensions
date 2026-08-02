<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class PropertyProfileService
{
    public function __construct(private readonly PremisesProfileRegistry $profiles)
    {
    }

    public function summary(Property $property): array
    {
        $profile = $this->profiles->forProperty($property);
        $occupancyEnabled = (bool) ($profile['features']['occupancy'] ?? false);

        return [
            'property_id' => $property->id,
            'name' => $property->name,
            'address' => $property->address,
            'premise_type' => $property->type,
            'profile' => $profile,
            'attributes' => $property->profileAttributes(),
            'access_instructions_present' => !empty($property->access_notes) || !empty($property->lockbox_code) || !empty($property->keys_location),
            'access_secrets_redacted' => true,
            'hazards' => $property->hazards,
            'occupancy_summary' => $occupancyEnabled ? [
                'current_count' => $property->currentOccupancies()->count(),
                'vacant_space_count' => $property->spaces()->where('is_occupiable', true)->where('availability_status', 'available')->count(),
            ] : null,
        ];
    }
}
