<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Support;

use Illuminate\Support\Facades\Schema;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Services\PremisesProfileRegistry;

class PropertyContextBuilder
{
    public static function forProperty(Property $property): array
    {
        $profile = app(PremisesProfileRegistry::class)->forProperty($property);
        $occupancyEnabled = (bool) ($profile['features']['occupancy'] ?? false);
        $occupancySummary = $occupancyEnabled ? [
            'current_count' => $property->currentOccupancies()->count(),
            'reserved_count' => $property->occupancies()->where('status', 'reserved')->count(),
            'active_count' => $property->occupancies()->whereIn('status', ['active', 'notice_given', 'ending', 'suspended'])->count(),
            'vacant_space_count' => $property->spaces()->where('is_occupiable', true)->where('availability_status', 'available')->count(),
        ] : null;

        $agreementsEnabled = (bool) ($profile['features']['agreements'] ?? false);
        $agreementSummary = $agreementsEnabled && Schema::hasTable('pm_premise_agreements') ? [
            'draft_count' => $property->agreements()->where('status', 'draft')->count(),
            'active_count' => $property->agreements()->where('status', 'active')->count(),
            'notice_or_ending_count' => $property->agreements()->whereIn('status', ['notice_given', 'ending'])->count(),
            'expiring_within_60_days' => $property->agreements()
                ->whereIn('status', ['active', 'notice_given', 'ending'])
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [now()->toDateString(), now()->addDays(60)->toDateString()])
                ->count(),
        ] : null;

        $accessSummary = Schema::hasTable('pm_premise_access_items') ? [
            'item_count' => $property->accessItems()->count(),
            'issued_count' => $property->accessItems()->where('status', 'issued')->count(),
            'overdue_return_count' => $property->accessItems()->where('status', 'issued')->whereNotNull('expected_return_at')->where('expected_return_at', '<', now())->count(),
            'active_authorisation_count' => Schema::hasTable('pm_premise_access_authorisations')
                ? $property->accessAuthorisations()->where('status', 'active')->count()
                : 0,
            'secrets_redacted' => true,
        ] : null;

        $physicalCardSummary = Schema::hasTable('pm_premise_access_card_requests') ? [
            'draft_count' => $property->accessCardRequests()->where('status', 'draft')->count(),
            'awaiting_approval_count' => $property->accessCardRequests()->where('status', 'submitted')->count(),
            'approved_or_issuing_count' => $property->accessCardRequests()->whereIn('status', ['approved', 'partially_issued'])->count(),
            'card_identifiers_and_contacts_excluded' => true,
        ] : null;

        $workPermitSummary = Schema::hasTable('pm_premise_work_permits') ? [
            'awaiting_manager_approval_count' => $property->workPermits()->where('status', 'submitted')->count(),
            'awaiting_site_approval_count' => $property->workPermits()->where('status', 'manager_approved')->count(),
            'awaiting_validation_count' => $property->workPermits()->where('status', 'site_approved')->count(),
            'active_count' => $property->workPermits()->where('status', 'active')->count(),
            'workcore_completion_not_inferred' => true,
        ] : null;

        $conditionSummary = Schema::hasTable('pm_premise_condition_records') ? [
            'draft_or_in_progress_count' => $property->conditionRecords()->whereIn('status', ['draft', 'in_progress'])->count(),
            'completed_count' => $property->conditionRecords()->whereIn('status', ['completed', 'approved'])->count(),
        ] : null;

        $incidentSummary = Schema::hasTable('pm_premise_incidents') ? [
            'open_standard_incident_count' => $property->incidents()->where('privacy_level', 'standard')->whereNotIn('status', ['closed', 'cancelled'])->count(),
            'restricted_records_excluded' => true,
        ] : null;

        $operationsSummary = Schema::hasTable('pm_premise_workflows') ? [
            'active_workflow_count' => $property->workflows()->where('status', 'active')->count(),
            'paused_workflow_count' => $property->workflows()->where('status', 'paused')->count(),
            'overdue_workflow_count' => $property->workflows()->whereIn('status', ['active', 'paused'])->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'workflow_details_require_explicit_permission' => true,
            'workflow_status_does_not_infer_financial_or_legal_completion' => true,
        ] : null;


        $portalSummary = Schema::hasTable('pm_premise_portal_grants') ? [
            'invited_count' => $property->portalGrants()->where('status', 'invited')->count(),
            'active_count' => $property->portalGrants()->where('status', 'active')->count(),
            'expiring_within_7_days' => $property->portalGrants()->whereIn('status', ['invited', 'active'])->whereNotNull('expires_at')->whereBetween('expires_at', [now(), now()->addDays(7)])->count(),
            'tokens_and_personal_data_excluded' => true,
        ] : null;

        $vacancySummary = Schema::hasTable('pm_premise_vacancy_listings') ? [
            'draft_count' => $property->vacancyListings()->where('status', 'draft')->count(),
            'pending_approval_count' => $property->vacancyListings()->where('status', 'pending_approval')->count(),
            'approved_count' => $property->vacancyListings()->where('status', 'approved')->count(),
            'published_count' => $property->vacancyListings()->where('status', 'published')->count(),
            'publication_requires_human_approval' => true,
            'exact_address_not_included' => true,
        ] : null;

        $complianceSummary = Schema::hasTable('pm_compliance_requirements') && Schema::hasTable('pm_compliance_occurrences') ? [
            'active_requirement_count' => $property->complianceRequirements()->where('status', 'active')->count(),
            'due_occurrence_count' => $property->complianceOccurrences()->where('status', 'due')->count(),
            'overdue_occurrence_count' => $property->complianceOccurrences()->where('status', 'overdue')->count(),
            'legal_compliance_not_inferred' => true,
            'source_verification_required' => true,
        ] : null;

        return [
            'module' => 'managedpremises',
            'page' => 'property.show',
            'company_id' => $property->company_id,
            'property_id' => $property->id,
            'property' => [
                'name' => $property->name,
                'address' => $property->address,
                'premise_type' => $property->type,
                'profile_key' => $profile['key'],
                'profile_label' => $profile['label'] ?? null,
                'terminology' => $profile['terminology'] ?? [],
                'features' => $profile['features'] ?? [],
                'attributes' => $property->profileAttributes(),
                'access_instructions_present' => !empty($property->access_notes) || !empty($property->lockbox_code) || !empty($property->keys_location),
                'access_summary' => $accessSummary,
                'physical_access_card_summary' => $physicalCardSummary,
                'work_permit_summary' => $workPermitSummary,
                'condition_summary' => $conditionSummary,
                'incident_summary' => $incidentSummary,
                'compliance_summary' => $complianceSummary,
                'operations_summary' => $operationsSummary,
                'portal_summary' => $portalSummary,
                'vacancy_summary' => $vacancySummary,
                'hazards' => $property->hazards,
                'occupancy_summary' => $occupancySummary,
                'agreement_summary' => $agreementSummary,
            ],
        ];
    }
}
