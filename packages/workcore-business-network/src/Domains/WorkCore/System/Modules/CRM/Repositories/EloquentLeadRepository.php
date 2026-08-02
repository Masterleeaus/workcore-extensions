<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Repositories;

use App\Domains\WorkCore\System\Authorization\CompanyRecordAuthorizer;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\LeadRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\LeadData;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use App\Domains\WorkCore\System\Validation\CompanyScopedReferenceValidator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class EloquentLeadRepository extends TenantScopedRepository implements LeadRepositoryContract
{
    private const TABLE = 'tz_leads';

    public function __construct(
        \Illuminate\Database\ConnectionInterface $db,
        \App\Domains\WorkCore\System\Contracts\TenantContextContract $tenant,
        private CompanyScopedReferenceValidator $references,
        private CompanyRecordAuthorizer $authorizer,
    ) {
        parent::__construct($db, $tenant);
    }

    public function search(
        int $companyId,
        WorkCoreAccessLevel $accessLevel,
        int $actorId,
        ?string $query = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $this->assertCompany($companyId);
        $builder = $this->db->table(self::TABLE)
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        $this->authorizer->apply(
            $builder,
            $accessLevel,
            $actorId,
            'created_by_user_id',
            ['owner_user_id'],
        );

        return $builder
            ->when($query, static fn ($queryBuilder) => $queryBuilder->where(static function ($nested) use ($query): void {
                $nested->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('company_name', 'like', "%{$query}%");
            }))
            ->select(['public_id', 'name', 'email', 'company_name', 'phone', 'notes', 'owner_user_id', 'priority', 'next_follow_up'])
            ->orderBy('name')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function create(LeadData $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) {
            throw new \RuntimeException('Repository actor does not match the active WorkCore actor.');
        }
        if ($data->ownerId !== null) {
            $this->references->requireActiveMember($companyId, $data->ownerId, 'owner_id');
        }

        $sourceId = $data->sourcePublicId !== null ? $this->references->requirePublicRecordId($companyId, 'tz_lead_sources', $data->sourcePublicId, 'source_public_id') : null;
        $statusId = $data->statusPublicId !== null ? $this->references->requirePublicRecordId($companyId, 'tz_lead_statuses', $data->statusPublicId, 'status_public_id') : (int) $this->db->table('tz_lead_statuses')->where('company_id', $companyId)->where('key', 'new')->value('id');

        $publicId = (string) Str::ulid();
        $this->db->table(self::TABLE)->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'name' => $data->name,
            'email' => $data->email,
            'normalized_email' => $data->email,
            'company_name' => $data->companyName,
            'phone' => $data->phone,
            'normalized_phone' => $data->phone,
            'notes' => $data->note,
            'source_id' => $sourceId,
            'status_id' => $statusId,
            'owner_user_id' => $data->ownerId,
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
            'next_follow_up' => true,
            'priority' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->findByPublicId($companyId, $publicId) ?? ['public_id' => $publicId];
    }

    public function findByPublicId(
        int $companyId,
        string $publicId,
        WorkCoreAccessLevel $accessLevel = WorkCoreAccessLevel::All,
        ?int $actorId = null,
    ): ?array {
        $this->assertCompany($companyId);
        $builder = $this->db->table(self::TABLE)
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->whereNull('deleted_at');

        if ($actorId !== null) {
            $this->authorizer->apply($builder, $accessLevel, $actorId, 'created_by_user_id', ['owner_user_id']);
        }

        $row = $builder
            ->select(['public_id', 'name', 'email', 'company_name', 'phone', 'notes', 'owner_user_id', 'priority', 'next_follow_up'])
            ->first();

        return $row ? (array) $row : null;
    }
}
