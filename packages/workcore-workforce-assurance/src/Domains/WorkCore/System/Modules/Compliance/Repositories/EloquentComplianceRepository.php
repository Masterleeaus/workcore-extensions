<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Compliance\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Compliance\Contracts\ComplianceRepositoryContract;
use App\Domains\WorkCore\System\Modules\Compliance\Services\ComplianceStatusPolicy;
use App\Domains\WorkCore\System\Modules\Compliance\Services\SafetySeverityPolicy;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentComplianceRepository extends TenantScopedRepository implements ComplianceRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private ComplianceStatusPolicy $statusPolicy,
        private SafetySeverityPolicy $severityPolicy,
    ) {
        parent::__construct($db, $tenant);
    }

    public function search(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $q = $this->db->table('tz_compliance_obligations as o')
            ->join('tz_compliance_requirements as r', 'r.id', '=', 'o.requirement_id')
            ->leftJoin('tz_premises as p', 'p.id', '=', 'o.premises_id')
            ->leftJoin('tz_assets as a', 'a.id', '=', 'o.asset_id')
            ->leftJoin('tz_workers as w', 'w.id', '=', 'o.worker_id')
            ->where('o.company_id', $companyId)
            ->whereNull('o.deleted_at');
        if (! empty($filters['status'])) $q->where('o.status', $filters['status']);
        if (! empty($filters['category'])) $q->where('r.category', $filters['category']);
        if (! empty($filters['premises_public_id'])) $q->where('p.public_id', $filters['premises_public_id']);
        if (! empty($filters['asset_public_id'])) $q->where('a.public_id', $filters['asset_public_id']);
        if (! empty($filters['worker_public_id'])) $q->where('w.public_id', $filters['worker_public_id']);
        if (! empty($filters['due_before'])) $q->whereDate('o.due_on', '<=', $filters['due_before']);
        if (! empty($filters['q'])) {
            $term = '%' . trim((string) $filters['q']) . '%';
            $q->where(fn ($x) => $x->where('r.name', 'like', $term)->orWhere('r.code', 'like', $term)->orWhere('p.name', 'like', $term)->orWhere('a.name', 'like', $term));
        }
        return $q->select([
            'o.public_id', 'o.subject_type', 'o.status', 'o.due_on', 'o.completed_on', 'o.last_checked_at', 'o.notes',
            'r.public_id as requirement_public_id', 'r.code as requirement_code', 'r.name as requirement_name', 'r.category',
            'r.jurisdiction_country', 'r.jurisdiction_region', 'p.public_id as premises_public_id', 'p.name as premises_name',
            'a.public_id as asset_public_id', 'a.name as asset_name', 'w.public_id as worker_public_id', 'w.display_name as worker_name',
        ])->orderByRaw("CASE o.status WHEN 'overdue' THEN 0 WHEN 'non_compliant' THEN 1 WHEN 'due' THEN 2 ELSE 3 END")
          ->orderBy('o.due_on')->paginate(max(1, min(100, $perPage)));
    }

    public function createRequirement(array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId, $actorId);
        $id = (string) Str::ulid();
        $this->db->table('tz_compliance_requirements')->insert([
            'public_id' => $id, 'company_id' => $companyId, 'code' => strtoupper((string) $d['code']),
            'name' => $d['name'], 'description' => $d['description'] ?? null, 'category' => $d['category'] ?? 'general',
            'jurisdiction_country' => strtoupper((string) ($d['jurisdiction_country'] ?? config('workcore.context.default_country'))),
            'jurisdiction_region' => $d['jurisdiction_region'] ?? null, 'authority_name' => $d['authority_name'] ?? null,
            'applies_to' => $d['applies_to'] ?? 'premises', 'frequency_months' => $d['frequency_months'] ?? null,
            'evidence_required' => (bool) ($d['evidence_required'] ?? true), 'is_active' => (bool) ($d['is_active'] ?? true),
            'metadata' => $this->json($d['metadata'] ?? null), 'created_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_compliance_requirements')->where('public_id', $id)->first();
    }

    public function createObligation(array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId, $actorId);
        $requirement = $this->record('tz_compliance_requirements', $companyId, $d['requirement_public_id'] ?? null, true);
        if (! $requirement) throw new InvalidArgumentException('A company compliance requirement is required.');
        $subject = $this->subjectRefs($d, $companyId);
        $id = (string) Str::ulid();
        $status = $this->statusPolicy->derive($d['completed_on'] ?? null, $d['due_on'] ?? null);
        $this->db->table('tz_compliance_obligations')->insert(array_merge([
            'public_id' => $id, 'company_id' => $companyId, 'requirement_id' => $requirement->id,
            'subject_type' => $subject['subject_type'], 'status' => $status, 'due_on' => $d['due_on'] ?? null,
            'completed_on' => $d['completed_on'] ?? null, 'last_checked_at' => $d['last_checked_at'] ?? null,
            'responsible_worker_id' => $this->record('tz_workers', $companyId, $d['responsible_worker_public_id'] ?? null, true)?->id,
            'form_submission_id' => $this->record('tz_form_submissions', $companyId, $d['form_submission_public_id'] ?? null)?->id,
            'notes' => $d['notes'] ?? null, 'metadata' => $this->json($d['metadata'] ?? null),
            'created_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ], $subject));
        $this->history($companyId, 'obligation', (int) $this->db->table('tz_compliance_obligations')->where('public_id', $id)->value('id'), null, $status, null, $actorId);
        return (array) $this->db->table('tz_compliance_obligations')->where('public_id', $id)->first();
    }

    public function changeObligationStatus(string $publicId, array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId, $actorId);
        $o = $this->record('tz_compliance_obligations', $companyId, $publicId, true);
        if (! $o) throw new InvalidArgumentException('Compliance obligation does not belong to active company.');
        $to = (string) $d['status'];
        $this->statusPolicy->assertTransition((string) $o->status, $to, $d['reason'] ?? null);
        $this->db->table('tz_compliance_obligations')->where('id', $o->id)->update([
            'status' => $to, 'completed_on' => $to === 'compliant' ? ($d['completed_on'] ?? now()->toDateString()) : $o->completed_on,
            'last_checked_at' => now(), 'notes' => $d['notes'] ?? $o->notes, 'updated_at' => now(),
        ]);
        $this->history($companyId, 'obligation', (int) $o->id, (string) $o->status, $to, $d['reason'] ?? null, $actorId);
        return (array) $this->db->table('tz_compliance_obligations')->where('id', $o->id)->first();
    }

    public function recordCertificate(array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId, $actorId);
        $o = $this->record('tz_compliance_obligations', $companyId, $d['obligation_public_id'] ?? null, true);
        if (! $o) throw new InvalidArgumentException('A compliance obligation is required for a certificate.');
        $id = (string) Str::ulid();
        $status = ! empty($d['expires_on']) && strtotime((string) $d['expires_on']) < strtotime(date('Y-m-d')) ? 'expired' : ($d['status'] ?? 'valid');
        return $this->db->transaction(function () use ($d, $companyId, $actorId, $o, $id, $status): array {
            $this->db->table('tz_compliance_certificates')->insert([
                'public_id' => $id, 'company_id' => $companyId, 'obligation_id' => $o->id,
                'premises_id' => $o->premises_id, 'asset_id' => $o->asset_id, 'worker_id' => $o->worker_id,
                'certificate_type' => $d['certificate_type'], 'certificate_number' => $d['certificate_number'] ?? null,
                'issuer' => $d['issuer'] ?? null, 'issued_on' => $d['issued_on'] ?? null, 'expires_on' => $d['expires_on'] ?? null,
                'status' => $status, 'document_path' => $d['document_path'] ?? null, 'notes' => $d['notes'] ?? null,
                'metadata' => $this->json($d['metadata'] ?? null), 'created_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($status === 'valid') {
                $this->db->table('tz_compliance_obligations')->where('id', $o->id)->update(['status' => 'compliant', 'completed_on' => $d['issued_on'] ?? now()->toDateString(), 'last_checked_at' => now(), 'updated_at' => now()]);
                $this->history($companyId, 'obligation', (int) $o->id, (string) $o->status, 'compliant', 'Valid certificate recorded.', $actorId);
            }
            return (array) $this->db->table('tz_compliance_certificates')->where('public_id', $id)->first();
        });
    }

    public function recordHazard(array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId, $actorId);
        $severity = (string) ($d['severity'] ?? 'medium');
        $this->severityPolicy->priority($severity);
        $likelihood = max(1, min(5, (int) ($d['likelihood'] ?? 1)));
        $id = (string) Str::ulid();
        $this->db->table('tz_hazards')->insert([
            'public_id' => $id, 'company_id' => $companyId,
            'premises_id' => $this->record('tz_premises', $companyId, $d['premises_public_id'] ?? null)?->id,
            'asset_id' => $this->record('tz_assets', $companyId, $d['asset_public_id'] ?? null, true)?->id,
            'work_order_id' => $this->record('tz_work_orders', $companyId, $d['work_order_public_id'] ?? null, true)?->id,
            'form_failure_id' => $this->record('tz_form_failures', $companyId, $d['form_failure_public_id'] ?? null)?->id,
            'title' => $d['title'], 'description' => $d['description'], 'category' => $d['category'] ?? 'general',
            'severity' => $severity, 'likelihood' => $likelihood, 'risk_score' => $this->riskScore($severity, $likelihood),
            'status' => 'open', 'identified_at' => $d['identified_at'] ?? now(),
            'owner_worker_id' => $this->record('tz_workers', $companyId, $d['owner_worker_public_id'] ?? null, true)?->id,
            'controls' => $d['controls'] ?? null, 'metadata' => $this->json($d['metadata'] ?? null),
            'created_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_hazards')->where('public_id', $id)->first();
    }

    public function changeHazardStatus(string $publicId, array $d, int $companyId, int $actorId): array
    {
        return $this->changeSafetyStatus('tz_hazards', 'hazard', $publicId, $d, $companyId, $actorId);
    }

    public function recordIncident(array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId, $actorId);
        $severity = (string) ($d['severity'] ?? 'medium');
        $this->severityPolicy->priority($severity);
        $id = (string) Str::ulid();
        $this->db->table('tz_safety_incidents')->insert([
            'public_id' => $id, 'company_id' => $companyId, 'incident_number' => 'INC-' . date('Y') . '-' . substr($id, -8),
            'premises_id' => $this->record('tz_premises', $companyId, $d['premises_public_id'] ?? null)?->id,
            'asset_id' => $this->record('tz_assets', $companyId, $d['asset_public_id'] ?? null, true)?->id,
            'worker_id' => $this->record('tz_workers', $companyId, $d['worker_public_id'] ?? null, true)?->id,
            'work_order_id' => $this->record('tz_work_orders', $companyId, $d['work_order_public_id'] ?? null, true)?->id,
            'incident_type' => $d['incident_type'] ?? 'safety', 'severity' => $severity, 'status' => 'reported',
            'occurred_at' => $d['occurred_at'], 'reported_at' => now(), 'description' => $d['description'],
            'immediate_actions' => $d['immediate_actions'] ?? null, 'injury_occurred' => (bool) ($d['injury_occurred'] ?? false),
            'is_reportable' => (bool) ($d['is_reportable'] ?? false), 'authority_reference' => $d['authority_reference'] ?? null,
            'metadata' => $this->json($d['metadata'] ?? null), 'reported_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_safety_incidents')->where('public_id', $id)->first();
    }

    public function changeIncidentStatus(string $publicId, array $d, int $companyId, int $actorId): array
    {
        return $this->changeSafetyStatus('tz_safety_incidents', 'incident', $publicId, $d, $companyId, $actorId);
    }

    public function createCorrectiveAction(array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId, $actorId);
        $obligation = $this->record('tz_compliance_obligations', $companyId, $d['obligation_public_id'] ?? null, true);
        $hazard = $this->record('tz_hazards', $companyId, $d['hazard_public_id'] ?? null, true);
        $incident = $this->record('tz_safety_incidents', $companyId, $d['incident_public_id'] ?? null, true);
        if (! $obligation && ! $hazard && ! $incident) throw new InvalidArgumentException('A corrective action must link to an obligation, hazard or incident.');
        $severity = (string) ($d['severity'] ?? ($hazard->severity ?? $incident->severity ?? 'medium'));
        $id = (string) Str::ulid();
        $this->db->table('tz_corrective_actions')->insert([
            'public_id' => $id, 'company_id' => $companyId, 'obligation_id' => $obligation?->id,
            'hazard_id' => $hazard?->id, 'incident_id' => $incident?->id,
            'form_failure_id' => $this->record('tz_form_failures', $companyId, $d['form_failure_public_id'] ?? null)?->id,
            'assigned_worker_id' => $this->record('tz_workers', $companyId, $d['assigned_worker_public_id'] ?? null, true)?->id,
            'title' => $d['title'], 'description' => $d['description'] ?? null,
            'priority' => $d['priority'] ?? $this->severityPolicy->priority($severity), 'status' => 'open',
            'due_on' => $d['due_on'] ?? null, 'metadata' => $this->json($d['metadata'] ?? null),
            'created_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_corrective_actions')->where('public_id', $id)->first();
    }

    public function convertCorrectiveActionToWorkOrder(string $publicId, array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId, $actorId);
        $a = $this->record('tz_corrective_actions', $companyId, $publicId, true);
        if (! $a) throw new InvalidArgumentException('Corrective action does not belong to active company.');
        if ($a->work_order_id) {
            return (array) $this->db->table('tz_work_orders')->where('company_id', $companyId)->where('id', $a->work_order_id)->first();
        }
        $hazard = $a->hazard_id ? $this->db->table('tz_hazards')->where('id', $a->hazard_id)->first() : null;
        $incident = $a->incident_id ? $this->db->table('tz_safety_incidents')->where('id', $a->incident_id)->first() : null;
        $obligation = $a->obligation_id ? $this->db->table('tz_compliance_obligations')->where('id', $a->obligation_id)->first() : null;
        $premisesId = $d['premises_public_id'] ?? null;
        $premises = $premisesId ? $this->record('tz_premises', $companyId, $premisesId, true) : null;
        if (! $premises) {
            $pid = $hazard->premises_id ?? $incident->premises_id ?? $obligation->premises_id ?? null;
            $premises = $pid ? $this->db->table('tz_premises')->where('company_id', $companyId)->where('id', $pid)->whereNull('deleted_at')->first() : null;
        }
        $customer = $this->record('tz_customers', $companyId, $d['customer_public_id'] ?? null, true);
        if (! $customer && $premises?->customer_id) $customer = $this->db->table('tz_customers')->where('company_id', $companyId)->where('id', $premises->customer_id)->whereNull('deleted_at')->first();
        if (! $customer) throw new InvalidArgumentException('A customer or customer-linked premises is required to create a corrective Work Order.');
        $workOrderPublicId = (string) Str::ulid();
        $number = 'CMP-' . date('Ymd') . '-' . substr($workOrderPublicId, -6);
        return $this->db->transaction(function () use ($companyId, $actorId, $a, $premises, $customer, $workOrderPublicId, $number, $d): array {
            $this->db->table('tz_work_orders')->insert([
                'public_id' => $workOrderPublicId, 'company_id' => $companyId, 'customer_id' => $customer->id,
                'premises_id' => $premises?->id, 'number' => $number, 'title' => $d['title'] ?? $a->title,
                'description' => $d['description'] ?? $a->description, 'priority' => $a->priority, 'status' => 'ready',
                'source' => 'compliance_corrective_action', 'due_at' => $a->due_on, 'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $workOrderId = (int) $this->db->table('tz_work_orders')->where('public_id', $workOrderPublicId)->value('id');
            $this->db->table('tz_work_order_status_history')->insert([
                'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'work_order_id' => $workOrderId,
                'from_status' => null, 'to_status' => 'ready', 'reason' => 'Created from WorkCore corrective action.',
                'changed_by_user_id' => $actorId, 'changed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->db->table('tz_corrective_actions')->where('id', $a->id)->update(['work_order_id' => $workOrderId, 'status' => 'in_progress', 'updated_at' => now()]);
            return (array) $this->db->table('tz_work_orders')->where('id', $workOrderId)->first();
        });
    }

    private function changeSafetyStatus(string $table, string $subjectType, string $publicId, array $d, int $companyId, int $actorId): array
    {
        $this->actor($companyId, $actorId);
        $row = $this->record($table, $companyId, $publicId, true);
        if (! $row) throw new InvalidArgumentException(ucfirst($subjectType) . ' does not belong to active company.');
        $to = (string) $d['status'];
        $this->severityPolicy->assertClosure((string) $row->status, $to, $d['reason'] ?? null);
        $updates = ['status' => $to, 'updated_at' => now()];
        if ($table === 'tz_hazards') {
            if ($to === 'controlled') $updates['controlled_at'] = now();
            if (in_array($to, ['resolved', 'closed'], true)) $updates['resolved_at'] = now();
            if (array_key_exists('controls', $d)) $updates['controls'] = $d['controls'];
        } else {
            if (in_array($to, ['resolved', 'closed'], true)) $updates['resolved_at'] = now();
            if (array_key_exists('investigation_findings', $d)) $updates['investigation_findings'] = $d['investigation_findings'];
        }
        $this->db->table($table)->where('id', $row->id)->update($updates);
        $this->history($companyId, $subjectType, (int) $row->id, (string) $row->status, $to, $d['reason'] ?? null, $actorId);
        return (array) $this->db->table($table)->where('id', $row->id)->first();
    }

    private function subjectRefs(array $d, int $companyId): array
    {
        $type = (string) ($d['subject_type'] ?? 'company');
        $premises = $this->record('tz_premises', $companyId, $d['premises_public_id'] ?? null, true);
        $asset = $this->record('tz_assets', $companyId, $d['asset_public_id'] ?? null, true);
        $worker = $this->record('tz_workers', $companyId, $d['worker_public_id'] ?? null, true);
        $valid = ['company', 'premises', 'asset', 'worker'];
        if (! in_array($type, $valid, true)) throw new InvalidArgumentException('Unsupported compliance subject type.');
        if ($type === 'premises' && ! $premises) throw new InvalidArgumentException('Premises subject requires premises_public_id.');
        if ($type === 'asset' && ! $asset) throw new InvalidArgumentException('Asset subject requires asset_public_id.');
        if ($type === 'worker' && ! $worker) throw new InvalidArgumentException('Worker subject requires worker_public_id.');
        return ['subject_type' => $type, 'premises_id' => $premises?->id, 'asset_id' => $asset?->id, 'worker_id' => $worker?->id];
    }

    private function riskScore(string $severity, int $likelihood): int
    {
        $impact = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4, 'life_safety' => 5][$severity] ?? 2;
        return $impact * $likelihood;
    }

    private function record(string $table, int $companyId, mixed $publicId, bool $soft = false): ?object
    {
        if (! $publicId) return null;
        $q = $this->db->table($table)->where('company_id', $companyId)->where('public_id', (string) $publicId);
        if ($soft) $q->whereNull('deleted_at');
        $r = $q->first();
        if (! $r) throw new InvalidArgumentException("Referenced {$table} record does not belong to active company.");
        return $r;
    }

    private function actor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId < 1 || $actorId !== $this->actorId()) throw new InvalidArgumentException('Compliance action actor does not match active user.');
    }

    private function history(int $companyId, string $type, int $id, ?string $from, string $to, ?string $reason, int $actorId): void
    {
        $this->db->table('tz_compliance_status_history')->insert([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'subject_type' => $type, 'subject_id' => $id,
            'from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'actor_user_id' => $actorId,
            'changed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function json(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }
}
