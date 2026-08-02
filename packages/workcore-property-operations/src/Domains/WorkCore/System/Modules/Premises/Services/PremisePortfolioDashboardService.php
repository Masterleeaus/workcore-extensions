<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseComplianceOccurrence;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseIncident;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseSpace;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseWorkflow;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class PremisePortfolioDashboardService
{
    public function build(int $companyId): array
    {
        $profileCounts = Property::where('company_id', $companyId)
            ->selectRaw('profile_key, COUNT(*) as aggregate')
            ->groupBy('profile_key')
            ->pluck('aggregate', 'profile_key')
            ->map(fn ($value) => (int) $value)
            ->all();

        return [
            'total_premises' => array_sum($profileCounts),
            'profile_counts' => $profileCounts,
            'available_spaces' => PremiseSpace::where('company_id', $companyId)
                ->where('is_occupiable', true)->where('availability_status', 'available')->count(),
            'occupied_spaces' => PremiseSpace::where('company_id', $companyId)
                ->where('is_occupiable', true)->where('availability_status', 'occupied')->count(),
            'turnover_spaces' => PremiseSpace::where('company_id', $companyId)
                ->whereIn('availability_status', ['maintenance', 'cleaning', 'inspection_hold'])->count(),
            'open_standard_incidents' => PremiseIncident::where('company_id', $companyId)
                ->where('privacy_level', 'standard')->whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count(),
            'overdue_compliance' => PremiseComplianceOccurrence::where('company_id', $companyId)
                ->where('status', 'overdue')->count(),
            'active_workflows' => PremiseWorkflow::where('company_id', $companyId)
                ->whereIn('status', ['active', 'paused'])->count(),
        ];
    }
}
