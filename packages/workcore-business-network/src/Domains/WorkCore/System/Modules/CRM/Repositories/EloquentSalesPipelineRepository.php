<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Repositories;

use App\Domains\WorkCore\System\Authorization\CompanyRecordAuthorizer;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\SalesPipelineRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\SalesPipelineData;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EloquentSalesPipelineRepository extends TenantScopedRepository implements SalesPipelineRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private CompanyRecordAuthorizer $authorizer,
    ) {
        parent::__construct($db, $tenant);
    }

    public function list(int $companyId): array
    {
        $this->assertCompany($companyId);

        return $this->db->table('tz_sales_pipelines as pipelines')
            ->where('pipelines.company_id', $companyId)
            ->whereNull('pipelines.deleted_at')
            ->orderByDesc('pipelines.is_default')
            ->orderBy('pipelines.name')
            ->get([
                'pipelines.public_id', 'pipelines.key', 'pipelines.name',
                'pipelines.is_default', 'pipelines.is_active', 'pipelines.created_at', 'pipelines.updated_at',
            ])
            ->map(function (object $pipeline) use ($companyId): array {
                $row = (array) $pipeline;
                $row['stage_count'] = $this->db->table('tz_sales_pipeline_stages')
                    ->where('company_id', $companyId)
                    ->where('pipeline_id', $this->pipelineInternalId($companyId, (string) $pipeline->public_id))
                    ->whereNull('deleted_at')
                    ->count();
                return $row;
            })->all();
    }

    public function create(SalesPipelineData $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        if ($data->name === '' || $data->key === '' || preg_match('/^[a-z0-9_-]+$/', $data->key) !== 1) {
            throw ValidationException::withMessages(['key' => ['A pipeline requires a name and a stable lowercase key.']]);
        }
        $this->validateStages($data->stages);

        return $this->db->transaction(function () use ($data, $companyId, $actorId): array {
            $duplicate = $this->db->table('tz_sales_pipelines')
                ->where('company_id', $companyId)
                ->where('key', $data->key)
                ->whereNull('deleted_at')
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['key' => ['A sales pipeline with this key already exists.']]);
            }

            if ($data->isDefault) {
                $this->db->table('tz_sales_pipelines')->where('company_id', $companyId)->whereNull('deleted_at')->update([
                    'is_default' => false,
                    'updated_by_user_id' => $actorId,
                    'updated_at' => now(),
                ]);
            }

            $publicId = (string) Str::ulid();
            $pipelineId = $this->db->table('tz_sales_pipelines')->insertGetId([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'key' => $data->key,
                'name' => $data->name,
                'is_default' => $data->isDefault,
                'is_active' => true,
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (array_values($data->stages) as $index => $stage) {
                $name = trim((string) ($stage['name'] ?? ''));
                $key = trim((string) ($stage['key'] ?? Str::slug($name)));
                $this->db->table('tz_sales_pipeline_stages')->insert([
                    'public_id' => (string) Str::ulid(),
                    'company_id' => $companyId,
                    'pipeline_id' => $pipelineId,
                    'key' => $key,
                    'name' => $name,
                    'color' => (string) ($stage['color'] ?? '#2563eb'),
                    'position' => $index,
                    'probability' => (int) ($stage['probability'] ?? 0),
                    'is_closed' => (bool) ($stage['is_closed'] ?? false),
                    'is_won' => (bool) ($stage['is_won'] ?? false),
                    'is_active' => (bool) ($stage['is_active'] ?? true),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $this->findByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
        }, 3);
    }

    public function findByPublicId(int $companyId, string $publicId): ?array
    {
        $this->assertCompany($companyId);
        $pipeline = $this->db->table('tz_sales_pipelines')
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->first(['id', 'public_id', 'key', 'name', 'is_default', 'is_active', 'created_at', 'updated_at']);
        if ($pipeline === null) {
            return null;
        }

        $record = (array) $pipeline;
        $pipelineId = (int) $record['id'];
        unset($record['id']);
        $record['stages'] = $this->db->table('tz_sales_pipeline_stages')
            ->where('company_id', $companyId)
            ->where('pipeline_id', $pipelineId)
            ->whereNull('deleted_at')
            ->orderBy('position')
            ->get(['public_id', 'key', 'name', 'color', 'position', 'probability', 'is_closed', 'is_won', 'is_active'])
            ->map(static fn (object $stage): array => (array) $stage)->all();

        return $record;
    }

    public function board(int $companyId, string $pipelinePublicId, WorkCoreAccessLevel $accessLevel, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        $pipeline = $this->findByPublicId($companyId, $pipelinePublicId);
        if ($pipeline === null) {
            throw ValidationException::withMessages(['pipeline_public_id' => ['The selected pipeline does not belong to the current company.']]);
        }

        $pipelineId = $this->pipelineInternalId($companyId, $pipelinePublicId);
        foreach ($pipeline['stages'] as &$stage) {
            $stageId = (int) $this->db->table('tz_sales_pipeline_stages')
                ->where('company_id', $companyId)
                ->where('public_id', $stage['public_id'])
                ->value('id');
            $query = $this->db->table('tz_opportunities as opportunities')
                ->leftJoin('tz_customers as customers', 'customers.id', '=', 'opportunities.customer_id')
                ->leftJoin('tz_leads as leads', 'leads.id', '=', 'opportunities.lead_id')
                ->where('opportunities.company_id', $companyId)
                ->where('opportunities.pipeline_id', $pipelineId)
                ->where('opportunities.stage_id', $stageId)
                ->whereNull('opportunities.deleted_at');
            $this->authorizer->apply($query, $accessLevel, $actorId, 'opportunities.created_by_user_id', ['opportunities.owner_user_id']);
            $opportunities = $query->orderBy('opportunities.position')->get([
                'opportunities.public_id', 'opportunities.title', 'opportunities.amount', 'opportunities.currency_code',
                'opportunities.status', 'opportunities.probability', 'opportunities.position', 'opportunities.expected_close_date',
                'opportunities.owner_user_id', 'customers.public_id as customer_public_id', 'customers.name as customer_name',
                'leads.public_id as lead_public_id', 'leads.name as lead_name',
            ])->map(static fn (object $row): array => (array) $row)->all();
            $stage['opportunities'] = $opportunities;
            $stage['opportunity_count'] = count($opportunities);
            $stage['total_value'] = round(array_sum(array_map(static fn (array $row): float => (float) $row['amount'], $opportunities)), 2);
        }
        unset($stage);

        return $pipeline;
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) {
            throw new \RuntimeException('Repository actor does not match the active WorkCore actor.');
        }
    }

    private function pipelineInternalId(int $companyId, string $publicId): int
    {
        return (int) $this->db->table('tz_sales_pipelines')
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->value('id');
    }

    /** @param list<array<string,mixed>> $stages */
    private function validateStages(array $stages): void
    {
        if (count($stages) < 2 || count($stages) > 30) {
            throw ValidationException::withMessages(['stages' => ['A pipeline requires between 2 and 30 stages.']]);
        }
        $keys = [];
        $wonStages = 0;
        $lostStages = 0;
        foreach ($stages as $index => $stage) {
            $name = trim((string) ($stage['name'] ?? ''));
            $key = trim((string) ($stage['key'] ?? Str::slug($name)));
            $probability = (int) ($stage['probability'] ?? 0);
            if ($name === '' || $key === '') {
                throw ValidationException::withMessages(["stages.{$index}.name" => ['Every stage requires a name and stable key.']]);
            }
            if ($probability < 0 || $probability > 100) {
                throw ValidationException::withMessages(["stages.{$index}.probability" => ['Stage probability must be between 0 and 100.']]);
            }
            $color = (string) ($stage['color'] ?? '#2563eb');
            if (preg_match('/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/', $color) !== 1) {
                throw ValidationException::withMessages(["stages.{$index}.color" => ['Stage color must be a six- or eight-digit hexadecimal value.']]);
            }
            if (isset($keys[$key])) {
                throw ValidationException::withMessages(["stages.{$index}.key" => ['Stage keys must be unique within the pipeline.']]);
            }
            $isClosed = (bool) ($stage['is_closed'] ?? false);
            $isWon = (bool) ($stage['is_won'] ?? false);
            if ($isWon && ! $isClosed) {
                throw ValidationException::withMessages(["stages.{$index}.is_won" => ['A won stage must also be closed.']]);
            }
            $wonStages += $isWon ? 1 : 0;
            $lostStages += $isClosed && ! $isWon ? 1 : 0;
            $keys[$key] = true;
        }
        if ($wonStages < 1 || $lostStages < 1) {
            throw ValidationException::withMessages(['stages' => ['A pipeline requires at least one won stage and one closed lost stage.']]);
        }
    }
}
