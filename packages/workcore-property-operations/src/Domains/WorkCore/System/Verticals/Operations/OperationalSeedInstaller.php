<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Operations;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;

final class OperationalSeedInstaller
{
    /** @var array<string,array{table:string,retire:array<string,mixed>,natural:list<string>}> */
    private array $managedTypes = [
        'service_category' => ['table' => 'tz_service_categories', 'retire' => ['status' => 'inactive'], 'natural' => ['company_id', 'name']],
        'service' => ['table' => 'tz_services', 'retire' => ['status' => 'inactive'], 'natural' => ['company_id', 'code']],
        'job_template' => ['table' => 'tz_job_templates', 'retire' => ['status' => 'inactive'], 'natural' => ['company_id', 'service_id', 'name']],
        'task_template' => ['table' => 'tz_task_templates', 'retire' => ['active' => false], 'natural' => ['company_id', 'job_template_id', 'name']],
        'skill' => ['table' => 'tz_skills', 'retire' => ['active' => false], 'natural' => ['company_id', 'code']],
        'form_template' => ['table' => 'tz_form_templates', 'retire' => ['active' => false], 'natural' => ['company_id', 'code', 'version']],
    ];

    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param array<string,mixed> $operations
     * @param list<array{public_id:string,key:string,vertical_key:string}> $businessLines
     * @return array<string,mixed>
     */
    public function synchronise(int $companyId, int $actorId, array $operations, array $businessLines): array
    {
        $lineIds = $this->businessLineIds($companyId, $businessLines);
        $planned = [];
        $counts = ['categories' => 0, 'services' => 0, 'job_templates' => 0, 'tasks' => 0, 'skills' => 0, 'forms' => 0];
        $categoryIds = [];

        foreach ((array) ($operations['categories'] ?? []) as $category) {
            if (! is_array($category)) { continue; }
            $vertical = (string) $category['vertical_key'];
            $seedKey = 'operations.category.' . (string) $category['key'];
            $id = $this->upsertSeededRecord($companyId, $vertical, $seedKey, 'service_category', [
                'company_id' => $companyId,
                'business_line_id' => null,
                'parent_id' => null,
                'name' => (string) $category['name'],
                'description' => $category['description'] ?? null,
                'sort_order' => (int) ($category['sort_order'] ?? 0),
                'status' => 'active',
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
            ]);
            $categoryIds[(string) $category['key']] = $id;
            $planned[$this->identity($vertical, 'service_category', $seedKey)] = true;
            $counts['categories']++;
        }

        foreach ((array) ($operations['services'] ?? []) as $service) {
            if (! is_array($service)) { continue; }
            $vertical = (string) $service['vertical_key'];
            $serviceKey = (string) $service['key'];
            $businessLineId = $this->businessLineId($lineIds, $service);
            $seedKey = 'operations.service.' . $serviceKey;
            $serviceId = $this->upsertSeededRecord($companyId, $vertical, $seedKey, 'service', [
                'company_id' => $companyId,
                'business_line_id' => $businessLineId,
                'category_id' => $categoryIds[(string) $service['category_key']] ?? null,
                'code' => $serviceKey,
                'name' => (string) $service['name'],
                'description' => $service['description'] ?? null,
                'default_duration_minutes' => $service['default_duration_minutes'] ?? null,
                'pricing_model' => (string) ($service['pricing_model'] ?? 'fixed'),
                'base_price' => $service['base_price'] ?? null,
                'tax_code' => $service['tax_code'] ?? null,
                'status' => 'active',
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
            ]);
            $planned[$this->identity($vertical, 'service', $seedKey)] = true;
            $counts['services']++;

            $templateSeed = 'operations.job_template.' . $serviceKey;
            $templateId = $this->upsertSeededRecord($companyId, $vertical, $templateSeed, 'job_template', [
                'company_id' => $companyId,
                'business_line_id' => $businessLineId,
                'service_id' => $serviceId,
                'name' => (string) $service['name'] . ' Standard Job',
                'description' => $service['description'] ?? null,
                'estimated_duration_minutes' => $service['default_duration_minutes'] ?? null,
                'status' => 'active',
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
            ]);
            $planned[$this->identity($vertical, 'job_template', $templateSeed)] = true;
            $counts['job_templates']++;

            foreach ((array) ($service['tasks'] ?? []) as $index => $task) {
                if (! is_array($task)) { continue; }
                $taskKey = (string) ($task['key'] ?? 'task_' . $index);
                $taskSeed = 'operations.task_template.' . $serviceKey . '.' . $taskKey;
                $this->upsertSeededRecord($companyId, $vertical, $taskSeed, 'task_template', [
                    'company_id' => $companyId,
                    'job_template_id' => $templateId,
                    'name' => (string) $task['name'],
                    'description' => $task['description'] ?? null,
                    'sort_order' => (int) ($task['sort_order'] ?? $index),
                    'estimated_minutes' => $task['estimated_minutes'] ?? null,
                    'is_required' => (bool) ($task['is_required'] ?? true),
                    'active' => true,
                ]);
                $planned[$this->identity($vertical, 'task_template', $taskSeed)] = true;
                $counts['tasks']++;
            }
        }

        foreach ((array) ($operations['skills'] ?? []) as $skill) {
            if (! is_array($skill)) { continue; }
            $vertical = (string) $skill['vertical_key'];
            $seedKey = 'operations.skill.' . (string) $skill['key'];
            $this->upsertSeededRecord($companyId, $vertical, $seedKey, 'skill', [
                'company_id' => $companyId,
                'name' => (string) $skill['name'],
                'code' => (string) $skill['key'],
                'description' => $skill['description'] ?? null,
                'category' => $skill['category'] ?? null,
                'requires_certification' => (bool) ($skill['requires_certification'] ?? false),
                'active' => true,
            ]);
            $planned[$this->identity($vertical, 'skill', $seedKey)] = true;
            $counts['skills']++;
        }

        foreach ((array) ($operations['forms'] ?? []) as $form) {
            if (! is_array($form)) { continue; }
            $vertical = (string) $form['vertical_key'];
            $seedKey = 'operations.form_template.' . (string) $form['key'];
            $this->upsertSeededRecord($companyId, $vertical, $seedKey, 'form_template', [
                'company_id' => $companyId,
                'business_line_id' => $this->businessLineId($lineIds, $form),
                'code' => (string) $form['key'],
                'name' => (string) $form['name'],
                'form_type' => (string) ($form['form_type'] ?? 'checklist'),
                'version' => 1,
                'status' => 'published',
                'applies_to' => (string) ($form['applies_to'] ?? 'work_order'),
                'description' => $form['description'] ?? null,
                'settings' => json_encode(['generated_by' => 'vertical_operations', 'requires_human_review' => true], JSON_THROW_ON_ERROR),
                'active' => true,
                'published_at' => now(),
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
            ]);
            $planned[$this->identity($vertical, 'form_template', $seedKey)] = true;
            $counts['forms']++;
        }

        $this->retireObsolete($companyId, $planned);
        $this->persistComplianceRequirements($companyId, (array) ($operations['compliance_requirements'] ?? []));

        return $counts + [
            'compliance_requirements' => array_values((array) ($operations['compliance_requirements'] ?? [])),
        ];
    }

