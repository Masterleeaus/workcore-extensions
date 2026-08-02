<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Domain\Portal\PortalGrantState;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseAccessAuthorisation;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseIncident;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseManagementAuthority;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseOwnershipInterest;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePartyRole;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortfolioMembership;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseSpace;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortalEvent;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremisePortalGrant;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseWorkflow;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremisePortalGrantIssued;
use App\Domains\WorkCore\System\Modules\Premises\Events\PremisePortalGrantRevoked;

class PortalAccessService
{
    public function issue(Property $premise, array $attributes): array
    {
        $this->assertLinkedRecords($premise, $attributes);
        $allowed = config('managedpremises_portal.allowed_capabilities', []);
        $requested = array_values(array_unique($attributes['capabilities'] ?? config('managedpremises_portal.default_capabilities', [])));
        $invalid = array_values(array_diff($requested, $allowed));
        if ($invalid !== []) {
            throw ValidationException::withMessages(['capabilities' => 'Unsupported portal capability: ' . implode(', ', $invalid)]);
        }

        $bytes = max(32, (int) config('managedpremises_portal.token_bytes', 32));
        $token = rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
        $expiresAt = $attributes['expires_at'] ?? now()->addDays((int) config('managedpremises_portal.default_expiry_days', 30));

        $grant = PremisePortalGrant::create([
            'company_id' => $premise->company_id,
            'premise_id' => $premise->id,
            'portfolio_id' => $attributes['portfolio_id'] ?? null,
            'party_role_id' => $attributes['party_role_id'] ?? null,
            'occupancy_id' => $attributes['occupancy_id'] ?? null,
            'agreement_id' => $attributes['agreement_id'] ?? null,
            'granted_by_user_id' => $attributes['granted_by_user_id'] ?? $this->actorId(),
            'label' => $attributes['label'],
            'status' => 'invited',
            'token_hash' => hash('sha256', $token),
            'token_hint' => substr($token, -8),
            'capabilities' => $requested,
            'invited_at' => now(),
            'expires_at' => $expiresAt,
            'metadata' => Arr::except($attributes['metadata'] ?? [], ['token', 'secret', 'password']),
        ]);

        event(new PremisePortalGrantIssued($grant));
        return [$grant, $token];
    }

