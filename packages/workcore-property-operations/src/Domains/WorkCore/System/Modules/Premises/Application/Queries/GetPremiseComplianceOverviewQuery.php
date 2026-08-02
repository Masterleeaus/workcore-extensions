<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Application\Queries;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class GetPremiseComplianceOverviewQuery
{
    public function handle(Property $property): array
    {
        $companyId = function_exists('company_id') ? company_id() : $property->company_id;

        $openHazards = Schema::hasTable('pm_property_hazards') ? DB::table('pm_property_hazards')
            ->where('property_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count() : 0;

        $overdueApprovals = Schema::hasTable('pm_property_approvals') ? DB::table('pm_property_approvals')
            ->where('property_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('status', 'pending')
            ->whereNotNull('requested_at')
            ->where('requested_at', '<', now()->subDays(7))
            ->count() : 0;

        $documentsCount = Schema::hasTable('pm_property_documents') ? DB::table('pm_property_documents')
            ->where('property_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count() : 0;

        $qcFailing = Schema::hasTable('qc_records') ? DB::table('qc_records')
            ->where('property_id', $property->id)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', ['fail', 'reclean_required'])
            ->count() : 0;

        $activeRequirements = Schema::hasTable('pm_compliance_requirements') ? DB::table('pm_compliance_requirements')
            ->where('premise_id', $property->id)->where('company_id', $companyId)->where('status', 'active')->count() : 0;
        $dueRequirements = Schema::hasTable('pm_compliance_occurrences') ? DB::table('pm_compliance_occurrences')
            ->where('premise_id', $property->id)->where('company_id', $companyId)->where('status', 'due')->count() : 0;
        $overdueRequirements = Schema::hasTable('pm_compliance_occurrences') ? DB::table('pm_compliance_occurrences')
            ->where('premise_id', $property->id)->where('company_id', $companyId)->where('status', 'overdue')->count() : 0;
        $verifiedEvidence = Schema::hasTable('pm_compliance_evidence') ? DB::table('pm_compliance_evidence')
            ->where('premise_id', $property->id)->where('company_id', $companyId)->where('status', 'verified')->count() : 0;
        $missingEvidence = 0;
        if (Schema::hasTable('pm_compliance_requirements') && Schema::hasTable('pm_compliance_evidence')) {
            $missingEvidence = DB::table('pm_compliance_requirements as r')
                ->where('r.premise_id', $property->id)
                ->where('r.company_id', $companyId)
                ->where('r.status', 'active')
                ->where('r.evidence_required', true)
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')->from('pm_compliance_evidence as e')
                        ->whereColumn('e.requirement_id', 'r.id')
                        ->whereIn('e.status', ['submitted', 'verified']);
                })->count();
        }

        return [
            'open_hazards' => $openHazards,
            'overdue_approvals' => $overdueApprovals,
            'documents_count' => $documentsCount,
            'qc_failing' => $qcFailing,
            'active_requirements' => $activeRequirements,
            'due_requirements' => $dueRequirements,
            'overdue_requirements' => $overdueRequirements,
            'verified_evidence' => $verifiedEvidence,
            'missing_evidence' => $missingEvidence,
            'legal_compliance_not_inferred' => true,
        ];
    }
}
