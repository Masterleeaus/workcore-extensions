<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Forms\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\Forms\Contracts\FormRepositoryContract;
use App\Domains\WorkCore\System\Modules\Forms\Services\FormFieldPolicy;
use App\Domains\WorkCore\System\Modules\Forms\Services\FormSubmissionPolicy;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class EloquentFormRepository extends TenantScopedRepository implements FormRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private FormFieldPolicy $fieldPolicy,
        private FormSubmissionPolicy $submissionPolicy,
    ) {
        parent::__construct($db, $tenant);
    }

    public function searchTemplates(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_form_templates')->where('company_id', $companyId)->whereNull('deleted_at');
        if ($q = trim((string) ($filters['q'] ?? ''))) {
            $query->where(fn (Builder $b) => $b->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%"));
        }
        foreach (['form_type','status','applies_to'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        return $query->orderBy('name')->orderByDesc('version')->paginate(max(1, min($perPage, 100)));
    }

    public function searchSubmissions(int $companyId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertCompany($companyId);
        $query = $this->db->table('tz_form_submissions as submissions')
            ->join('tz_form_templates as templates', 'templates.id', '=', 'submissions.template_id')
            ->where('submissions.company_id', $companyId)
            ->whereNull('submissions.deleted_at')
            ->select('submissions.*', 'templates.name as template_name', 'templates.form_type');
        foreach (['status','subject_type'] as $field) {
            if (! empty($filters[$field])) {
                $query->where("submissions.{$field}", $filters[$field]);
            }
        }
        foreach (['premises_public_id' => 'tz_premises', 'work_order_public_id' => 'tz_work_orders', 'asset_public_id' => 'tz_assets'] as $filter => $table) {
            if (! empty($filters[$filter])) {
                $column = match ($filter) { 'premises_public_id' => 'premises_id', 'work_order_public_id' => 'work_order_id', default => 'asset_id' };
                $id = $this->db->table($table)->where('company_id', $companyId)->where('public_id', $filters[$filter])->value('id');
                $query->where("submissions.{$column}", $id ?: -1);
            }
        }
        return $query->orderByDesc('submissions.started_at')->paginate(max(1, min($perPage, 100)));
    }

    public function createTemplate(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        return $this->db->transaction(function () use ($data, $companyId, $actorId): array {
            $code = Str::upper(trim((string) $data['code']));
            $version = (int) ($data['version'] ?? 1);
            if ($this->db->table('tz_form_templates')->where('company_id', $companyId)->where('code', $code)->where('version', $version)->exists()) {
                throw new InvalidArgumentException('A form template with this code and version already exists.');
            }
            $publicId = (string) Str::ulid();
            $templateId = $this->db->table('tz_form_templates')->insertGetId([
                'public_id' => $publicId, 'company_id' => $companyId, 'code' => $code, 'name' => (string) $data['name'],
                'form_type' => (string) ($data['form_type'] ?? 'checklist'), 'version' => $version, 'status' => 'draft',
                'applies_to' => (string) ($data['applies_to'] ?? 'general'), 'description' => $data['description'] ?? null,
                'settings' => isset($data['settings']) ? json_encode($data['settings'], JSON_THROW_ON_ERROR) : null,
                'active' => (bool) ($data['active'] ?? true), 'created_by_user_id' => $actorId, 'updated_by_user_id' => $actorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $fieldCount = 0;
            foreach ((array) ($data['sections'] ?? []) as $sectionOrder => $section) {
                $sectionId = $this->db->table('tz_form_template_sections')->insertGetId([
                    'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'template_id' => $templateId,
                    'title' => (string) $section['title'], 'instructions' => $section['instructions'] ?? null,
                    'sort_order' => (int) ($section['sort_order'] ?? $sectionOrder), 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ((array) ($section['fields'] ?? []) as $fieldOrder => $field) {
                    $this->insertField($templateId, $sectionId, (array) $field, $fieldOrder, $companyId);
                    $fieldCount++;
                }
            }
            foreach ((array) ($data['fields'] ?? []) as $fieldOrder => $field) {
                $this->insertField($templateId, null, (array) $field, $fieldOrder, $companyId);
                $fieldCount++;
            }
            return ['public_id' => $publicId, 'code' => $code, 'name' => $data['name'], 'version' => $version, 'status' => 'draft', 'field_count' => $fieldCount];
        }, 3);
    }

    public function publishTemplate(string $templatePublicId, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        return $this->db->transaction(function () use ($templatePublicId, $companyId, $actorId): array {
            $template = $this->db->table('tz_form_templates')->where('company_id', $companyId)->where('public_id', $templatePublicId)->whereNull('deleted_at')->lockForUpdate()->first();
            if (! $template) {
                throw new InvalidArgumentException('Form template does not belong to the active company.');
            }
            $count = $this->db->table('tz_form_template_fields')->where('company_id', $companyId)->where('template_id', $template->id)->count();
            $this->submissionPolicy->assertTemplatePublishable($count, (string) $template->status);
            $this->db->table('tz_form_templates')->where('id', $template->id)->update(['status' => 'published', 'published_at' => now(), 'updated_by_user_id' => $actorId, 'updated_at' => now()]);
            return ['public_id' => $templatePublicId, 'status' => 'published', 'field_count' => $count];
        }, 3);
    }

    public function startSubmission(array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $template = $this->db->table('tz_form_templates')->where('company_id', $companyId)->where('public_id', (string) $data['template_public_id'])->where('status', 'published')->where('active', true)->whereNull('deleted_at')->first();
        if (! $template) {
            throw new InvalidArgumentException('Published form template does not belong to the active company.');
        }
        $links = $this->resolveSubjectLinks($companyId, (string) ($data['subject_type'] ?? 'general'), $data['subject_public_id'] ?? null);
        $workerId = null;
        if (! empty($data['submitted_by_worker_public_id'])) {
            $workerId = $this->companyRecordId('tz_workers', $companyId, (string) $data['submitted_by_worker_public_id']);
        }
        $publicId = (string) Str::ulid();
        $this->db->table('tz_form_submissions')->insert([
            'public_id' => $publicId, 'company_id' => $companyId, 'template_id' => $template->id, 'template_version' => $template->version,
            'subject_type' => (string) ($data['subject_type'] ?? 'general'), 'subject_public_id' => $data['subject_public_id'] ?? null,
            'customer_id' => $links['customer_id'], 'premises_id' => $links['premises_id'], 'work_order_id' => $links['work_order_id'],
            'appointment_id' => $links['appointment_id'], 'asset_id' => $links['asset_id'], 'worker_id' => $links['worker_id'],
            'roster_shift_id' => $links['roster_shift_id'], 'submitted_by_worker_id' => $workerId,
            'status' => 'in_progress', 'started_at' => now(), 'notes' => $data['notes'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata'], JSON_THROW_ON_ERROR) : null,
            'created_by_user_id' => $actorId, 'updated_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return ['public_id' => $publicId, 'template_public_id' => $template->public_id, 'template_name' => $template->name, 'status' => 'in_progress', 'subject_type' => $data['subject_type'] ?? 'general', 'subject_public_id' => $data['subject_public_id'] ?? null];
    }

    public function saveResponse(string $submissionPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        return $this->db->transaction(function () use ($submissionPublicId, $data, $companyId, $actorId): array {
            $submission = $this->submission($submissionPublicId, $companyId, true);
            $this->submissionPolicy->assertEditable((string) $submission->status);
            $field = $this->db->table('tz_form_template_fields')->where('company_id', $companyId)->where('template_id', $submission->template_id)->where('public_id', (string) $data['field_public_id'])->first();
            if (! $field) {
                throw new InvalidArgumentException('Form field does not belong to this submission template.');
            }
            $values = $this->normaliseResponseValues((string) $field->field_type, $data['value'] ?? null);
            $existing = $this->db->table('tz_form_responses')->where('submission_id', $submission->id)->where('field_id', $field->id)->first(['id','public_id']);
            $publicId = $existing?->public_id ?? (string) Str::ulid();
            $record = $values + [
                'company_id' => $companyId, 'submission_id' => $submission->id, 'field_id' => $field->id,
                'result' => $data['result'] ?? null, 'notes' => $data['notes'] ?? null, 'recorded_by_user_id' => $actorId, 'updated_at' => now(),
            ];
            if ($existing) {
                $this->db->table('tz_form_responses')->where('id', $existing->id)->update($record);
                $responseId = $existing->id;
            } else {
                $record['public_id'] = $publicId; $record['created_at'] = now();
                $responseId = $this->db->table('tz_form_responses')->insertGetId($record);
            }
            $this->db->table('tz_form_evidence')->where('company_id', $companyId)->where('submission_id', $submission->id)->where('response_id', $responseId)->delete();
            foreach ((array) ($data['evidence'] ?? []) as $evidence) {
                $this->db->table('tz_form_evidence')->insert([
                    'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'submission_id' => $submission->id, 'response_id' => $responseId,
                    'evidence_type' => (string) ($evidence['evidence_type'] ?? 'photo'), 'storage_path' => (string) $evidence['storage_path'],
                    'filename' => $evidence['filename'] ?? null, 'mime_type' => $evidence['mime_type'] ?? null, 'size_bytes' => $evidence['size_bytes'] ?? null,
                    'caption' => $evidence['caption'] ?? null, 'captured_at' => $evidence['captured_at'] ?? null,
                    'uploaded_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return ['public_id' => $publicId, 'submission_public_id' => $submissionPublicId, 'field_public_id' => $field->public_id, 'result' => $data['result'] ?? null, 'evidence_count' => count((array) ($data['evidence'] ?? []))];
        }, 3);
    }

    public function submitSubmission(string $submissionPublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        return $this->db->transaction(function () use ($submissionPublicId, $data, $companyId, $actorId): array {
            $submission = $this->submission($submissionPublicId, $companyId, true);
            $this->submissionPolicy->assertEditable((string) $submission->status);
            $fields = $this->db->table('tz_form_template_fields')->where('company_id', $companyId)->where('template_id', $submission->template_id)->orderBy('sort_order')->get();
            $responses = $this->db->table('tz_form_responses')->where('company_id', $companyId)->where('submission_id', $submission->id)->get()->keyBy('field_id');
            $required = 0; $answered = 0; $failures = 0;
            foreach ($fields as $field) {
                $response = $responses->get($field->id);
                $value = $this->responseValue($response);
                if ((bool) $field->is_required) {
                    $required++;
                    if (! $this->fieldPolicy->isAnswered((string) $field->field_type, $value)) {
                        throw new InvalidArgumentException("Required field '{$field->label}' has not been answered.");
                    }
                }
                if ($this->fieldPolicy->isAnswered((string) $field->field_type, $value)) {
                    $answered++;
                }
                if ($response && $response->result === 'fail') {
                    $failures++;
                    $exists = $this->db->table('tz_form_failures')->where('submission_id', $submission->id)->where('response_id', $response->id)->exists();
                    if (! $exists && $field->failure_action !== 'none') {
                        $this->db->table('tz_form_failures')->insert([
                            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'submission_id' => $submission->id, 'response_id' => $response->id,
                            'severity' => $field->failure_severity, 'description' => trim((string) ($response->notes ?: "Failed item: {$field->label}")),
                            'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
            $outcome = $this->submissionPolicy->outcome($failures, $required, $answered);
            $scorable = max(1, $fields->count());
            $score = round((($scorable - $failures) / $scorable) * 100, 2);
            $this->db->table('tz_form_submissions')->where('id', $submission->id)->update([
                'status' => 'submitted', 'score' => $score, 'outcome' => $outcome, 'submitted_at' => now(),
                'signature_name' => $data['signature_name'] ?? null, 'signature_path' => $data['signature_path'] ?? null,
                'notes' => $data['notes'] ?? $submission->notes, 'updated_by_user_id' => $actorId, 'updated_at' => now(),
            ]);
            return ['public_id' => $submissionPublicId, 'status' => 'submitted', 'outcome' => $outcome, 'score' => $score, 'failure_count' => $failures];
        }, 3);
    }

    public function convertFailureToWorkOrder(string $failurePublicId, array $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        return $this->db->transaction(function () use ($failurePublicId, $data, $companyId, $actorId): array {
            $failure = $this->db->table('tz_form_failures as failures')
                ->join('tz_form_submissions as submissions', 'submissions.id', '=', 'failures.submission_id')
                ->join('tz_form_templates as templates', 'templates.id', '=', 'submissions.template_id')
                ->where('failures.company_id', $companyId)->where('failures.public_id', $failurePublicId)->lockForUpdate()
                ->first(['failures.*','submissions.customer_id','submissions.premises_id','submissions.work_order_id as source_work_order_id','templates.name as template_name']);
            if (! $failure) {
                throw new InvalidArgumentException('Form failure does not belong to the active company.');
            }
            if ($failure->work_order_id) {
                $existing = $this->db->table('tz_work_orders')->where('id', $failure->work_order_id)->first(['public_id','number','title']);
                return ['failure_public_id' => $failurePublicId, 'status' => 'converted', 'work_order' => (array) $existing];
            }
            $customerId = $failure->customer_id;
            if (! $customerId && $failure->premises_id) {
                $customerId = $this->db->table('tz_premises')->where('company_id', $companyId)->where('id', $failure->premises_id)->value('customer_id');
            }
            if (! $customerId && $failure->source_work_order_id) {
                $customerId = $this->db->table('tz_work_orders')->where('company_id', $companyId)->where('id', $failure->source_work_order_id)->value('customer_id');
            }
            if (! $customerId) {
                throw new InvalidArgumentException('A customer relationship is required before a failed form item can create a Work Order.');
            }
            $publicId = (string) Str::ulid();
            $number = 'WO-FORM-' . now()->format('ymd') . '-' . Str::upper(substr($publicId, -5));
            $priority = match ((string) $failure->severity) { 'life_safety','critical' => 'urgent', 'high' => 'high', 'low' => 'low', default => 'normal' };
            $title = trim((string) ($data['title'] ?? 'Form issue: ' . $failure->template_name));
            $workOrderId = $this->db->table('tz_work_orders')->insertGetId([
                'public_id' => $publicId, 'company_id' => $companyId, 'customer_id' => $customerId, 'premises_id' => $failure->premises_id,
                'number' => $number, 'title' => $title, 'description' => $data['description'] ?? $failure->description,
                'priority' => $priority, 'status' => 'ready', 'source' => 'form_failure', 'due_at' => $data['due_at'] ?? null,
                'created_by_user_id' => $actorId, 'updated_by_user_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->db->table('tz_work_order_status_history')->insert([
                'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'work_order_id' => $workOrderId,
                'from_status' => null, 'to_status' => 'ready', 'reason' => 'Created from failed form or checklist item.',
                'changed_by_user_id' => $actorId, 'changed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->db->table('tz_form_failures')->where('id', $failure->id)->update(['work_order_id' => $workOrderId, 'status' => 'converted', 'converted_at' => now(), 'updated_at' => now()]);
            return ['failure_public_id' => $failurePublicId, 'status' => 'converted', 'work_order' => ['public_id' => $publicId, 'number' => $number, 'title' => $title, 'priority' => $priority, 'status' => 'ready']];
        }, 3);
    }

    private function insertField(int $templateId, ?int $sectionId, array $field, int $order, int $companyId): void
    {
        $this->fieldPolicy->assertField($field);
        $this->db->table('tz_form_template_fields')->insert([
            'public_id' => (string) Str::ulid(), 'company_id' => $companyId, 'template_id' => $templateId, 'section_id' => $sectionId,
            'field_key' => (string) $field['field_key'], 'label' => (string) $field['label'], 'field_type' => (string) $field['field_type'],
            'is_required' => (bool) ($field['is_required'] ?? false), 'sort_order' => (int) ($field['sort_order'] ?? $order),
            'help_text' => $field['help_text'] ?? null, 'options' => isset($field['options']) ? json_encode($field['options'], JSON_THROW_ON_ERROR) : null,
            'validation_rules' => isset($field['validation_rules']) ? json_encode($field['validation_rules'], JSON_THROW_ON_ERROR) : null,
            'failure_action' => (string) ($field['failure_action'] ?? 'flag'), 'failure_severity' => (string) ($field['failure_severity'] ?? 'medium'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function resolveSubjectLinks(int $companyId, string $subjectType, mixed $publicId): array
    {
        $links = ['customer_id' => null, 'premises_id' => null, 'work_order_id' => null, 'appointment_id' => null, 'asset_id' => null, 'worker_id' => null, 'roster_shift_id' => null];
        if ($subjectType === 'general' || $publicId === null || $publicId === '') {
            return $links;
        }
        if ($subjectType === 'customer') {
            $links['customer_id'] = $this->companyRecordId('tz_customers', $companyId, (string) $publicId); return $links;
        }
        if ($subjectType === 'premises') {
            $record = $this->db->table('tz_premises')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->first(['id','customer_id']);
            if (! $record) throw new InvalidArgumentException('Premises does not belong to the active company.');
            $links['premises_id'] = $record->id; $links['customer_id'] = $record->customer_id; return $links;
        }
        if ($subjectType === 'work_order') {
            $record = $this->db->table('tz_work_orders')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->first(['id','customer_id','premises_id']);
            if (! $record) throw new InvalidArgumentException('Work Order does not belong to the active company.');
            $links['work_order_id'] = $record->id; $links['customer_id'] = $record->customer_id; $links['premises_id'] = $record->premises_id; return $links;
        }
        if ($subjectType === 'appointment') {
            $record = $this->db->table('tz_appointments')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->first(['id','customer_id','premises_id','work_order_id']);
            if (! $record) throw new InvalidArgumentException('Appointment does not belong to the active company.');
            $links['appointment_id'] = $record->id; $links['customer_id'] = $record->customer_id; $links['premises_id'] = $record->premises_id; $links['work_order_id'] = $record->work_order_id; return $links;
        }
        if ($subjectType === 'asset') {
            $record = $this->db->table('tz_assets')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at')->first(['id','premises_id']);
            if (! $record) throw new InvalidArgumentException('Asset does not belong to the active company.');
            $links['asset_id'] = $record->id; $links['premises_id'] = $record->premises_id;
            if ($record->premises_id) $links['customer_id'] = $this->db->table('tz_premises')->where('id', $record->premises_id)->value('customer_id');
            return $links;
        }
        if ($subjectType === 'worker') {
            $links['worker_id'] = $this->companyRecordId('tz_workers', $companyId, (string) $publicId); return $links;
        }
        if ($subjectType === 'roster_shift') {
            $record = $this->db->table('tz_roster_shifts')->where('company_id', $companyId)->where('public_id', $publicId)->first(['id','premises_id']);
            if (! $record) throw new InvalidArgumentException('Roster shift does not belong to the active company.');
            $links['roster_shift_id'] = $record->id; $links['premises_id'] = $record->premises_id;
            if ($record->premises_id) $links['customer_id'] = $this->db->table('tz_premises')->where('id', $record->premises_id)->value('customer_id');
            return $links;
        }
        throw new InvalidArgumentException('Unsupported form submission subject type.');
    }

    private function normaliseResponseValues(string $fieldType, mixed $value): array
    {
        $empty = ['value_text' => null, 'value_number' => null, 'value_boolean' => null, 'value_date' => null, 'value_json' => null];
        return match ($fieldType) {
            'number','rating','meter_reading' => array_replace($empty, ['value_number' => $value]),
            'boolean','pass_fail' => array_replace($empty, ['value_boolean' => is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)]),
            'date' => array_replace($empty, ['value_date' => $value]),
            'multiselect','photo','file','signature' => array_replace($empty, ['value_json' => json_encode($value, JSON_THROW_ON_ERROR)]),
            default => array_replace($empty, ['value_text' => is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR)]),
        };
    }

    private function responseValue(?object $response): mixed
    {
        if (! $response) return null;
        foreach (['value_text','value_number','value_boolean','value_date','value_json'] as $column) {
            if ($response->{$column} !== null) return $response->{$column};
        }
        return null;
    }

    private function submission(string $publicId, int $companyId, bool $lock = false): object
    {
        $query = $this->db->table('tz_form_submissions')->where('company_id', $companyId)->where('public_id', $publicId)->whereNull('deleted_at');
        if ($lock) $query->lockForUpdate();
        $submission = $query->first();
        if (! $submission) throw new InvalidArgumentException('Form submission does not belong to the active company.');
        return $submission;
    }

    private function companyRecordId(string $table, int $companyId, string $publicId): int
    {
        $query = $this->db->table($table)->where('company_id', $companyId)->where('public_id', $publicId);
        if (in_array($table, ['tz_workers','tz_customers'], true)) $query->whereNull('deleted_at');
        $id = $query->value('id');
        if (! $id) throw new InvalidArgumentException('Referenced record does not belong to the active company.');
        return (int) $id;
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) throw new RuntimeException('Repository actor does not match the active WorkCore actor.');
    }
}