    /**
     * @param list<array{public_id:string,key:string,vertical_key:string}> $businessLines
     * @return array{by_vertical:array<string,int>,by_key:array<string,int>}
     */
    private function businessLineIds(int $companyId, array $businessLines): array
    {
        $map = ['by_vertical' => [], 'by_key' => []];
        foreach ($businessLines as $line) {
            $id = $this->db->table('tz_business_lines')
                ->where('company_id', $companyId)
                ->where('public_id', (string) $line['public_id'])
                ->value('id');
            if ($id === null) {
                continue;
            }

            $lineId = (int) $id;
            $verticalKey = (string) $line['vertical_key'];
            $lineKey = (string) $line['key'];
            $map['by_key'][$lineKey] = $lineId;
            $map['by_vertical'][$verticalKey] ??= $lineId;
        }

        return $map;
    }

    /**
     * @param array{by_vertical:array<string,int>,by_key:array<string,int>} $lineIds
     * @param array<string,mixed> $definition
     */
    private function businessLineId(array $lineIds, array $definition): ?int
    {
        $lineKey = (string) ($definition['business_line_key'] ?? '');
        if ($lineKey !== '' && isset($lineIds['by_key'][$lineKey])) {
            return $lineIds['by_key'][$lineKey];
        }

        $verticalKey = (string) ($definition['vertical_key'] ?? '');
        return $lineIds['by_vertical'][$verticalKey] ?? null;
    }

    /** @param array<string,mixed> $values */
    private function upsertSeededRecord(int $companyId, string $verticalKey, string $seedKey, string $recordType, array $values): int
    {
        $definition = $this->managedTypes[$recordType] ?? throw new RuntimeException("Unsupported seeded record type [{$recordType}].");
        $table = $definition['table'];
        $provenance = $this->db->table('tz_vertical_seed_provenance')
            ->where('company_id', $companyId)
            ->where('vertical_key', $verticalKey)
            ->where('seed_key', $seedKey)
            ->where('record_type', $recordType)
            ->first();

        $recordId = $provenance !== null ? (int) $provenance->record_id : 0;
        if ($provenance !== null && (bool) $provenance->is_customer_modified && $recordId > 0
            && $this->db->table($table)->where('id', $recordId)->exists()) {
            return $recordId;
        }

        $now = now();
        $payload = $values + ['updated_at' => $now];
        if ($this->supportsSoftDeletes($recordType)) {
            $payload['deleted_at'] = null;
        }
        if ($recordId > 0 && $this->db->table($table)->where('id', $recordId)->exists()) {
            $this->db->table($table)->where('id', $recordId)->update($payload);
        } else {
            $naturalMatch = $this->findNaturalMatch($recordType, $values);
            if ($naturalMatch !== null) {
                $recordId = (int) $naturalMatch->id;
                if ($this->supportsSoftDeletes($recordType)) {
                    $this->db->table($table)->where('id', $recordId)->update(['deleted_at' => null, 'updated_at' => $now]);
                }
                $this->writeProvenance(
                    $companyId,
                    $verticalKey,
                    $seedKey,
                    $recordType,
                    $recordId,
                    $values,
                    true,
                    ['source' => 'vertical_operations', 'adopted_existing' => true],
                    $provenance?->created_at ?? $now,
                    $now,
                );
                return $recordId;
            }

            $payload['public_id'] = (string) Str::ulid();
            $payload['created_at'] = $now;
            $recordId = (int) $this->db->table($table)->insertGetId($payload);
        }

        $this->writeProvenance(
            $companyId,
            $verticalKey,
            $seedKey,
            $recordType,
            $recordId,
            $values,
            false,
            ['source' => 'vertical_operations'],
            $provenance?->created_at ?? $now,
            $now,
        );

        return $recordId;
    }

