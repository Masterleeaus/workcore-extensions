<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortalGrant;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseApprovalRequest;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseManagementAuthority;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseOwnershipInterest;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortfolioMembership;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseVacancyListing;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseWorkflow;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class GenerativePanelService
{
    public function __construct(private PremiseOperatingDashboardService $operations) {}

    public function available(): array
    {
        return collect(config('managedpremises_generative_panels', []))->map(fn ($panel, $key) => [
            'key' => $key,
            'schema' => $panel['schema'] ?? 'managedpremises.panel.v1',
            'title' => $panel['title'] ?? $key,
            'permission' => $panel['permission'] ?? null,
            'components' => $panel['components'] ?? [],
            'actions' => $panel['actions'] ?? [],
        ])->values()->all();
    }

    public function build(Property $property, string $panelKey): array
    {
        $definition = config("managedpremises_generative_panels.{$panelKey}");
        if (!$definition) {
            throw ValidationException::withMessages(['panel' => 'Unknown ManagedPremises panel.']);
        }

        $payload = match ($panelKey) {
            'premise_operations' => $this->operationsPayload($property),
            'vacancy_board' => $this->vacancyPayload($property),
            'mobile_workflows' => $this->workflowPayload($property),
            'portal_summary' => $this->portalPayload($property),
            'owner_portfolio' => $this->ownerPortfolioPayload($property),
            default => [],
        };

        return [
            'schema' => $definition['schema'] ?? 'managedpremises.panel.v1',
            'panel' => $panelKey,
            'title' => $definition['title'] ?? $panelKey,
            'premise_id' => $property->id,
            'profile_key' => $property->profileKey(),
            'components' => $definition['components'] ?? [],
            'actions' => $definition['actions'] ?? [],
            'payload' => $payload,
            'safety' => [
                'access_secrets_redacted' => true,
                'restricted_incidents_excluded' => true,
                'portal_tokens_excluded' => true,
                'finance_identifiers_excluded' => true,
                'actions_require_normal_permission_and_approval_checks' => true,
            ],
        ];
    }

    private function operationsPayload(Property $property): array
    {
        $dashboard = $this->operations->build($property);
        return [
            'profile_label' => $dashboard['profile']['label'] ?? $property->profileKey(),
            'cards' => collect($dashboard['cards'] ?? [])->map(fn ($card) => [
                'label' => $card['label'] ?? null,
                'value' => $card['value'] ?? null,
                'metric' => $card['metric'] ?? null,
            ])->values()->all(),
            'alerts' => collect($dashboard['alerts'] ?? [])->map(fn ($alert) => [
                'type' => $alert['type'] ?? null,
                'label' => $alert['label'] ?? null,
                'count' => $alert['count'] ?? null,
                'severity' => $alert['severity'] ?? $alert['level'] ?? 'info',
            ])->values()->all(),
            'workflows' => collect($dashboard['active_workflows'] ?? [])->map(fn ($workflow) => $this->workflowCard($workflow))->values()->all(),
        ];
    }

    private function vacancyPayload(Property $property): array
    {
        if (!Schema::hasTable('pm_premise_vacancy_listings')) return ['counts' => [], 'listings' => []];
        $query = PremiseVacancyListing::withoutGlobalScopes()->where('company_id', $property->company_id)->where('premise_id', $property->id);
        $counts = [];
        foreach (PremiseVacancyListing::STATUSES as $status) $counts[$status] = (clone $query)->where('status', $status)->count();
        $listings = (clone $query)->with('space')->latest('id')->limit(20)->get()->map(fn ($listing) => [
            'id' => $listing->id,
            'title' => $listing->title,
            'status' => $listing->status,
            'space' => $listing->space?->name,
            'available_from' => optional($listing->available_from)->toDateString(),
            'publication_channels' => $listing->publications()->pluck('status', 'channel')->all(),
        ])->values()->all();
        return ['counts' => $counts, 'listings' => $listings];
    }

    private function workflowPayload(Property $property): array
    {
        if (!Schema::hasTable('pm_premise_workflows')) return ['workflows' => []];
        return ['workflows' => PremiseWorkflow::withoutGlobalScopes()
            ->where('company_id', $property->company_id)->where('premise_id', $property->id)
            ->whereIn('status', ['active', 'paused'])->orderBy('due_at')->limit(30)->get()
            ->map(fn ($workflow) => $this->workflowCard($workflow))->values()->all()];
    }

    private function portalPayload(Property $property): array
    {
        if (!Schema::hasTable('pm_premise_portal_grants')) return ['counts' => [], 'grants' => []];
        $query = PremisePortalGrant::withoutGlobalScopes()->where('company_id', $property->company_id)->where('premise_id', $property->id);
        $counts = [];
        foreach (PremisePortalGrant::STATUSES as $status) $counts[$status] = (clone $query)->where('status', $status)->count();
        return [
            'counts' => $counts,
            'grants' => (clone $query)->latest('id')->limit(20)->get()->map(fn ($grant) => [
                'id' => $grant->id,
                'label' => $grant->label,
                'status' => $grant->status,
                'capabilities' => $grant->capabilities,
                'expires_at' => optional($grant->expires_at)->toIso8601String(),
                'last_accessed_at' => optional($grant->last_accessed_at)->toIso8601String(),
                'token_hint' => $grant->token_hint,
            ])->values()->all(),
        ];
    }

    private function ownerPortfolioPayload(Property $property): array
    {
        if (!Schema::hasTable('pm_premise_ownership_interests')) return ['ownership' => [], 'authorities' => [], 'approvals' => [], 'portfolios' => []];
        return [
            'ownership' => PremiseOwnershipInterest::withoutGlobalScopes()->where('company_id', $property->company_id)
                ->where('premise_id', $property->id)->whereNull('valid_until')->with('partyRole:id,display_name,role')
                ->get()->map(fn ($interest) => [
                    'owner' => $interest->partyRole?->display_name,
                    'ownership_type' => $interest->ownership_type,
                    'share_percent' => $interest->share_percent,
                    'is_primary' => $interest->is_primary,
                ])->values()->all(),
            'authorities' => PremiseManagementAuthority::withoutGlobalScopes()->where('company_id', $property->company_id)
                ->where('premise_id', $property->id)->whereIn('status', ['pending_approval','active','notice_given','suspended'])
                ->with('partyRole:id,display_name,role')->get()->map(fn ($authority) => [
                    'party' => $authority->partyRole?->display_name,
                    'authority_type' => $authority->authority_type,
                    'status' => $authority->status,
                    'start_date' => optional($authority->start_date)->toDateString(),
                    'end_date' => optional($authority->end_date)->toDateString(),
                ])->values()->all(),
            'approvals' => PremiseApprovalRequest::withoutGlobalScopes()->where('company_id', $property->company_id)
                ->where('premise_id', $property->id)->whereIn('status', ['draft','pending','approved'])
                ->latest('id')->limit(20)->get(['id','request_type','title','status','required_decisions','due_at'])->toArray(),
            'portfolios' => PremisePortfolioMembership::withoutGlobalScopes()->where('company_id', $property->company_id)
                ->where('premise_id', $property->id)->whereNull('valid_until')->with('portfolio:id,name,code')
                ->get()->map(fn ($membership) => [
                    'name' => $membership->portfolio?->name,
                    'code' => $membership->portfolio?->code,
                    'membership_type' => $membership->membership_type,
                ])->values()->all(),
            'finance_boundary' => 'No Titan Money balances, transactions, account identifiers, or statements are included.',
        ];
    }

    private function workflowCard(PremiseWorkflow $workflow): array
    {
        return [
            'id' => $workflow->id,
            'title' => $workflow->title,
            'status' => $workflow->status,
            'current_stage' => $workflow->currentStageLabel(),
            'completion_percent' => $workflow->completionPercent(),
            'due_at' => optional($workflow->due_at)->toIso8601String(),
            'assigned_user_id' => $workflow->assigned_user_id,
        ];
    }
}
