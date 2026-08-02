<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assurance\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Assurance\Contracts\AssuranceRepositoryContract;
use App\Domains\WorkCore\System\Modules\Assurance\Services\InspectionScoringPolicy;
use App\Domains\WorkCore\System\Modules\Assurance\Services\RiskMatrix;
use App\Domains\WorkCore\System\Modules\Documents\Services\OperationalSubjectResolver;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use App\Domains\WorkCore\System\Validation\CompanyScopedReferenceValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EloquentAssuranceRepository extends TenantScopedRepository implements AssuranceRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private OperationalSubjectResolver $subjects,
        private CompanyScopedReferenceValidator $references,
        private InspectionScoringPolicy $scoring,
        private RiskMatrix $riskMatrix,
    ) {
        parent::__construct($db, $tenant);
    }

    public function searchInspections(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_inspections as i')
            ->leftJoin('tz_inspection_templates as t', 't.id', '=', 'i.template_id')
            ->leftJoin('tz_premises as p', 'p.id', '=', 'i.premises_id')
            ->leftJoin('tz_work_orders as wo', 'wo.id', '=', 'i.work_order_id')
            ->where('i.company_id', $companyId)->whereNull('i.deleted_at');
        foreach (['status', 'outcome', 'inspection_type', 'subject_type', 'subject_public_id'] as $field) {
            if (($filters[$field] ?? null) !== null) {
                $query->where("i.{$field}", (string) $filters[$field]);
            }
        }
        return $query->select([
            'i.public_id', 'i.inspection_number', 'i.inspection_type', 'i.subject_type', 'i.subject_public_id',
            'i.status', 'i.outcome', 'i.score', 'i.scheduled_at', 'i.started_at', 'i.completed_at', 'i.created_at',
            't.public_id as template_public_id', 't.name as template_name', 'p.public_id as premises_public_id',
            'p.name as premises_name', 'wo.public_id as work_order_public_id', 'wo.number as work_order_number',
        ])->orderByDesc('i.created_at')->paginate(max(1, min(100, $perPage)));
    }

    public function searchFindings(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_assurance_findings as f')
            ->leftJoin('tz_inspections as i', 'i.id', '=', 'f.inspection_id')
            ->leftJoin('tz_work_orders as wo', 'wo.id', '=', 'f.work_order_id')
            ->where('f.company_id', $companyId)->whereNull('f.deleted_at');
        foreach (['status', 'severity', 'finding_type', 'subject_type', 'subject_public_id'] as $field) {
            if (($filters[$field] ?? null) !== null) {
                $query->where("f.{$field}", (string) $filters[$field]);
            }
        }
        if (($filters['overdue'] ?? false) === true) {
            $query->whereNotNull('f.due_at')->where('f.due_at', '<', now())->whereNotIn('f.status', ['resolved', 'closed', 'cancelled']);
        }
        return $query->select([
            'f.public_id', 'f.finding_type', 'f.title', 'f.description', 'f.severity', 'f.status', 'f.due_at',
            'f.subject_type', 'f.subject_public_id', 'f.resolved_at', 'f.created_at',
            'i.public_id as inspection_public_id', 'i.inspection_number', 'wo.public_id as work_order_public_id', 'wo.number as work_order_number',
        ])->orderByRaw("CASE f.severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->orderBy('f.due_at')->paginate(max(1, min(100, $perPage)));
    }

    public function searchRisks(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_risk_register')->where('company_id', $companyId)->whereNull('deleted_at');
        foreach (['status', 'risk_level', 'category', 'subject_type', 'subject_public_id'] as $field) {
            if (($filters[$field] ?? null) !== null) {
                $query->where($field, (string) $filters[$field]);
            }
        }
        return $query->select([
            'public_id', 'subject_type', 'subject_public_id', 'category', 'title', 'description', 'likelihood',
            'consequence', 'risk_score', 'risk_level', 'status', 'controls', 'review_due_on', 'assessed_at', 'created_at',
        ])->orderByDesc('risk_score')->orderBy('review_due_on')->paginate(max(1, min(100, $perPage)));
    }

    public function searchIncidents(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_safety_incidents as i')
            ->leftJoin('tz_premises as p', 'p.id', '=', 'i.premises_id')
            ->leftJoin('tz_work_orders as wo', 'wo.id', '=', 'i.work_order_id')
            ->leftJoin('tz_assets as a', 'a.id', '=', 'i.asset_id')
            ->leftJoin('tz_workers as w', 'w.id', '=', 'i.worker_id')
            ->where('i.company_id', $companyId)
            ->whereNull('i.deleted_at');

        foreach (['status', 'severity'] as $field) {
            if (($filters[$field] ?? null) !== null) {
                $query->where("i.{$field}", (string) $filters[$field]);
            }
        }
        if (($filters['category'] ?? null) !== null) {
            $query->where('i.incident_type', (string) $filters['category']);
        }
        if (array_key_exists('notifiable', $filters)) {
            $query->where('i.is_reportable', (bool) $filters['notifiable']);
        }
        if (($filters['subject_type'] ?? null) !== null && ($filters['subject_public_id'] ?? null) !== null) {
            $subjectType = strtolower((string) $filters['subject_type']);
            $subjectPublicId = (string) $filters['subject_public_id'];
            match ($subjectType) {
                'premises' => $query->where('p.public_id', $subjectPublicId),
                'work_order' => $query->where('wo.public_id', $subjectPublicId),
                'asset' => $query->where('a.public_id', $subjectPublicId),
                'worker' => $query->where('w.public_id', $subjectPublicId),
                default => throw new InvalidArgumentException('Unsupported safety incident subject filter.'),
            };
        }

        return $query->select([
            'i.public_id', 'i.incident_number', 'i.incident_type as category', 'i.severity', 'i.status',
            'i.occurred_at', 'i.reported_at', 'i.description as summary', 'i.description',
            'i.immediate_actions as immediate_action', 'i.is_reportable as notifiable',
            'i.authority_reference as notified_authority', 'i.created_at',
            'p.public_id as premises_public_id', 'wo.public_id as work_order_public_id',
            'a.public_id as asset_public_id', 'w.public_id as worker_public_id',
        ])->orderByDesc('i.occurred_at')->paginate(max(1, min(100, $perPage)));
    }

    public function summary(int $companyId): array
    {
        $this->assertCompany($companyId);
        return [
            'pending_inspections' => $this->db->table('tz_inspections')->where('company_id', $companyId)
                ->whereNull('deleted_at')->whereIn('status', ['scheduled', 'in_progress'])->count(),
            'open_findings' => $this->db->table('tz_assurance_findings')->where('company_id', $companyId)
                ->whereNull('deleted_at')->whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count(),
            'overdue_corrective_actions' => $this->db->table('tz_corrective_actions')->where('company_id', $companyId)
                ->whereNull('deleted_at')->whereNotNull('due_on')->where('due_on', '<', now()->toDateString())
                ->whereNotIn('status', ['completed', 'verified', 'closed', 'cancelled'])->count(),
            'high_critical_risks' => $this->db->table('tz_risk_register')->where('company_id', $companyId)
                ->whereNull('deleted_at')->whereIn('risk_level', ['high', 'critical'])->where('status', 'open')->count(),
            'open_incidents' => $this->db->table('tz_safety_incidents')->where('company_id', $companyId)
                ->whereNull('deleted_at')->whereNotIn('status', ['resolved', 'closed', 'cancelled'])->count(),
        ];
    }

    public function createTemplate(array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $items = (array) ($data['items'] ?? []);
        if ($code === '' || $name === '' || $items === []) {
            throw new InvalidArgumentException('Inspection template code, name and at least one item are required.');
        }

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $code, $name, $items): array {
            $publicId = (string) Str::ulid();
            $templateId = $this->db->table('tz_inspection_templates')->insertGetId([
                'public_id' => $publicId, 'company_id' => $companyId, 'code' => $code, 'name' => $name,
                'description' => $data['description'] ?? null, 'inspection_type' => $data['inspection_type'] ?? 'quality',
                'status' => $data['status'] ?? 'draft', 'version' => (int) ($data['version'] ?? 1),
                'applicable_subject_types' => $this->json($data['applicable_subject_types'] ?? null),
                'created_by_user_id' => $actorId, 'updated_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($items as $index => $item) {
                $itemCode = trim((string) ($item['item_code'] ?? ''));
                $label = trim((string) ($item['label'] ?? ''));
                if ($itemCode === '' || $label === '') {
                    throw new InvalidArgumentException('Every inspection template item requires an item code and label.');
                }
                $this->db->table('tz_inspection_template_items')->insert([
                    'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'template_id' => $templateId,
                    'item_code' => $itemCode, 'label' => $label, 'guidance' => $item['guidance'] ?? null,
                    'response_type' => $item['response_type'] ?? 'pass_fail_na',
                    'is_required' => (bool) ($item['is_required'] ?? true), 'weight' => (float) ($item['weight'] ?? 1),
                    'severity' => $item['severity'] ?? 'medium', 'failure_action' => $item['failure_action'] ?? 'finding',
                    'sort_order' => (int) ($item['sort_order'] ?? $index), 'metadata' => $this->json($item['metadata'] ?? null),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $record = (array) $this->db->table('tz_inspection_templates')->where('id', $templateId)->first();
            $record['item_count'] = count($items);
            return $record;
        }, 3);
    }

    public function startInspection(array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $subjectType = (string) ($data['subject_type'] ?? '');
        $subjectPublicId = (string) ($data['subject_public_id'] ?? '');
        $this->subjects->resolve($companyId, $subjectType, $subjectPublicId);
        $template = ($data['template_public_id'] ?? null) ? $this->record('tz_inspection_templates', $companyId, (string) $data['template_public_id']) : null;
        $premises = ($data['premises_public_id'] ?? null) ? $this->record('tz_premises', $companyId, (string) $data['premises_public_id']) : null;
        $workOrder = ($data['work_order_public_id'] ?? null) ? $this->record('tz_work_orders', $companyId, (string) $data['work_order_public_id']) : null;
        $asset = ($data['asset_public_id'] ?? null) ? $this->record('tz_assets', $companyId, (string) $data['asset_public_id']) : null;
        $worker = ($data['inspector_worker_public_id'] ?? null) ? $this->record('tz_workers', $companyId, (string) $data['inspector_worker_public_id']) : null;
        if (($data['inspector_user_id'] ?? null) !== null) {
            $this->references->requireActiveMember($companyId, (int) $data['inspector_user_id'], 'inspector_user_id');
        }

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $subjectType, $subjectPublicId, $template, $premises, $workOrder, $asset, $worker): array {
            $publicId = (string) Str::ulid();
            $number = trim((string) ($data['inspection_number'] ?? '')) ?: 'INS-' . now()->format('ymd') . '-' . Str::upper(substr($publicId, -6));
            $startedAt = $data['started_at'] ?? now();
            $inspectionId = $this->db->table('tz_inspections')->insertGetId([
                'public_id' => $publicId, 'company_id' => $companyId, 'inspection_number' => $number,
                'template_id' => $template?->id, 'inspection_type' => $data['inspection_type'] ?? ($template?->inspection_type ?? 'quality'),
                'subject_type' => strtolower($subjectType), 'subject_public_id' => $subjectPublicId,
                'premises_id' => $premises?->id, 'work_order_id' => $workOrder?->id, 'asset_id' => $asset?->id,
                'inspector_worker_id' => $worker?->id, 'inspector_user_id' => $data['inspector_user_id'] ?? $actorId,
                'status' => 'in_progress', 'outcome' => 'pending', 'scheduled_at' => $data['scheduled_at'] ?? null,
                'started_at' => $startedAt, 'notes' => $data['notes'] ?? null, 'metadata' => $this->json($data['metadata'] ?? null),
                'created_by_user_id' => $actorId, 'updated_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($template !== null) {
                $items = $this->db->table('tz_inspection_template_items')->where('company_id', $companyId)
                    ->where('template_id', $template->id)->orderBy('sort_order')->get();
                foreach ($items as $item) {
                    $this->db->table('tz_inspection_results')->insert([
                        'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'inspection_id' => $inspectionId,
                        'template_item_id' => $item->id, 'item_code' => $item->item_code, 'item_label' => $item->label,
                        'result' => 'pending', 'severity' => $item->severity, 'weight' => $item->weight,
                        'evidence_count' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            $record = (array) $this->db->table('tz_inspections')->where('id', $inspectionId)->first();
            $record['result_count'] = $this->db->table('tz_inspection_results')->where('inspection_id', $inspectionId)->count();
            return $record;
        }, 3);
    }

    public function recordResult(string $inspectionPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $itemCode = trim((string) ($data['item_code'] ?? ''));
        $result = strtolower((string) ($data['result'] ?? ''));
        if ($itemCode === '' || ! in_array($result, ['pass', 'fail', 'na', 'pending'], true)) {
            throw new InvalidArgumentException('Inspection item code and a valid result are required.');
        }
        return $this->db->transaction(function () use ($inspectionPublicId, $data, $companyId, $actorId, $itemCode, $result): array {
            $inspection = $this->inspection($inspectionPublicId, $companyId, true);
            if (! in_array($inspection->status, ['scheduled', 'in_progress'], true)) {
                throw new InvalidArgumentException('Completed or cancelled inspections cannot be changed.');
            }
            $existing = $this->db->table('tz_inspection_results')->where('company_id', $companyId)
                ->where('inspection_id', $inspection->id)->where('item_code', $itemCode)->lockForUpdate()->first();
            if ($existing) {
                $this->db->table('tz_inspection_results')->where('id', $existing->id)->update([
                    'result' => $result, 'value_text' => $data['value_text'] ?? null,
                    'value_number' => $data['value_number'] ?? null, 'value_json' => $this->json($data['value_json'] ?? null),
                    'notes' => $data['notes'] ?? null, 'evidence_count' => (int) ($data['evidence_count'] ?? $existing->evidence_count),
                    'recorded_by_user_id' => $actorId, 'updated_at' => now(),
                ]);
                return (array) $this->db->table('tz_inspection_results')->where('id', $existing->id)->first();
            }
            $label = trim((string) ($data['item_label'] ?? ''));
            if ($label === '') {
                throw new InvalidArgumentException('Ad hoc inspection results require an item label.');
            }
            $publicId = (string) Str::ulid();
            $this->db->table('tz_inspection_results')->insert([
                'public_id' => $publicId, 'company_id' => $companyId, 'inspection_id' => $inspection->id,
                'item_code' => $itemCode, 'item_label' => $label, 'result' => $result,
                'severity' => $data['severity'] ?? 'medium', 'weight' => (float) ($data['weight'] ?? 1),
                'value_text' => $data['value_text'] ?? null, 'value_number' => $data['value_number'] ?? null,
                'value_json' => $this->json($data['value_json'] ?? null), 'notes' => $data['notes'] ?? null,
                'evidence_count' => (int) ($data['evidence_count'] ?? 0), 'recorded_by_user_id' => $actorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return (array) $this->db->table('tz_inspection_results')->where('public_id', $publicId)->first();
        }, 3);
    }

    public function completeInspection(string $inspectionPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        return $this->db->transaction(function () use ($inspectionPublicId, $data, $companyId, $actorId): array {
            $inspection = $this->inspection($inspectionPublicId, $companyId, true);
            if (! in_array($inspection->status, ['scheduled', 'in_progress'], true)) {
                throw new InvalidArgumentException('Inspection is not open for completion.');
            }
            $results = $this->db->table('tz_inspection_results')->where('company_id', $companyId)
                ->where('inspection_id', $inspection->id)->get();
            if ($results->isEmpty()) {
                throw new InvalidArgumentException('Inspection requires at least one result.');
            }
            if ($inspection->template_id) {
                $pendingRequired = $this->db->table('tz_inspection_results as r')
                    ->join('tz_inspection_template_items as ti', 'ti.id', '=', 'r.template_item_id')
                    ->where('r.company_id', $companyId)->where('r.inspection_id', $inspection->id)
                    ->where('ti.is_required', true)->where('r.result', 'pending')->count();
                if ($pendingRequired > 0) {
                    throw new InvalidArgumentException('All required inspection items must be completed.');
                }
            }
            $score = $this->scoring->score(array_map(static fn ($row): array => [
                'result' => $row->result, 'weight' => (float) $row->weight, 'severity' => $row->severity,
            ], $results->all()));
            $findingCount = 0;
            foreach ($results as $result) {
                if ($result->result !== 'fail') {
                    continue;
                }
                $exists = $this->db->table('tz_assurance_findings')->where('company_id', $companyId)
                    ->where('inspection_result_id', $result->id)->whereNull('deleted_at')->exists();
                if ($exists) {
                    continue;
                }
                $dueAt = $data['finding_due_at'] ?? $this->defaultDueAt((string) $result->severity);
                $this->db->table('tz_assurance_findings')->insert([
                    'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'inspection_id' => $inspection->id,
                    'inspection_result_id' => $result->id, 'subject_type' => $inspection->subject_type,
                    'subject_public_id' => $inspection->subject_public_id, 'finding_type' => 'inspection_nonconformance',
                    'title' => 'Inspection failure: ' . $result->item_label,
                    'description' => $result->notes ?: 'Inspection item did not meet the required standard.',
                    'severity' => $result->severity, 'status' => 'open', 'due_at' => $dueAt,
                    'work_order_id' => $inspection->work_order_id, 'created_by_user_id' => $actorId,
                    'updated_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $findingCount++;
            }
            $this->db->table('tz_inspections')->where('id', $inspection->id)->update([
                'status' => 'completed', 'outcome' => $score['outcome'], 'score' => $score['score'],
                'completed_at' => $data['completed_at'] ?? now(), 'notes' => $data['notes'] ?? $inspection->notes,
                'updated_by_user_id' => $actorId, 'updated_at' => now(),
            ]);
            $record = (array) $this->db->table('tz_inspections')->where('id', $inspection->id)->first();
            $record['failure_count'] = $score['failures'];
            $record['critical_failure_count'] = $score['critical_failures'];
            $record['findings_created'] = $findingCount;
            return $record;
        }, 3);
    }

    public function createFinding(array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $subjectType = (string) ($data['subject_type'] ?? '');
        $subjectPublicId = (string) ($data['subject_public_id'] ?? '');
        $this->subjects->resolve($companyId, $subjectType, $subjectPublicId);
        $severity = strtolower((string) ($data['severity'] ?? 'medium'));
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if ($title === '' || $description === '') {
            throw new InvalidArgumentException('Finding title and description are required.');
        }
        $dueAt = $data['due_at'] ?? null;
        if (in_array($severity, ['high', 'critical'], true) && $dueAt === null) {
            throw new InvalidArgumentException('High and critical findings require a due date.');
        }
        $inspection = ($data['inspection_public_id'] ?? null) ? $this->record('tz_inspections', $companyId, (string) $data['inspection_public_id']) : null;
        $result = ($data['inspection_result_public_id'] ?? null) ? $this->record('tz_inspection_results', $companyId, (string) $data['inspection_result_public_id']) : null;
        if ($result && $inspection && (int) $result->inspection_id !== (int) $inspection->id) {
            throw new InvalidArgumentException('Inspection result does not belong to the selected inspection.');
        }
        $workOrder = ($data['work_order_public_id'] ?? null) ? $this->record('tz_work_orders', $companyId, (string) $data['work_order_public_id']) : null;
        $worker = ($data['owner_worker_public_id'] ?? null) ? $this->record('tz_workers', $companyId, (string) $data['owner_worker_public_id']) : null;
        if (($data['owner_user_id'] ?? null) !== null) {
            $this->references->requireActiveMember($companyId, (int) $data['owner_user_id'], 'owner_user_id');
        }
        $publicId = (string) Str::ulid();
        $this->db->table('tz_assurance_findings')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'inspection_id' => $inspection?->id,
            'inspection_result_id' => $result?->id, 'subject_type' => strtolower($subjectType), 'subject_public_id' => $subjectPublicId,
            'finding_type' => $data['finding_type'] ?? 'nonconformance', 'title' => $title, 'description' => $description,
            'severity' => $severity, 'status' => 'open', 'due_at' => $dueAt, 'owner_worker_id' => $worker?->id,
            'owner_user_id' => $data['owner_user_id'] ?? null, 'work_order_id' => $workOrder?->id,
            'metadata' => $this->json($data['metadata'] ?? null), 'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_assurance_findings')->where('public_id', $publicId)->first();
    }

    public function createCorrectiveAction(string $findingPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $finding = $this->record('tz_assurance_findings', $companyId, $findingPublicId, true);
        $description = trim((string) ($data['action'] ?? $data['description'] ?? ''));
        if ($description === '') {
            throw new InvalidArgumentException('Corrective action description is required.');
        }
        $dueAt = $data['due_at'] ?? $finding->due_at;
        if (in_array($finding->severity, ['high', 'critical'], true) && $dueAt === null) {
            throw new InvalidArgumentException('Corrective actions for high and critical findings require a due date.');
        }
        $workOrder = ($data['work_order_public_id'] ?? null) ? $this->record('tz_work_orders', $companyId, (string) $data['work_order_public_id']) : null;
        $worker = ($data['assigned_worker_public_id'] ?? null) ? $this->record('tz_workers', $companyId, (string) $data['assigned_worker_public_id']) : null;
        if (($data['assigned_user_id'] ?? null) !== null) {
            $this->references->requireActiveMember($companyId, (int) $data['assigned_user_id'], 'assigned_user_id');
        }
        $publicId = (string) Str::ulid();
        $metadata = array_merge((array) ($data['metadata'] ?? []), [
            'assurance' => [
                'assigned_user_id' => $data['assigned_user_id'] ?? null,
                'evidence_required' => (bool) ($data['evidence_required'] ?? true),
                'verification_status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ],
        ]);
        $this->db->table('tz_corrective_actions')->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'finding_id' => $finding->id,
            'work_order_id' => $workOrder?->id,
            'assigned_worker_id' => $worker?->id,
            'title' => trim((string) ($data['title'] ?? $finding->title)),
            'description' => $description,
            'priority' => $data['priority'] ?? $this->priorityForSeverity((string) $finding->severity),
            'status' => 'open',
            'due_on' => $dueAt instanceof \DateTimeInterface ? $dueAt->format('Y-m-d') : ($dueAt !== null ? substr((string) $dueAt, 0, 10) : null),
            'metadata' => $this->json($metadata),
            'created_by_user_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_corrective_actions')->where('public_id', $publicId)->first();
    }

    public function updateCorrectiveAction(string $actionPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $requestedStatus = strtolower((string) ($data['status'] ?? ''));
        if (! in_array($requestedStatus, ['planned', 'open', 'in_progress', 'completed', 'verified', 'closed', 'cancelled'], true)) {
            throw new InvalidArgumentException('A valid corrective action status is required.');
        }
        $status = $requestedStatus === 'planned' ? 'open' : ($requestedStatus === 'verified' ? 'closed' : $requestedStatus);

        return $this->db->transaction(function () use ($actionPublicId, $data, $companyId, $actorId, $requestedStatus, $status): array {
            $action = $this->record('tz_corrective_actions', $companyId, $actionPublicId, true, true);
            if ($action->finding_id === null) {
                throw new InvalidArgumentException('Corrective action is not linked to an assurance finding.');
            }

            $metadata = $this->decodeMetadata($action->metadata ?? null);
            $assurance = (array) ($metadata['assurance'] ?? []);
            if (array_key_exists('notes', $data)) {
                $assurance['notes'] = $data['notes'];
            }
            if (in_array($requestedStatus, ['verified', 'closed'], true)) {
                $assurance['verification_status'] = 'verified';
                $assurance['verified_at'] = (string) ($data['verified_at'] ?? now());
                $assurance['verified_by_user_id'] = $actorId;
            }
            $metadata['assurance'] = $assurance;

            $updates = ['status' => $status, 'metadata' => $this->json($metadata), 'updated_at' => now()];
            if ($status === 'completed') {
                $updates['completed_at'] = $data['completed_at'] ?? now();
            }
            if (array_key_exists('closure_notes', $data) || array_key_exists('notes', $data)) {
                $updates['closure_notes'] = $data['closure_notes'] ?? $data['notes'];
            }
            $this->db->table('tz_corrective_actions')->where('id', $action->id)->update($updates);

            if (in_array($requestedStatus, ['verified', 'closed'], true)) {
                $open = $this->db->table('tz_corrective_actions')->where('company_id', $companyId)
                    ->where('finding_id', $action->finding_id)->whereNull('deleted_at')
                    ->whereNotIn('status', ['completed', 'closed', 'cancelled'])->exists();
                if (! $open) {
                    $this->db->table('tz_assurance_findings')->where('company_id', $companyId)->where('id', $action->finding_id)
                        ->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by_user_id' => $actorId,
                            'updated_by_user_id' => $actorId, 'updated_at' => now()]);
                }
            }
            return (array) $this->db->table('tz_corrective_actions')->where('id', $action->id)->first();
        }, 3);
    }

    public function recordRisk(array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $subjectType = (string) ($data['subject_type'] ?? '');
        $subjectPublicId = (string) ($data['subject_public_id'] ?? '');
        $this->subjects->resolve($companyId, $subjectType, $subjectPublicId);
        $assessment = $this->riskMatrix->assess((int) ($data['likelihood'] ?? 0), (int) ($data['consequence'] ?? 0));
        $title = trim((string) ($data['title'] ?? ''));
        $category = trim((string) ($data['category'] ?? ''));
        if ($title === '' || $category === '') {
            throw new InvalidArgumentException('Risk title and category are required.');
        }
        if (($data['owner_user_id'] ?? null) !== null) {
            $this->references->requireActiveMember($companyId, (int) $data['owner_user_id'], 'owner_user_id');
        }
        $publicId = (string) Str::ulid();
        $this->db->table('tz_risk_register')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'subject_type' => strtolower($subjectType),
            'subject_public_id' => $subjectPublicId, 'category' => $category, 'title' => $title,
            'description' => $data['description'] ?? null, 'likelihood' => (int) $data['likelihood'],
            'consequence' => (int) $data['consequence'], 'risk_score' => $assessment['score'], 'risk_level' => $assessment['level'],
            'status' => $data['status'] ?? 'open', 'controls' => $this->json($data['controls'] ?? null),
            'owner_user_id' => $data['owner_user_id'] ?? null, 'review_due_on' => $data['review_due_on'] ?? null,
            'assessed_by_user_id' => $actorId, 'assessed_at' => $data['assessed_at'] ?? now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_risk_register')->where('public_id', $publicId)->first();
    }

    public function recordIncident(array $data, int $companyId, int $actorId): array
    {
        $this->guard($data, $companyId, $actorId);
        $subjectType = strtolower((string) ($data['subject_type'] ?? ''));
        $subjectPublicId = (string) ($data['subject_public_id'] ?? '');
        $subject = $this->subjects->resolve($companyId, $subjectType, $subjectPublicId);
        $summary = trim((string) ($data['summary'] ?? $data['description'] ?? ''));
        $category = trim((string) ($data['category'] ?? $data['incident_type'] ?? ''));
        if ($summary === '' || $category === '' || ($data['occurred_at'] ?? null) === null) {
            throw new InvalidArgumentException('Incident category, summary and occurrence time are required.');
        }

        $premises = ($data['premises_public_id'] ?? null) ? $this->record('tz_premises', $companyId, (string) $data['premises_public_id']) : null;
        $workOrder = ($data['work_order_public_id'] ?? null) ? $this->record('tz_work_orders', $companyId, (string) $data['work_order_public_id']) : null;
        $asset = ($data['asset_public_id'] ?? null) ? $this->record('tz_assets', $companyId, (string) $data['asset_public_id']) : null;
        $worker = ($data['involved_worker_public_id'] ?? null) ? $this->record('tz_workers', $companyId, (string) $data['involved_worker_public_id']) : null;
        if ($premises === null && $subjectType === 'premises') $premises = $subject;
        if ($workOrder === null && $subjectType === 'work_order') $workOrder = $subject;
        if ($asset === null && $subjectType === 'asset') $asset = $subject;
        if ($worker === null && $subjectType === 'worker') $worker = $subject;

        $risk = ($data['related_risk_public_id'] ?? null) ? $this->record('tz_risk_register', $companyId, (string) $data['related_risk_public_id']) : null;
        $finding = ($data['related_finding_public_id'] ?? null) ? $this->record('tz_assurance_findings', $companyId, (string) $data['related_finding_public_id']) : null;
        $publicId = (string) Str::ulid();
        $number = trim((string) ($data['incident_number'] ?? '')) ?: 'INC-' . now()->format('Y') . '-' . Str::upper(substr($publicId, -8));
        $metadata = array_merge((array) ($data['metadata'] ?? []), [
            'assurance' => [
                'subject_type' => $subjectType,
                'subject_public_id' => $subjectPublicId,
                'related_risk_public_id' => $risk?->public_id,
                'related_finding_public_id' => $finding?->public_id,
                'notified_authority' => $data['notified_authority'] ?? null,
                'authority_notified_at' => $data['authority_notified_at'] ?? null,
            ],
        ]);
        $this->db->table('tz_safety_incidents')->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'incident_number' => $number,
            'premises_id' => $premises?->id,
            'work_order_id' => $workOrder?->id,
            'asset_id' => $asset?->id,
            'worker_id' => $worker?->id,
            'incident_type' => $category,
            'severity' => $data['severity'] ?? 'medium',
            'status' => 'reported',
            'occurred_at' => $data['occurred_at'],
            'reported_at' => now(),
            'description' => $summary . (($data['description'] ?? null) && $data['description'] !== $summary ? "\n\n" . $data['description'] : ''),
            'immediate_actions' => $data['immediate_action'] ?? $data['immediate_actions'] ?? null,
            'injury_occurred' => (bool) ($data['injury_occurred'] ?? false),
            'is_reportable' => (bool) ($data['notifiable'] ?? $data['is_reportable'] ?? false),
            'authority_reference' => $data['authority_reference'] ?? $data['notified_authority'] ?? null,
            'metadata' => $this->json($metadata),
            'reported_by_user_id' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (array) $this->db->table('tz_safety_incidents')->where('public_id', $publicId)->first();
    }

    private function inspection(string $publicId, int $companyId, bool $lock = false): object
    {
        return $this->record('tz_inspections', $companyId, $publicId, true, $lock);
    }

    private function record(string $table, int $companyId, string $publicId, bool $soft = false, bool $lock = false): object
    {
        $query = $this->db->table($table)->where('company_id', $companyId)->where('public_id', $publicId);
        if ($soft) {
            $query->whereNull('deleted_at');
        }
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->first() ?? throw new InvalidArgumentException("Referenced {$table} record does not belong to the active company.");
    }

    private function guard(array $data, int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId < 1 || $actorId !== $this->actorId()) {
            throw new InvalidArgumentException('Assurance action actor does not match the active user.');
        }
        if (array_key_exists('company_id', $data) || array_key_exists('companyId', $data)) {
            throw new InvalidArgumentException('Company authority cannot be supplied in an assurance action payload.');
        }
    }

    private function priorityForSeverity(string $severity): string
    {
        return match ($severity) {
            'critical' => 'urgent',
            'high' => 'high',
            'low' => 'low',
            default => 'normal',
        };
    }

    private function defaultDueAt(string $severity): Carbon
    {
        return now()->addDays(match ($severity) {
            'critical' => 1,
            'high' => 3,
            'medium' => 7,
            default => 14,
        });
    }

    /** @return array<string,mixed> */
    private function decodeMetadata(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function json(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
