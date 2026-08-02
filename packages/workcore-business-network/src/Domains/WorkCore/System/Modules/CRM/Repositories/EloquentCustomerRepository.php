<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\CRM\Repositories;

use App\Domains\WorkCore\System\Authorization\CompanyRecordAuthorizer;
use App\Domains\WorkCore\System\Authorization\WorkCoreAccessLevel;
use App\Domains\WorkCore\System\Modules\CRM\Contracts\CustomerRepositoryContract;
use App\Domains\WorkCore\System\Modules\CRM\Data\CustomerData;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class EloquentCustomerRepository extends TenantScopedRepository implements CustomerRepositoryContract
{
    private const TABLE = 'tz_customers';

    public function __construct(
        \Illuminate\Database\ConnectionInterface $db,
        \App\Domains\WorkCore\System\Contracts\TenantContextContract $tenant,
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

        // Customers do not yet have an explicit owner column. Until the CRM
        // ownership model is added, creator identity is the ownership signal.
        $this->authorizer->apply(
            $builder,
            $accessLevel,
            $actorId,
            'created_by_user_id',
            ['created_by_user_id'],
        );

        return $builder
            ->when($query, static fn ($queryBuilder) => $queryBuilder->where(static function ($nested) use ($query): void {
                $nested->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('legal_name', 'like', "%{$query}%");
            }))
            ->select(['public_id', 'name', 'email', 'legal_name as company_name', 'phone', 'address', 'notes as note'])
            ->orderBy('name')
            ->paginate(max(1, min($perPage, 100)));
    }

    public function create(CustomerData $data, int $companyId, int $actorId): array
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) {
            throw new \RuntimeException('Repository actor does not match the active WorkCore actor.');
        }

        $publicId = (string) Str::ulid();
        $this->db->table(self::TABLE)->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'name' => $data->name,
            'legal_name' => $data->companyName,
            'email' => $data->email,
            'normalized_email' => $data->email,
            'phone' => $data->phone,
            'normalized_phone' => $data->phone,
            'address' => $data->address,
            'notes' => $data->note,
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
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
            $this->authorizer->apply($builder, $accessLevel, $actorId, 'created_by_user_id', ['created_by_user_id']);
        }

        $row = $builder
            ->select(['public_id', 'name', 'email', 'legal_name as company_name', 'phone', 'address', 'notes as note'])
            ->first();

        return $row ? (array) $row : null;
    }
}
