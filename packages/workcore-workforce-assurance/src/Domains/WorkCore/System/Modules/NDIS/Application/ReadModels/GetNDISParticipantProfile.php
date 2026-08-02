<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\NDIS\Application\ReadModels;

use App\Domains\WorkCore\System\Contracts\{PermissionResolverContract,TenantContextContract};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;

final class GetNDISParticipantProfile
{
    public function __construct(private TenantContextContract $tenant, private ConnectionInterface $db, private PermissionResolverContract $permissions) {}

    /** @return array<string,mixed> */
    public function execute(string $participantPublicId, ?int $companyId = null): array
    {
        $companyId ??= $this->tenant->companyId();
        $userId = $this->tenant->userId();
        if ($userId === null || ! $this->permissions->allows($userId, $companyId, (string) config('workcore.ndis.permissions.view'))) {
            throw new AuthorizationException('NDIS participant view permission is required.');
        }
        $participant = $this->db->table('tz_ndis_participants')->where('company_id', $companyId)
            ->where('public_id', $participantPublicId)->whereNull('deleted_at')->first()
            ?? throw new InvalidArgumentException('NDIS participant does not belong to the active company.');
        if ((bool) $participant->restricted_access && ! $this->permissions->allows($userId, $companyId, (string) config('workcore.ndis.permissions.restricted_notes'))) {
            throw new AuthorizationException('Restricted NDIS participant permission is required.');
        }
        $contacts = $this->db->table('tz_ndis_participant_contacts')->where('company_id', $companyId)->where('participant_id', $participant->id)
            ->whereNull('deleted_at')->orderByDesc('is_primary')->orderBy('display_name')->get()->map(static fn (object $row): array => [
                'public_id' => $row->public_id, 'contact_type' => $row->contact_type, 'display_name' => $row->display_name,
                'organisation_name' => $row->organisation_name, 'relationship' => $row->relationship, 'email' => $row->email,
                'phone' => $row->phone, 'is_primary' => (bool) $row->is_primary, 'consent_status' => $row->consent_status,
            ])->all();
        $plans = $this->db->table('tz_ndis_plans')->where('company_id', $companyId)->where('participant_id', $participant->id)
            ->whereNull('deleted_at')->orderByDesc('starts_on')->get()->map(static fn (object $row): array => [
                'public_id' => $row->public_id, 'plan_number' => $row->plan_number, 'status' => $row->status,
                'starts_on' => $row->starts_on, 'ends_on' => $row->ends_on, 'funding_management' => $row->funding_management,
                'total_budget' => (float) $row->total_budget, 'currency' => $row->currency,
            ])->all();
        $goals = $this->db->table('tz_ndis_participant_goals')->where('company_id', $companyId)->where('participant_id', $participant->id)
            ->whereNull('deleted_at')->orderBy('sort_order')->orderBy('created_at')->get()->map(static fn (object $row): array => [
                'public_id' => $row->public_id, 'status' => $row->status, 'title' => $row->title,
                'target_on' => $row->target_on, 'achieved_on' => $row->achieved_on, 'visibility' => $row->visibility,
            ])->all();
        $agreements = $this->db->table('tz_ndis_service_agreements')->where('company_id', $companyId)->where('participant_id', $participant->id)
            ->whereNull('deleted_at')->orderByDesc('starts_on')->get()->map(static fn (object $row): array => [
                'public_id' => $row->public_id, 'agreement_number' => $row->agreement_number, 'status' => $row->status,
                'starts_on' => $row->starts_on, 'ends_on' => $row->ends_on, 'funding_management' => $row->funding_management,
            ])->all();
        $workerMatches = $this->db->table('tz_ndis_worker_matches as m')->join('tz_workers as w', 'w.id', '=', 'm.worker_id')
            ->where('m.company_id', $companyId)->where('m.participant_id', $participant->id)->whereNull('m.deleted_at')
            ->orderByDesc('m.status')->get(['m.public_id','m.support_class','m.status','m.compatibility_score','w.public_id as worker_public_id','w.display_name'])
            ->map(static fn (object $row): array => (array) $row)->all();
        $incidents = $this->db->table('tz_ndis_incident_links as l')->join('tz_safety_incidents as i', 'i.id', '=', 'l.incident_id')
            ->where('l.company_id', $companyId)->where('l.participant_id', $participant->id)
            ->orderByDesc('i.occurred_at')->limit(20)->get(['l.public_id','l.participant_impact','l.restrictive_practice','l.reportable_to_ndis','i.public_id as incident_public_id','i.incident_number','i.status','i.severity','i.occurred_at'])
            ->map(static fn (object $row): array => (array) $row)->all();
        $noteCounts = $this->db->table('tz_ndis_case_notes')->where('company_id', $companyId)->where('participant_id', $participant->id)
            ->whereNull('deleted_at')->selectRaw('visibility, sensitivity, COUNT(*) as total')->groupBy('visibility','sensitivity')->get()
            ->map(static fn (object $row): array => ['visibility' => $row->visibility, 'sensitivity' => $row->sensitivity, 'total' => (int) $row->total])->all();

        return [
            'participant' => [
                'public_id' => $participant->public_id, 'participant_reference' => $participant->participant_reference,
                'ndis_number_masked' => $participant->ndis_number_last4 ? '••••' . $participant->ndis_number_last4 : null,
                'display_name' => $participant->display_name, 'preferred_name' => $participant->preferred_name,
                'date_of_birth' => $participant->date_of_birth, 'status' => $participant->status,
                'funding_management' => $participant->funding_management, 'consent_status' => $participant->consent_status,
                'risk_level' => $participant->risk_level, 'restricted_access' => (bool) $participant->restricted_access,
            ],
            'contacts' => $contacts, 'plans' => $plans, 'goals' => $goals, 'service_agreements' => $agreements,
            'worker_matches' => $workerMatches, 'incidents' => $incidents, 'case_note_counts' => $noteCounts,
        ];
    }
}
