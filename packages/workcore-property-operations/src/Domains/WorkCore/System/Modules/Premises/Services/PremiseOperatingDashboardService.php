<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Carbon\Carbon;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAccessAuthorisation;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAccessItem;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAgreement;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseComplianceOccurrence;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseConditionRecord;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseIncident;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseOccupancy;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseSpace;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseWorkflow;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyServicePlan;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PropertyVisit;

class PremiseOperatingDashboardService
{
    public function __construct(private PremiseWorkflowRegistry $registry) {}

    public function build(Property $property): array
    {
        $profile = $this->registry->profile($property);
        $spaces = PremiseSpace::where('company_id', $property->company_id)
            ->where('premise_id', $property->id)
            ->with(['currentOccupancies.agreements'])
            ->get();

        $currentOccupancies = PremiseOccupancy::where('company_id', $property->company_id)
            ->where('premise_id', $property->id)
            ->whereIn('status', ['reserved', 'pending', 'active', 'notice_given', 'ending', 'suspended'])
            ->with('agreements')
            ->get();

        $expiryDays = $property->profileKey() === 'commercial_property' ? 90 : ($property->profileKey() === 'storage_facility' ? 30 : 60);
        $agreementCutoff = Carbon::now()->addDays($expiryDays)->endOfDay();
        $departureCutoff = Carbon::now()->addDays(30)->endOfDay();

        $allowedIncidentPrivacyLevels = $this->allowedIncidentPrivacyLevels();
        $openIncidentQuery = PremiseIncident::where('company_id', $property->company_id)
            ->where('premise_id', $property->id)
            ->whereIn('privacy_level', $allowedIncidentPrivacyLevels)
            ->whereNotIn('status', ['resolved', 'closed', 'cancelled']);

        $activeWorkflows = PremiseWorkflow::where('company_id', $property->company_id)
            ->where('premise_id', $property->id)
            ->whereIn('status', ['active', 'paused'])
            ->with(['space', 'occupancy', 'agreement', 'incident'])
            ->orderByRaw("CASE WHEN due_at IS NULL THEN 1 ELSE 0 END")
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->get();

        $allocatableSpaces = $spaces->filter(fn ($space) => $space->is_occupiable && $space->availability_status !== 'decommissioned');
        $occupiedSpaces = $allocatableSpaces->where('availability_status', 'occupied')->count();
        $metrics = [
            'total_spaces' => $spaces->count(),
            'allocatable_spaces' => $allocatableSpaces->count(),
            'occupied_spaces' => $occupiedSpaces,
            'available_spaces' => $allocatableSpaces->where('availability_status', 'available')->count(),
            'reserved_spaces' => $allocatableSpaces->where('availability_status', 'reserved')->count(),
            'turnover_spaces' => $allocatableSpaces->whereIn('availability_status', ['maintenance', 'cleaning', 'inspection_hold'])->count(),
            'occupancy_rate' => $allocatableSpaces->count() > 0 ? (int) round(($occupiedSpaces / $allocatableSpaces->count()) * 100) : 0,
            'upcoming_departures' => $currentOccupancies->filter(function ($occupancy) use ($departureCutoff) {
                return in_array($occupancy->status, ['notice_given', 'ending'], true)
                    || ($occupancy->end_date && $occupancy->end_date->lte($departureCutoff));
            })->count(),
            'agreements_expiring' => PremiseAgreement::where('company_id', $property->company_id)
                ->where('premise_id', $property->id)
                ->whereIn('status', ['active', 'notice_given', 'ending'])
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [Carbon::today(), $agreementCutoff])
                ->count(),
            'open_incidents' => (clone $openIncidentQuery)->count(),
            'restricted_incidents' => count($allowedIncidentPrivacyLevels) > 1
                ? (clone $openIncidentQuery)->whereIn('privacy_level', ['sensitive', 'restricted'])->count()
                : 0,
            'workcore_linked_incidents' => (clone $openIncidentQuery)->whereNotNull('workcore_record_id')->count(),
            'overdue_compliance' => PremiseComplianceOccurrence::where('company_id', $property->company_id)
                ->where('premise_id', $property->id)->where('status', 'overdue')->count(),
            'pending_condition_records' => PremiseConditionRecord::where('company_id', $property->company_id)
                ->where('premise_id', $property->id)->whereIn('status', ['draft', 'in_progress'])->count(),
            'overdue_access_returns' => PremiseAccessItem::where('company_id', $property->company_id)
                ->where('premise_id', $property->id)->where('status', 'issued')
                ->whereNotNull('expected_return_at')->where('expected_return_at', '<', now())->count(),
            'active_access_authorisations' => PremiseAccessAuthorisation::where('company_id', $property->company_id)
                ->where('premise_id', $property->id)->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                })->count(),
            'active_service_plans' => PropertyServicePlan::where('company_id', $property->company_id)
                ->where('property_id', $property->id)->where('is_active', true)->count(),
            'upcoming_visits' => PropertyVisit::where('company_id', $property->company_id)
                ->where('property_id', $property->id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->whereBetween('scheduled_for', [now(), Carbon::now()->addDays(30)->endOfDay()])->count(),
            'active_workflows' => $activeWorkflows->where('status', 'active')->count(),
            'paused_workflows' => $activeWorkflows->where('status', 'paused')->count(),
        ];

        $alerts = $this->buildAlerts($property, $spaces, $currentOccupancies, $metrics);
        $cards = collect($profile['dashboard']['cards'] ?? [])->map(function (array $card) use ($metrics) {
            $metric = $card['metric'] ?? '';
            return array_replace($card, ['value' => $metrics[$metric] ?? 0]);
        })->all();

        return [
            'profile' => $profile,
            'metrics' => $metrics,
            'cards' => $cards,
            'alerts' => $alerts,
            'templates' => $this->registry->templates($property),
            'active_workflows' => $activeWorkflows,
            'recent_workflows' => PremiseWorkflow::where('company_id', $property->company_id)
                ->where('premise_id', $property->id)
                ->whereIn('status', ['completed', 'cancelled'])
                ->latest('updated_at')->limit(10)->get(),
            'spaces' => $spaces->sortBy('name')->values(),
            'occupancies' => $currentOccupancies->sortBy('display_name')->values(),
            'agreements' => PremiseAgreement::where('company_id', $property->company_id)
                ->where('premise_id', $property->id)
                ->whereIn('status', ['offered', 'pending_signature', 'active', 'notice_given', 'ending'])
                ->orderBy('title')->get(),
            'incidents' => PremiseIncident::where('company_id', $property->company_id)
                ->where('premise_id', $property->id)
                ->where('privacy_level', 'standard')
                ->whereNotIn('status', ['closed', 'cancelled'])
                ->orderByDesc('reported_at')->get(),
        ];
    }

    private function buildAlerts(Property $property, $spaces, $occupancies, array $metrics): array
    {
        $alerts = [];
        $configured = $this->registry->profile($property)['dashboard']['alerts'] ?? [];
        $add = static function (string $key, string $label, int $count, string $level = 'warning') use (&$alerts, $configured): void {
            if (in_array($key, $configured, true) && $count > 0) {
                $alerts[] = compact('key', 'label', 'count', 'level');
            }
        };

        $capacityWarnings = $spaces->filter(function ($space) {
            if (!$space->is_occupiable) {
                return false;
            }
            $used = $space->currentOccupancies->sum('capacity_used');
            return ($space->capacity !== null && $used > (int) $space->capacity)
                || ($space->capacity === null && $used > 0);
        })->count();
        $missingAgreement = $occupancies->filter(function ($occupancy) {
            return in_array($occupancy->status, ['active', 'notice_given', 'ending'], true)
                && $occupancy->agreements->whereIn('status', ['active', 'notice_given', 'ending'])->isEmpty();
        })->count();
        $agreementsWithoutDocuments = PremiseAgreement::where('company_id', $property->company_id)
            ->where('premise_id', $property->id)
            ->whereIn('status', ['active', 'notice_given', 'ending'])
            ->whereNull('document_id')->count();
        $unassigned = $occupancies->whereNull('party_role_id')->count();
        $accessFailures = PremiseIncident::where('company_id', $property->company_id)
            ->where('premise_id', $property->id)
            ->where('incident_type', 'access_failure')
            ->whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count();

        $add('vacancies', 'Available spaces need allocation or advertising review', $metrics['available_spaces']);
        $add('capacity_warnings', 'Occupied spaces have missing or exceeded capacity settings', $capacityWarnings, 'danger');
        $add('unassigned_residents', 'Resident occupancies are not linked to a party record', $unassigned);
        $add('unassigned_storage_customers', 'Storage occupancies are not linked to a customer party record', $unassigned);
        $add('missing_active_agreements', 'Current occupancies have no active agreement', $missingAgreement, 'danger');
        $add('agreements_without_documents', 'Current agreements have no linked document', $agreementsWithoutDocuments);
        $add('restricted_incidents', 'Sensitive or restricted incidents require attention', $metrics['restricted_incidents'], 'danger');
        $add('compliance_holds', 'Spaces are held from allocation for compliance', $spaces->where('availability_status', 'compliance_hold')->count(), 'danger');
        $add('overdue_access_returns', 'Issued access items are overdue for return', $metrics['overdue_access_returns']);
        $add('access_failures', 'Open access-failure incidents require review', $accessFailures, 'danger');

        return $alerts;
    }

    private function allowedIncidentPrivacyLevels(): array
    {
        if (!function_exists('user') || !user()) {
            return ['standard', 'sensitive', 'restricted'];
        }

        if (user()->permission('managedpremises.incidents.restricted.view') !== 'none') {
            return ['standard', 'sensitive', 'restricted'];
        }

        if (user()->permission('managedpremises.incidents.sensitive.view') !== 'none') {
            return ['standard', 'sensitive'];
        }

        return ['standard'];
    }
}
