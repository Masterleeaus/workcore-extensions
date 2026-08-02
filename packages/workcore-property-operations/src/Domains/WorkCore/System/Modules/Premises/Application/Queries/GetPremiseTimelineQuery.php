<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Queries;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class GetPremiseTimelineQuery
{
    public function handle(Property $property, int $limit = 20): Collection
    {
        $companyId = function_exists('company_id') ? company_id() : null;

        $visits = Schema::hasTable('pm_property_visits') ? DB::table('pm_property_visits')
            ->selectRaw("'visit' as type, id, scheduled_for as happened_at, status")
            ->where('property_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->limit($limit)
            ->get() : collect();

        $approvals = Schema::hasTable('pm_property_approvals') ? DB::table('pm_property_approvals')
            ->selectRaw("'approval' as type, id, COALESCE(decided_at, requested_at, created_at) as happened_at, status")
            ->where('property_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->limit($limit)
            ->get() : collect();

        $meters = Schema::hasTable('pm_meter_readings') ? DB::table('pm_meter_readings')
            ->selectRaw("'meter' as type, id, reading_date as happened_at, meter_type as status")
            ->where('property_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->limit($limit)
            ->get() : collect();

        $agreements = Schema::hasTable('pm_premise_agreements') ? DB::table('pm_premise_agreements')
            ->selectRaw("'agreement' as type, id, COALESCE(terminated_at, notice_given_at, signed_at, offered_at, created_at) as happened_at, status")
            ->where('premise_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->limit($limit)
            ->get() : collect();

        $qc = Schema::hasTable('qc_records') ? DB::table('qc_records')
            ->selectRaw("'qc' as type, id, COALESCE(inspected_at, created_at) as happened_at, status")
            ->where('property_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->limit($limit)
            ->get() : collect();

        return collect()
            ->merge($visits)
            ->merge($approvals)
            ->merge($meters)
            ->merge($agreements)
            ->merge($qc)
            ->sortByDesc('happened_at')
            ->take($limit)
            ->values();
    }
}
