<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Queries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class GetPremiseServiceReadinessQuery
{
    public function handle(Property $property): array
    {
        $companyId = function_exists('company_id') ? company_id() : null;

        $accessReady = !empty($property->access_notes) || !empty($property->lockbox_code) || !empty($property->keys_location);
        $activeWindows = Schema::hasTable('pm_property_service_windows') ? DB::table('pm_property_service_windows')
            ->where('property_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count() : 0;
        $activePlans = Schema::hasTable('pm_property_service_plans') ? DB::table('pm_property_service_plans')
            ->where('property_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', ['active', 'scheduled'])
            ->count() : 0;
        $keysCount = Schema::hasTable('pm_property_keys') ? DB::table('pm_property_keys')
            ->where('property_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count() : 0;

        return [
            'access_ready' => $accessReady,
            'active_service_windows' => $activeWindows,
            'active_service_plans' => $activePlans,
            'keys_count' => $keysCount,
            'blockers' => array_values(array_filter([
                $accessReady ? null : 'Missing access instructions',
                $activeWindows > 0 ? null : 'No service window set',
                $activePlans > 0 ? null : 'No active service plan',
                $keysCount > 0 ? null : 'No key/access record',
            ])),
        ];
    }
}