    public function resolve(string $token, ?Request $request = null): PremisePortalGrant
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 32) {
            abort(404);
        }

        $grant = DB::transaction(function () use ($token, $request) {
            $grant = PremisePortalGrant::withoutGlobalScopes()
                ->where('token_hash', hash('sha256', $token))
                ->with(['premise', 'portfolio', 'partyRole.space', 'occupancy.space', 'agreement.space', 'events'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($grant->expires_at && $grant->expires_at->isPast() && !PortalGrantState::isTerminal($grant->status)) {
                $grant->update(['status' => 'expired']);
                return $grant->fresh(['premise', 'portfolio', 'partyRole.space', 'occupancy.space', 'agreement.space']);
            }

            if (PortalGrantState::isTerminal($grant->status)) {
                return $grant;
            }

            if ($grant->status === 'invited') {
                $grant->update(['status' => 'active', 'activated_at' => now(), 'last_accessed_at' => now()]);
                $eventType = 'activated';
            } else {
                $grant->update(['last_accessed_at' => now()]);
                $eventType = 'accessed';
            }

            $this->recordEvent($grant, $eventType, $request);
            return $grant->fresh(['premise', 'portfolio', 'partyRole.space', 'occupancy.space', 'agreement.space']);
        });

        abort_if(in_array($grant->status, PortalGrantState::TERMINAL_STATUSES, true), 410, 'This portal link is no longer active.');
        return $grant;
    }

    public function revoke(PremisePortalGrant $grant): PremisePortalGrant
    {
        if (!PortalGrantState::canTransition($grant->status, 'revoked')) {
            throw ValidationException::withMessages(['status' => 'This portal grant cannot be revoked from its current state.']);
        }
        $grant->update(['status' => 'revoked', 'revoked_at' => now()]);
        event(new PremisePortalGrantRevoked($grant));
        return $grant;
    }

    public function viewData(PremisePortalGrant $grant): array
    {
        $premise = $grant->premise;
        $occupancy = $grant->occupancy;
        $agreement = $grant->agreement;
        $space = $occupancy?->space ?? $agreement?->space ?? $grant->partyRole?->space;

        $data = [
            'grant' => [
                'label' => $grant->label,
                'status' => $grant->status,
                'capabilities' => $grant->capabilities ?? [],
                'expires_at' => optional($grant->expires_at)->toIso8601String(),
            ],
        ];

        if ($grant->allows('view_premise_summary')) {
            $data['premise'] = [
                'name' => $premise->name,
                'profile_key' => $premise->profileKey(),
            ];
        }
        if ($grant->allows('view_space_summary') && $space) {
            $data['space'] = Arr::only($space->toArray(), config('managedpremises_portal.public_fields.space', []));
        }
        if ($grant->allows('view_occupancy_summary') && $occupancy) {
            $data['occupancy'] = Arr::only($occupancy->toArray(), config('managedpremises_portal.public_fields.occupancy', []));
        }
        if ($grant->allows('view_agreement_summary') && $agreement) {
            $data['agreement'] = Arr::only($agreement->toArray(), config('managedpremises_portal.public_fields.agreement', []));
        }
        if ($grant->allows('view_workflow_progress')) {
            $query = PremiseWorkflow::withoutGlobalScopes()->where('company_id', $grant->company_id)->where('premise_id', $grant->premise_id)
                ->whereIn('status', ['active', 'paused']);
            if ($grant->occupancy_id) $query->where('occupancy_id', $grant->occupancy_id);
            elseif ($grant->agreement_id) $query->where('agreement_id', $grant->agreement_id);
            $data['workflows'] = $query->limit(20)->get()->map(fn ($workflow) => [
                'title' => $workflow->title,
                'status' => $workflow->status,
                'current_stage' => $workflow->currentStageLabel(),
                'completion_percent' => $workflow->completionPercent(),
                'due_at' => optional($workflow->due_at)->toIso8601String(),
            ])->values()->all();
        }
        if ($grant->allows('view_access_authorisations') && $grant->party_role_id) {
            $data['access_authorisations'] = PremiseAccessAuthorisation::withoutGlobalScopes()
                ->where('company_id', $grant->company_id)->where('premise_id', $grant->premise_id)
                ->where('party_role_id', $grant->party_role_id)->where('status', 'active')
                ->get(['subject_name', 'purpose', 'access_scope', 'starts_at', 'ends_at'])
                ->toArray();
        }
        if ($grant->allows('view_owner_summary') && $grant->party_role_id && Schema::hasTable('pm_premise_ownership_interests')) {
            $data['owner_summary'] = [
                'ownership' => PremiseOwnershipInterest::withoutGlobalScopes()
                    ->where('company_id', $grant->company_id)->where('premise_id', $grant->premise_id)
                    ->where('party_role_id', $grant->party_role_id)->whereNull('valid_until')
                    ->get(['ownership_type', 'share_percent', 'is_primary', 'valid_from'])->toArray(),
                'management_authorities' => Schema::hasTable('pm_premise_management_authorities')
                    ? PremiseManagementAuthority::withoutGlobalScopes()
                        ->where('company_id', $grant->company_id)->where('premise_id', $grant->premise_id)
                        ->where('party_role_id', $grant->party_role_id)->whereIn('status', ['active','notice_given','suspended'])
                        ->get(['authority_type','status','start_date','end_date','authorised_actions','restrictions'])->toArray()
                    : [],
                'financial_boundary' => 'Statements, balances and transactions are available only through an authorised Titan Money integration.',
            ];
        }
        if ($grant->allows('view_portfolio_summary') && $grant->portfolio_id && Schema::hasTable('pm_premise_portfolio_memberships')) {
            $membership = PremisePortfolioMembership::withoutGlobalScopes()->where('company_id', $grant->company_id)
                ->where('portfolio_id', $grant->portfolio_id)->where('premise_id', $grant->premise_id)->whereNull('valid_until')->exists();
            $authorisedRole = in_array($grant->partyRole?->role, ['owner','property_manager','real_estate_agent','facility_manager'], true);
            if ($membership && $authorisedRole) {
                $portfolioPremiseIds = PremisePortfolioMembership::withoutGlobalScopes()->where('company_id', $grant->company_id)
                    ->where('portfolio_id', $grant->portfolio_id)->whereNull('valid_until')->pluck('premise_id');
                if ($grant->partyRole?->party_id) {
                    $authorisedPremiseIds = PremisePartyRole::withoutGlobalScopes()->where('company_id', $grant->company_id)
                        ->whereIn('premise_id', $portfolioPremiseIds)
                        ->where('party_type', $grant->partyRole->party_type)
                        ->where('party_id', $grant->partyRole->party_id)
                        ->whereIn('role', ['owner','property_manager','real_estate_agent','facility_manager'])
                        ->whereNull('valid_until')->pluck('premise_id');
                } else {
                    $authorisedPremiseIds = collect([$grant->premise_id]);
                }
                $premises = Property::withoutGlobalScopes()->where('company_id', $grant->company_id)->whereIn('id', $authorisedPremiseIds)
                    ->get(['id','company_id','name','profile_key']);
                $data['portfolio'] = [
                    'name' => $grant->portfolio?->name,
                    'premise_count' => $premises->count(),
                    'premises' => $premises->map(function ($item) {
                        $spaceQuery = PremiseSpace::withoutGlobalScopes()->where('company_id', $item->company_id)->where('premise_id', $item->id);
                        return [
                            'name' => $item->name,
                            'profile_key' => $item->profile_key,
                            'occupied_spaces' => (clone $spaceQuery)->where('availability_status', 'occupied')->count(),
                            'available_spaces' => (clone $spaceQuery)->where('availability_status', 'available')->count(),
                        ];
                    })->values()->all(),
                ];
            }
        }

        return $data;
    }

    public function submitIncident(PremisePortalGrant $grant, array $attributes, PremiseIncidentService $incidents): PremiseIncident
    {
        if (!$grant->allows('submit_incident')) {
            abort(403);
        }

        $incident = $incidents->create($grant->premise, [
            'reported_by_party_role_id' => $grant->party_role_id,
            'occupancy_id' => $grant->occupancy_id,
            'space_id' => $grant->occupancy?->space_id,
            'incident_type' => $attributes['incident_type'],
            'severity' => $attributes['severity'] ?? 'medium',
            'privacy_level' => 'standard',
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? null,
            'metadata' => ['source' => 'portal', 'portal_grant_id' => $grant->id],
        ]);
        $this->recordEvent($grant, 'incident_submitted', request(), ['incident_id' => $incident->id]);
        return $incident;
    }

    private function assertLinkedRecords(Property $premise, array $attributes): void
    {
        $tables = ['party_role_id' => 'pm_premise_party_roles', 'occupancy_id' => 'pm_premise_occupancies', 'agreement_id' => 'pm_premise_agreements'];
        if (!empty($attributes['portfolio_id'])) {
            $member = DB::table('pm_premise_portfolio_memberships')->where('portfolio_id', $attributes['portfolio_id'])
                ->where('company_id', $premise->company_id)->where('premise_id', $premise->id)->whereNull('valid_until')->exists();
            if (!$member) throw ValidationException::withMessages(['portfolio_id' => 'The selected portfolio does not contain this premise.']);
            if (empty($attributes['party_role_id'])) throw ValidationException::withMessages(['party_role_id' => 'Portfolio portal access requires a scoped owner or manager party.']);
            $role = DB::table('pm_premise_party_roles')->where('id', $attributes['party_role_id'])
                ->where('company_id', $premise->company_id)->where('premise_id', $premise->id)->whereNull('valid_until')->value('role');
            if (!in_array($role, ['owner','property_manager','real_estate_agent','facility_manager'], true)) throw ValidationException::withMessages(['party_role_id' => 'Portfolio portal access requires an owner or management role.']);
        }
        foreach ($tables as $field => $table) {
            if (empty($attributes[$field])) continue;
            $exists = DB::table($table)->where('id', $attributes[$field])->where('company_id', $premise->company_id)->where('premise_id', $premise->id)->exists();
            if (!$exists) throw ValidationException::withMessages([$field => 'The linked record does not belong to this premise.']);
        }

        if (!empty($attributes['occupancy_id']) && !empty($attributes['party_role_id'])) {
            $matches = DB::table('pm_premise_occupancies')->where('id', $attributes['occupancy_id'])
                ->where('party_role_id', $attributes['party_role_id'])->exists();
            if (!$matches) throw ValidationException::withMessages(['party_role_id' => 'The selected party is not linked to the selected occupancy.']);
        }
        if (!empty($attributes['agreement_id']) && !empty($attributes['occupancy_id'])) {
            $matches = DB::table('pm_premise_agreements')->where('id', $attributes['agreement_id'])
                ->where('occupancy_id', $attributes['occupancy_id'])->exists();
            if (!$matches) throw ValidationException::withMessages(['agreement_id' => 'The selected agreement is not linked to the selected occupancy.']);
        }
    }

    private function recordEvent(PremisePortalGrant $grant, string $eventType, ?Request $request = null, array $metadata = []): void
    {
        $ip = $request?->ip();
        PremisePortalEvent::create([
            'company_id' => $grant->company_id,
            'portal_grant_id' => $grant->id,
            'event_type' => $eventType,
            'occurred_at' => now(),
            'ip_hash' => $ip ? hash('sha256', $ip . '|' . (string) config('app.key')) : null,
            'user_agent_summary' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
            'metadata' => $metadata,
        ]);
    }

    private function actorId(): ?int
    {
        return function_exists('user') && user() ? user()->id : auth()->id();
    }
}