    /** @param array<string,mixed> $values */
    private function findNaturalMatch(string $recordType, array $values): ?object
    {
        $definition = $this->managedTypes[$recordType] ?? null;
        if ($definition === null) {
            return null;
        }

        $query = $this->db->table($definition['table']);
        foreach ($definition['natural'] as $column) {
            if (! array_key_exists($column, $values)) {
                return null;
            }
            $values[$column] === null
                ? $query->whereNull($column)
                : $query->where($column, $values[$column]);
        }
        return $query->first(['id']);
    }

    /** @param array<string,mixed> $values @param array<string,mixed> $metadata */
    private function writeProvenance(
        int $companyId,
        string $verticalKey,
        string $seedKey,
        string $recordType,
        int $recordId,
        array $values,
        bool $customerModified,
        array $metadata,
        mixed $createdAt,
        mixed $updatedAt,
    ): void {
        $hashPayload = $values;
        ksort($hashPayload);
        $this->db->table('tz_vertical_seed_provenance')->updateOrInsert(
            ['company_id' => $companyId, 'vertical_key' => $verticalKey, 'seed_key' => $seedKey, 'record_type' => $recordType],
            [
                'record_id' => $recordId,
                'installed_version' => '1.0.0',
                'content_hash' => hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR)),
                'is_customer_modified' => $customerModified,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ],
        );
    }

    /** @param array<string,bool> $planned */
    private function retireObsolete(int $companyId, array $planned): void
    {
        $records = $this->db->table('tz_vertical_seed_provenance')
            ->where('company_id', $companyId)
            ->whereIn('record_type', array_keys($this->managedTypes))
            ->where('is_customer_modified', false)
            ->get();

        foreach ($records as $record) {
            if ($this->metadataSource($record->metadata) !== 'vertical_operations') {
                continue;
            }
            if (isset($planned[$this->identity((string) $record->vertical_key, (string) $record->record_type, (string) $record->seed_key)])) {
                continue;
            }
            $definition = $this->managedTypes[(string) $record->record_type] ?? null;
            if ($definition !== null) {
                $this->db->table($definition['table'])->where('id', (int) $record->record_id)->update($definition['retire'] + ['updated_at' => now()]);
            }
        }
    }

    private function metadataSource(mixed $metadata): string
    {
        if (is_object($metadata)) {
            return (string) ($metadata->source ?? '');
        }
        if (is_array($metadata)) {
            return (string) ($metadata['source'] ?? '');
        }
        if (! is_string($metadata) || $metadata === '') {
            return '';
        }
        $decoded = json_decode($metadata, true);
        return is_array($decoded) ? (string) ($decoded['source'] ?? '') : '';
    }

    /** @param list<array<string,mixed>> $requirements */
    private function persistComplianceRequirements(int $companyId, array $requirements): void
    {
        $existing = $this->db->table('tz_company_settings')
            ->where('company_id', $companyId)
            ->where('setting_key', 'operations.compliance_requirements')
            ->first();
        if ($existing !== null) {
            if ((bool) ($existing->is_locked ?? false)) {
                return;
            }
            if ((string) ($existing->source ?? '') !== 'vertical_operations') {
                return;
            }
            $this->db->table('tz_company_settings')->where('id', (int) $existing->id)->update([
                'setting_group' => 'operations',
                'value' => json_encode(['value' => array_values($requirements)], JSON_THROW_ON_ERROR),
                'source' => 'vertical_operations',
                'updated_at' => now(),
            ]);
            return;
        }

        $this->db->table('tz_company_settings')->insert([
            'company_id' => $companyId,
            'setting_key' => 'operations.compliance_requirements',
            'setting_group' => 'operations',
            'value' => json_encode(['value' => array_values($requirements)], JSON_THROW_ON_ERROR),
            'source' => 'vertical_operations',
            'is_locked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function supportsSoftDeletes(string $recordType): bool
    {
        return in_array($recordType, ['service_category', 'service', 'job_template', 'skill', 'form_template'], true);
    }

    private function identity(string $verticalKey, string $recordType, string $seedKey): string
    {
        return $verticalKey . '|' . $recordType . '|' . $seedKey;
    }
}
