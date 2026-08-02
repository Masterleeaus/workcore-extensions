<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Repositories;

use App\Domains\WorkCore\System\Authorization\CompanyRecordAuthorizer;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\OpportunityRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\OpportunityData;
use App\Domains\WorkCore\System\Modules\CRM\Services\CrmActivityRecorder;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use App\Domains\WorkCore\System\Validation\CompanyScopedReferenceValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EloquentOpportunityRepository extends TenantScopedRepository implements OpportunityRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private CompanyScopedReferenceValidator $references,
        private CompanyRecordAuthorizer $authorizer,
        private CrmActivityRecorder $activities,
    ) {
        parent::__construct($db, $tenant);
    }

    public function search(int $companyId, WorkCoreAccessLevel $accessLevel, int $actorId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $this->assertActor($companyId, $actorId);
        $query = $this->baseQuery($companyId);
        $this->authorizer->apply($query, $accessLevel, $actorId, 'opportunities.created_by_user_id', ['opportunities.owner_user_id']);

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested->where('opportunities.title', 'like', "%{$search}%")
                    ->orWhere('opportunities.description', 'like', "%{$search}%")
                    ->orWhere('customers.name', 'like', "%{$search}%")
                    ->orWhere('leads.name', 'like', "%{$search}%");
            });
        }
        foreach (['status' => 'opportunities.status', 'currency_code' => 'opportunities.currency_code'] as $filter => $column) {
            if (isset($filters[$filter]) && trim((string) $filters[$filter]) !== '') {
                $query->where($column, trim((string) $filters[$filter]));
            }
        }
        if (isset($filters['pipeline_public_id']) && trim((string) $filters['pipeline_public_id']) !== '') {
            $query->where('pipelines.public_id', trim((string) $filters['pipeline_public_id']));
        }
        if (isset($filters['stage_public_id']) && trim((string) $filters['stage_public_id']) !== '') {
            $query->where('stages.public_id', trim((string) $filters['stage_public_id']));
        }
        if (isset($filters['owner_user_id']) && (int) $filters['owner_user_id'] > 0) {
            $query->where('opportunities.owner_user_id', (int) $filters['owner_user_id']);
        }
        if (filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('opportunities.status', 'open')->whereDate('opportunities.expected_close_date', '<', now()->toDateString());
        }

        return $query->orderByDesc('opportunities.updated_at')->paginate(max(1, min($perPage, 100)));
    }

    public function create(OpportunityData $data, int $companyId, int $actorId): array
    {
        $this->assertActor($companyId, $actorId);
        if ($data->title === '') {
            throw ValidationException::withMessages(['title' => ['Opportunity title is required.']]);
        }
        if ($data->amount < 0) {
            throw ValidationException::withMessages(['amount' => ['Opportunity amount cannot be negative.']]);
        }
        if (strlen($data->currencyCode) !== 3) {
            throw ValidationException::withMessages(['currency_code' => ['Currency code must contain exactly three characters.']]);
        }
        if ($data->probability !== null && ($data->probability < 0 || $data->probability > 100)) {
            throw ValidationException::withMessages(['probability' => ['Opportunity probability must be between 0 and 100.']]);
        }
        [$pipeline, $stage] = $this->pipelineAndStage($companyId, $data->pipelinePublicId, $data->stagePublicId);
        $leadId = $this->optionalReference($companyId, 'tz_leads', $data->leadPublicId, 'lead_public_id');
        $customerId = $this->optionalReference($companyId, 'tz_customers', $data->customerPublicId, 'customer_public_id');
        $contactId = $this->optionalReference($companyId, 'tz_customer_contacts', $data->contactPublicId, 'contact_public_id');
        if ($data->ownerUserId !== null) {
            $this->references->requireActiveMember($companyId, $data->ownerUserId, 'owner_user_id');
        }

        return $this->db->transaction(function () use ($data, $companyId, $actorId, $pipeline, $stage, $leadId, $customerId, $contactId): array {
            $maximumPosition = $this->db->table('tz_opportunities')
                ->where('company_id', $companyId)
                ->where('stage_id', $stage->id)
                ->whereNull('deleted_at')
                ->max('position');
            $position = $maximumPosition === null ? 0 : ((int) $maximumPosition + 1);
            $state = $this->stateForStage($stage);
            $publicId = (string) Str::ulid();
            $this->db->table('tz_opportunities')->insert([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'lead_id' => $leadId,
                'customer_id' => $customerId,
                'contact_id' => $contactId,
                'owner_user_id' => $data->ownerUserId,
                'title' => $data->title,
                'description' => $data->description,
                'amount' => $data->amount,
                'currency_code' => $data->currencyCode,
                'status' => $state['status'],
                'probability' => $state['status'] === 'open' ? ($data->probability ?? $state['probability']) : $state['probability'],
                'position' => max(0, $position),
                'expected_close_date' => $data->expectedCloseDate,
                'closed_at' => $state['closed_at'],
                'lost_reason' => null,
                'metadata' => $data->metadata === [] ? null : json_encode($data->metadata, JSON_THROW_ON_ERROR),
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->activities->record($companyId, 'opportunity', $publicId, 'system', 'Opportunity created', null, null, null, null, $actorId, [
                'pipeline_public_id' => $pipeline->public_id,
                'stage_public_id' => $stage->public_id,
            ]);
            return $this->findByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
        }, 3);
    }

    public function update(int $companyId, string $publicId, int $actorId, array $data): array
    {
        $this->assertActor($companyId, $actorId);
        $opportunity = $this->opportunityForUpdate($companyId, $publicId);
        if ($opportunity->status !== 'open' && array_key_exists('probability', $data)) {
            throw ValidationException::withMessages(['probability' => ['Closed opportunities keep the probability implied by their outcome.']]);
        }
        $updates = [];
        foreach (['title', 'description', 'expected_close_date'] as $field) {
            if (array_key_exists($field, $data)) {
                $updates[$field] = $data[$field] === null ? null : trim((string) $data[$field]);
            }
        }
        if (array_key_exists('amount', $data)) {
            $amount = round((float) $data['amount'], 2);
            if ($amount < 0) {
                throw ValidationException::withMessages(['amount' => ['Opportunity amount cannot be negative.']]);
            }
            $updates['amount'] = $amount;
        }
        if (array_key_exists('currency_code', $data)) {
            $currencyCode = strtoupper(trim((string) $data['currency_code']));
            if (strlen($currencyCode) !== 3) {
                throw ValidationException::withMessages(['currency_code' => ['Currency code must contain exactly three characters.']]);
            }
            $updates['currency_code'] = $currencyCode;
        }
        if (array_key_exists('probability', $data)) {
            $probability = (int) $data['probability'];
            if ($probability < 0 || $probability > 100) {
                throw ValidationException::withMessages(['probability' => ['Opportunity probability must be between 0 and 100.']]);
            }
            $updates['probability'] = $probability;
        }
        foreach (['lead_public_id' => ['lead_id', 'tz_leads'], 'customer_public_id' => ['customer_id', 'tz_customers'], 'contact_public_id' => ['contact_id', 'tz_customer_contacts']] as $field => [$column, $table]) {
            if (array_key_exists($field, $data)) {
                $updates[$column] = $this->optionalReference($companyId, $table, $data[$field] === null ? null : trim((string) $data[$field]), $field);
            }
        }
        if (array_key_exists('owner_user_id', $data)) {
            $ownerId = $data['owner_user_id'] === null ? null : (int) $data['owner_user_id'];
            if ($ownerId !== null) {
                $this->references->requireActiveMember($companyId, $ownerId, 'owner_user_id');
            }
            $updates['owner_user_id'] = $ownerId;
        }
        if (array_key_exists('metadata', $data)) {
            $metadata = (array) $data['metadata'];
            $updates['metadata'] = $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR);
        }
        if (array_key_exists('title', $updates) && $updates['title'] === '') {
            throw ValidationException::withMessages(['title' => ['Opportunity title is required.']]);
        }
        if ($updates === []) {
            return $this->findByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
        }
        $updates['updated_by_user_id'] = $actorId;
        $updates['updated_at'] = now();
        $this->db->table('tz_opportunities')->where('id', $opportunity->id)->update($updates);
        $this->activities->record($companyId, 'opportunity', $publicId, 'system', 'Opportunity updated', null, null, null, null, $actorId, ['changed_fields' => array_keys($updates)]);

        return $this->findByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
    }

    public function move(int $companyId, string $publicId, int $actorId, string $stagePublicId, int $position): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($companyId, $publicId, $actorId, $stagePublicId, $position): array {
            $opportunity = $this->opportunityForUpdate($companyId, $publicId);
            $targetStage = $this->stage($companyId, $stagePublicId, true);
            if ((int) $targetStage->pipeline_id !== (int) $opportunity->pipeline_id) {
                throw ValidationException::withMessages(['stage_public_id' => ['An opportunity can only move to a stage in its current pipeline.']]);
            }
            $sourceStageId = (int) $opportunity->stage_id;
            $this->reposition($companyId, (int) $opportunity->id, $sourceStageId, (int) $targetStage->id, $position);
            $state = $this->stateForStage($targetStage);
            $this->db->table('tz_opportunities')->where('id', $opportunity->id)->update([
                'stage_id' => $targetStage->id,
                'status' => $state['status'],
                'probability' => $state['probability'],
                'closed_at' => $state['closed_at'],
                'lost_reason' => $state['status'] === 'lost' ? $opportunity->lost_reason : null,
                'updated_by_user_id' => $actorId,
                'updated_at' => now(),
            ]);
            $sourceStagePublicId = (string) $this->db->table('tz_sales_pipeline_stages')->where('id', $sourceStageId)->value('public_id');
            $this->activities->record($companyId, 'opportunity', $publicId, 'system', 'Opportunity moved', null, null, null, null, $actorId, [
                'from_stage_public_id' => $sourceStagePublicId,
                'to_stage_public_id' => $stagePublicId,
                'position' => max(0, $position),
            ]);

            return $this->findByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
        }, 3);
    }

    public function markWon(int $companyId, string $publicId, int $actorId): array
    {
        return $this->close($companyId, $publicId, $actorId, true, null);
    }

    public function markLost(int $companyId, string $publicId, int $actorId, ?string $reason): array
    {
        return $this->close($companyId, $publicId, $actorId, false, $reason);
    }

    public function findByPublicId(int $companyId, string $publicId, WorkCoreAccessLevel $accessLevel = WorkCoreAccessLevel::All, ?int $actorId = null): ?array
    {
        $this->assertCompany($companyId);
        $query = $this->baseQuery($companyId)->where('opportunities.public_id', $publicId);
        if ($actorId !== null) {
            $this->authorizer->apply($query, $accessLevel, $actorId, 'opportunities.created_by_user_id', ['opportunities.owner_user_id']);
        }
        $row = $query->first();
        return $row ? (array) $row : null;
    }

    public function forecast(int $companyId, WorkCoreAccessLevel $accessLevel, int $actorId, ?string $pipelinePublicId = null): array
    {
        $this->assertActor($companyId, $actorId);
        $query = $this->db->table('tz_opportunities as opportunities')
            ->join('tz_sales_pipelines as pipelines', 'pipelines.id', '=', 'opportunities.pipeline_id')
            ->where('opportunities.company_id', $companyId)
            ->whereNull('opportunities.deleted_at');
        $this->authorizer->apply($query, $accessLevel, $actorId, 'opportunities.created_by_user_id', ['opportunities.owner_user_id']);
        if ($pipelinePublicId !== null && trim($pipelinePublicId) !== '') {
            $query->where('pipelines.public_id', trim($pipelinePublicId));
        }
        $rows = $query->get(['opportunities.amount', 'opportunities.probability', 'opportunities.status', 'opportunities.expected_close_date']);
        $summary = [
            'open_count' => 0,
            'open_value' => 0.0,
            'weighted_value' => 0.0,
            'won_count' => 0,
            'won_value' => 0.0,
            'lost_count' => 0,
            'lost_value' => 0.0,
            'overdue_count' => 0,
        ];
        foreach ($rows as $row) {
            $amount = (float) $row->amount;
            if ($row->status === 'open') {
                $summary['open_count']++;
                $summary['open_value'] += $amount;
                $summary['weighted_value'] += $amount * ((int) $row->probability / 100);
                if ($row->expected_close_date !== null && (string) $row->expected_close_date < now()->toDateString()) {
                    $summary['overdue_count']++;
                }
            } elseif ($row->status === 'won') {
                $summary['won_count']++;
                $summary['won_value'] += $amount;
            } elseif ($row->status === 'lost') {
                $summary['lost_count']++;
                $summary['lost_value'] += $amount;
            }
        }
        foreach (['open_value', 'weighted_value', 'won_value', 'lost_value'] as $key) {
            $summary[$key] = round((float) $summary[$key], 2);
        }
        $closedCount = $summary['won_count'] + $summary['lost_count'];
        $summary['win_rate'] = $closedCount === 0 ? null : round(($summary['won_count'] / $closedCount) * 100, 2);
        $summary['pipeline_public_id'] = $pipelinePublicId;

        return $summary;
    }

    /** @return array<string,mixed> */
    private function close(int $companyId, string $publicId, int $actorId, bool $won, ?string $reason): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($companyId, $publicId, $actorId, $won, $reason): array {
            $opportunity = $this->opportunityForUpdate($companyId, $publicId);
            $target = $this->db->table('tz_sales_pipeline_stages')
                ->where('company_id', $companyId)
                ->where('pipeline_id', $opportunity->pipeline_id)
                ->where('is_closed', true)
                ->where('is_won', $won)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('position')
                ->lockForUpdate()
                ->first();
            if ($target !== null && (int) $target->id !== (int) $opportunity->stage_id) {
                $targetPosition = (int) $this->db->table('tz_opportunities')
                    ->where('company_id', $companyId)->where('stage_id', $target->id)->whereNull('deleted_at')->count();
                $this->reposition($companyId, (int) $opportunity->id, (int) $opportunity->stage_id, (int) $target->id, $targetPosition);
            }
            $this->db->table('tz_opportunities')->where('id', $opportunity->id)->update([
                'stage_id' => $target?->id ?? $opportunity->stage_id,
                'status' => $won ? 'won' : 'lost',
                'probability' => $won ? 100 : 0,
                'closed_at' => now(),
                'lost_reason' => $won ? null : $reason,
                'updated_by_user_id' => $actorId,
                'updated_at' => now(),
            ]);
            $this->activities->record($companyId, 'opportunity', $publicId, 'system', $won ? 'Opportunity won' : 'Opportunity lost', $won ? null : $reason, null, null, now()->toDateTimeString(), $actorId, []);

            return $this->findByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
        }, 3);
    }

    private function reposition(int $companyId, int $opportunityId, int $sourceStageId, int $targetStageId, int $requestedPosition): void
    {
        $sourceIds = $this->db->table('tz_opportunities')
            ->where('company_id', $companyId)
            ->where('stage_id', $sourceStageId)
            ->where('id', '<>', $opportunityId)
            ->whereNull('deleted_at')
            ->orderBy('position')
            ->lockForUpdate()
            ->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        if ($sourceStageId === $targetStageId) {
            $targetIds = $sourceIds;
        } else {
            $targetIds = $this->db->table('tz_opportunities')
                ->where('company_id', $companyId)
                ->where('stage_id', $targetStageId)
                ->where('id', '<>', $opportunityId)
                ->whereNull('deleted_at')
                ->orderBy('position')
                ->lockForUpdate()
                ->pluck('id')->map(static fn ($id): int => (int) $id)->all();
            $this->writePositions($sourceIds, $sourceStageId);
        }

        $position = max(0, min($requestedPosition, count($targetIds)));
        array_splice($targetIds, $position, 0, [$opportunityId]);
        $this->writePositions($targetIds, $targetStageId);
    }

    /** @param list<int> $ids */
    private function writePositions(array $ids, int $stageId): void
    {
        foreach (array_values($ids) as $position => $id) {
            $this->db->table('tz_opportunities')->where('id', $id)->update([
                'stage_id' => $stageId,
                'position' => $position,
                'updated_at' => now(),
            ]);
        }
    }

    /** @return array{0:object,1:object} */
    private function pipelineAndStage(int $companyId, string $pipelinePublicId, string $stagePublicId): array
    {
        $pipeline = $this->db->table('tz_sales_pipelines')
            ->where('company_id', $companyId)->where('public_id', $pipelinePublicId)->where('is_active', true)->whereNull('deleted_at')->first();
        if ($pipeline === null) {
            throw ValidationException::withMessages(['pipeline_public_id' => ['The selected pipeline does not belong to the current company.']]);
        }
        $stage = $this->stage($companyId, $stagePublicId, false);
        if ((int) $stage->pipeline_id !== (int) $pipeline->id) {
            throw ValidationException::withMessages(['stage_public_id' => ['The selected stage does not belong to the selected pipeline.']]);
        }
        return [$pipeline, $stage];
    }

    private function stage(int $companyId, string $stagePublicId, bool $lock): object
    {
        $query = $this->db->table('tz_sales_pipeline_stages')
            ->where('company_id', $companyId)
            ->where('public_id', $stagePublicId)
            ->where('is_active', true)
            ->whereNull('deleted_at');
        if ($lock) {
            $query->lockForUpdate();
        }
        $stage = $query->first();
        if ($stage === null) {
            throw ValidationException::withMessages(['stage_public_id' => ['The selected stage does not belong to the current company.']]);
        }
        return $stage;
    }

    private function opportunityForUpdate(int $companyId, string $publicId): object
    {
        $opportunity = $this->db->table('tz_opportunities')
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();
        if ($opportunity === null) {
            throw ValidationException::withMessages(['opportunity_public_id' => ['The selected opportunity does not belong to the current company.']]);
        }
        return $opportunity;
    }

    private function optionalReference(int $companyId, string $table, ?string $publicId, string $field): ?int
    {
        if ($publicId === null || trim($publicId) === '') {
            return null;
        }
        return $this->references->requirePublicRecordId($companyId, $table, trim($publicId), $field);
    }

    /** @return array{status:string,probability:int,closed_at:mixed} */
    private function stateForStage(object $stage): array
    {
        if ((bool) $stage->is_won) {
            return ['status' => 'won', 'probability' => 100, 'closed_at' => now()];
        }
        if ((bool) $stage->is_closed) {
            return ['status' => 'lost', 'probability' => 0, 'closed_at' => now()];
        }
        return ['status' => 'open', 'probability' => (int) $stage->probability, 'closed_at' => null];
    }

    private function baseQuery(int $companyId): Builder
    {
        return $this->db->table('tz_opportunities as opportunities')
            ->join('tz_sales_pipelines as pipelines', 'pipelines.id', '=', 'opportunities.pipeline_id')
            ->join('tz_sales_pipeline_stages as stages', 'stages.id', '=', 'opportunities.stage_id')
            ->leftJoin('tz_leads as leads', 'leads.id', '=', 'opportunities.lead_id')
            ->leftJoin('tz_customers as customers', 'customers.id', '=', 'opportunities.customer_id')
            ->leftJoin('tz_customer_contacts as contacts', 'contacts.id', '=', 'opportunities.contact_id')
            ->where('opportunities.company_id', $companyId)
            ->whereNull('opportunities.deleted_at')
            ->select([
                'opportunities.public_id', 'opportunities.title', 'opportunities.description', 'opportunities.amount',
                'opportunities.currency_code', 'opportunities.status', 'opportunities.probability', 'opportunities.position',
                'opportunities.expected_close_date', 'opportunities.closed_at', 'opportunities.lost_reason',
                'opportunities.owner_user_id', 'opportunities.metadata', 'opportunities.created_at', 'opportunities.updated_at',
                'pipelines.public_id as pipeline_public_id', 'pipelines.name as pipeline_name',
                'stages.public_id as stage_public_id', 'stages.name as stage_name', 'stages.color as stage_color',
                'leads.public_id as lead_public_id', 'leads.name as lead_name',
                'customers.public_id as customer_public_id', 'customers.name as customer_name',
                'contacts.public_id as contact_public_id', 'contacts.name as contact_name',
            ]);
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) {
            throw new \RuntimeException('Repository actor does not match the active WorkCore actor.');
        }
    }
}
