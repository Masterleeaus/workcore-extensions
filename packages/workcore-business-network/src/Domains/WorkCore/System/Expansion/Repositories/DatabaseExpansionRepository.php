<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Expansion\Contracts\ExpansionRepositoryContract;
use App\Domains\WorkCore\System\Expansion\Services\AdaptiveBlueprintRollbackMerger;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;

final class DatabaseExpansionRepository extends TenantScopedRepository implements ExpansionRepositoryContract
{
    public function __construct(
        ConnectionInterface $db,
        TenantContextContract $tenant,
        private AdaptiveBlueprintRollbackMerger $rollbackMerger,
    ) {
        parent::__construct($db, $tenant);
    }

    private const REQUESTS = 'tz_capability_expansion_requests';
    private const DOMAINS = 'tz_adaptive_domains';
    private const VERSIONS = 'tz_adaptive_domain_versions';
    private const RECORDS = 'tz_adaptive_records';

    public function activeDomains(int $companyId): array
    {
        return $this->listAdaptiveDomains($companyId, 'active');
    }

    public function createRequest(
        int $companyId,
        int $actorId,
        string $requirement,
        string $normalisedRequirement,
        array $assessment,
        array $plan,
        string $status,
    ): array {
        $this->assertActor($companyId, $actorId);
        $publicId = (string) Str::ulid();
        $now = now();

        $this->db->table(self::REQUESTS)->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'requested_by_user_id' => $actorId,
            'requirement' => $requirement,
            'normalised_requirement' => $normalisedRequirement,
            'assessment' => json_encode($assessment, JSON_THROW_ON_ERROR),
            'plan' => json_encode($plan, JSON_THROW_ON_ERROR),
            'delivery_mode' => (string) ($plan['delivery_mode'] ?? ''),
            'risk' => (string) ($plan['risk'] ?? 'low'),
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findRequest($companyId, $publicId) ?? ['public_id' => $publicId, 'status' => $status];
    }

    public function findRequest(int $companyId, string $publicId): ?array
    {
        $this->assertCompany($companyId);
        $row = $this->db->table(self::REQUESTS)
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->first();

        return $row ? $this->requestRow((array) $row) : null;
    }

    public function listRequests(int $companyId, ?string $status = null, ?string $deliveryMode = null, int $limit = 50): array
    {
        $this->assertCompany($companyId);
        $rows = $this->db->table(self::REQUESTS)
            ->where('company_id', $companyId)
            ->when($status, static fn ($query) => $query->where('status', $status))
            ->when($deliveryMode, static fn ($query) => $query->where('delivery_mode', $deliveryMode))
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 100)))
            ->get();

        return array_map(fn (mixed $row): array => $this->requestRow((array) $row), $rows->all());
    }

    public function approveRequest(int $companyId, string $publicId, int $actorId, ?string $notes = null): array
    {
        $this->assertActor($companyId, $actorId);
        $request = $this->findRequest($companyId, $publicId)
            ?? throw new RuntimeException('Capability expansion request was not found.');

        if (! in_array($request['status'], ['proposed', 'native_build_required'], true)) {
            throw new RuntimeException("Capability expansion request cannot be approved from status [{$request['status']}].");
        }

        $status = $request['delivery_mode'] === 'native_module' ? 'approved_for_build' : 'approved';
        $this->db->table(self::REQUESTS)
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->update([
                'status' => $status,
                'approved_by_user_id' => $actorId,
                'approval_notes' => $notes,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

        return $this->findRequest($companyId, $publicId) ?? throw new RuntimeException('Approved expansion request could not be reloaded.');
    }

    public function markRequestStatus(int $companyId, string $publicId, string $status): array
    {
        $this->assertCompany($companyId);
        $values = ['status' => $status, 'updated_at' => now()];
        if ($status === 'activated') {
            $values['activated_at'] = now();
        }
        $exists = $this->db->table(self::REQUESTS)
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->exists();
        if (! $exists) {
            throw new RuntimeException('Capability expansion request was not found.');
        }
        $this->db->table(self::REQUESTS)
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->update($values);

        return $this->findRequest($companyId, $publicId) ?? throw new RuntimeException('Expansion request could not be reloaded.');
    }

    public function activateAdaptiveDomain(int $companyId, string $requestPublicId, int $actorId, array $blueprint): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($companyId, $requestPublicId, $actorId, $blueprint): array {
            $request = $this->findRequest($companyId, $requestPublicId)
                ?? throw new RuntimeException('Capability expansion request was not found.');
            $operation = (string) ($request['plan']['operation'] ?? 'create_adaptive_domain');
            $target = (string) ($request['plan']['target_capability'] ?? '');
            $existing = $this->db->table(self::DOMAINS)
                ->where('company_id', $companyId)
                ->where('slug', (string) $blueprint['slug'])
                ->lockForUpdate()
                ->first();
            $now = now();

            if ($existing) {
                $existingArray = (array) $existing;
                if ($operation !== 'extend_adaptive_domain') {
                    throw new RuntimeException('Adaptive domain slug already exists; only an explicit approved extension may update it.');
                }
                if (
                    $target !== ''
                    && $target !== (string) ($existingArray['capability_key'] ?? '')
                    && $target !== (string) ($existingArray['public_id'] ?? '')
                    && $target !== (string) ($existingArray['slug'] ?? '')
                ) {
                    throw new RuntimeException('Adaptive extension target does not match the existing domain.');
                }
                if ((string) ($blueprint['capability_key'] ?? '') !== (string) ($existingArray['capability_key'] ?? '')) {
                    throw new RuntimeException('Adaptive domain capability keys cannot change during extension.');
                }

                $version = max(((int) ($existingArray['version'] ?? 0)) + 1, (int) ($blueprint['version'] ?? 1));
                $versionedBlueprint = [...$blueprint, 'version' => $version];
                $this->db->table(self::DOMAINS)
                    ->where('company_id', $companyId)
                    ->where('id', $existingArray['id'])
                    ->update([
                        'source_request_public_id' => $requestPublicId,
                        'name' => (string) $versionedBlueprint['name'],
                        'description' => (string) ($versionedBlueprint['description'] ?? ''),
                        'version' => $version,
                        'risk' => (string) ($versionedBlueprint['risk'] ?? 'low'),
                        'status' => 'active',
                        'blueprint' => json_encode($versionedBlueprint, JSON_THROW_ON_ERROR),
                        'updated_by_user_id' => $actorId,
                        'updated_at' => $now,
                    ]);
                $domainId = (int) $existingArray['id'];
                $changeType = 'extension';
            } else {
                if ($operation === 'extend_adaptive_domain') {
                    throw new RuntimeException('The adaptive domain selected for extension no longer exists.');
                }
                $version = max(1, (int) ($blueprint['version'] ?? 1));
                $versionedBlueprint = [...$blueprint, 'version' => $version];
                $domainPublicId = (string) Str::ulid();
                $domainId = (int) $this->db->table(self::DOMAINS)->insertGetId([
                    'public_id' => $domainPublicId,
                    'company_id' => $companyId,
                    'source_request_public_id' => $requestPublicId,
                    'slug' => (string) $versionedBlueprint['slug'],
                    'name' => (string) $versionedBlueprint['name'],
                    'capability_key' => (string) $versionedBlueprint['capability_key'],
                    'description' => (string) ($versionedBlueprint['description'] ?? ''),
                    'version' => $version,
                    'risk' => (string) ($versionedBlueprint['risk'] ?? 'low'),
                    'status' => 'active',
                    'blueprint' => json_encode($versionedBlueprint, JSON_THROW_ON_ERROR),
                    'created_by_user_id' => $actorId,
                    'updated_by_user_id' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $changeType = $operation === 'extend_native_capability' ? 'native_capability_extension' : 'creation';
            }

            $this->insertDomainVersion(
                companyId: $companyId,
                domainId: $domainId,
                version: $version,
                blueprint: $versionedBlueprint,
                actorId: $actorId,
                changeType: $changeType,
                sourceRequestPublicId: $requestPublicId,
            );
            $this->markRequestStatus($companyId, $requestPublicId, 'activated');

            return $this->findAdaptiveDomain($companyId, (string) $versionedBlueprint['slug'])
                ?? throw new RuntimeException('Activated adaptive domain could not be reloaded.');
        });
    }

    public function findAdaptiveDomain(int $companyId, string $identifier, bool $activeOnly = false): ?array
    {
        $this->assertCompany($companyId);
        $builder = $this->db->table(self::DOMAINS)
            ->where('company_id', $companyId)
            ->where(static function ($query) use ($identifier): void {
                $query->where('public_id', $identifier)->orWhere('slug', $identifier)->orWhere('capability_key', $identifier);
            });
        if ($activeOnly) {
            $builder->where('status', 'active');
        }
        $row = $builder->first();

        return $row ? $this->domainRow((array) $row) : null;
    }

    public function findAdaptiveDomainByRequest(int $companyId, string $requestPublicId): ?array
    {
        $this->assertCompany($companyId);
        $row = $this->db->table(self::DOMAINS)
            ->where('company_id', $companyId)
            ->where('source_request_public_id', $requestPublicId)
            ->first();
        if (! $row) {
            $domainId = $this->db->table(self::VERSIONS)
                ->where('company_id', $companyId)
                ->where('source_request_public_id', $requestPublicId)
                ->orderByDesc('version')
                ->value('adaptive_domain_id');
            if ($domainId !== null) {
                $row = $this->db->table(self::DOMAINS)
                    ->where('company_id', $companyId)
                    ->where('id', (int) $domainId)
                    ->first();
            }
        }

        return $row ? $this->domainRow((array) $row) : null;
    }

    public function listAdaptiveDomains(int $companyId, ?string $status = null): array
    {
        $this->assertCompany($companyId);
        $rows = $this->db->table(self::DOMAINS)
            ->where('company_id', $companyId)
            ->when($status, static fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->get();

        return array_map(fn (mixed $row): array => $this->domainRow((array) $row), $rows->all());
    }

    public function listAdaptiveDomainVersions(int $companyId, array $domain, int $limit = 50): array
    {
        $this->assertCompany($companyId);
        $this->assertDomainCompany($companyId, $domain);
        $rows = $this->db->table(self::VERSIONS)
            ->where('company_id', $companyId)
            ->where('adaptive_domain_id', (int) $domain['id'])
            ->orderByDesc('version')
            ->limit(max(1, min($limit, 100)))
            ->get();

        return array_map(fn (mixed $row): array => $this->versionRow((array) $row), $rows->all());
    }

    public function restoreAdaptiveDomainVersion(int $companyId, int $actorId, array $domain, int $version): array
    {
        $this->assertActor($companyId, $actorId);
        $this->assertDomainCompany($companyId, $domain);

        return $this->db->transaction(function () use ($companyId, $actorId, $domain, $version): array {
            $current = $this->db->table(self::DOMAINS)
                ->where('company_id', $companyId)
                ->where('id', (int) $domain['id'])
                ->lockForUpdate()
                ->first();
            if (! $current) {
                throw new RuntimeException('Adaptive domain was not found.');
            }
            $snapshot = $this->db->table(self::VERSIONS)
                ->where('company_id', $companyId)
                ->where('adaptive_domain_id', (int) $domain['id'])
                ->where('version', $version)
                ->first();
            if (! $snapshot) {
                throw new RuntimeException('Requested adaptive domain version was not found.');
            }

            $currentArray = $this->domainRow((array) $current);
            $snapshotArray = $this->versionRow((array) $snapshot);
            $newVersion = ((int) $currentArray['version']) + 1;
            $blueprint = $this->rollbackMerger->merge(
                (array) $currentArray['blueprint'],
                (array) $snapshotArray['blueprint'],
                $version,
                $actorId,
            );
            $blueprint['version'] = $newVersion;
            $now = now();
            $this->db->table(self::DOMAINS)
                ->where('company_id', $companyId)
                ->where('id', (int) $domain['id'])
                ->update([
                    'name' => (string) $blueprint['name'],
                    'description' => (string) ($blueprint['description'] ?? ''),
                    'version' => $newVersion,
                    'risk' => (string) ($blueprint['risk'] ?? 'low'),
                    'status' => 'active',
                    'blueprint' => json_encode($blueprint, JSON_THROW_ON_ERROR),
                    'updated_by_user_id' => $actorId,
                    'updated_at' => $now,
                ]);
            $this->insertDomainVersion($companyId, (int) $domain['id'], $newVersion, $blueprint, $actorId, 'rollback', null);

            return $this->findAdaptiveDomain($companyId, (string) $domain['public_id'])
                ?? throw new RuntimeException('Restored adaptive domain could not be reloaded.');
        });
    }

    public function createAdaptiveRecord(int $companyId, int $actorId, array $domain, array $data, string $status): array
    {
        $this->assertActor($companyId, $actorId);
        $this->assertActiveDomain($companyId, $domain);
        $publicId = (string) Str::ulid();
        $now = now();
        $this->db->table(self::RECORDS)->insert([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'adaptive_domain_id' => (int) $domain['id'],
            'status' => $status,
            'data' => json_encode($data, JSON_THROW_ON_ERROR),
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findAdaptiveRecord($companyId, $publicId)
            ?? ['public_id' => $publicId, 'status' => $status, 'data' => $data, 'domain' => $this->domainSummary($domain)];
    }

    public function findAdaptiveRecord(int $companyId, string $publicId): ?array
    {
        $this->assertCompany($companyId);
        $row = $this->db->table(self::RECORDS)
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->first();
        if (! $row) {
            return null;
        }
        $domainRow = $this->db->table(self::DOMAINS)
            ->where('company_id', $companyId)
            ->where('id', (int) ((array) $row)['adaptive_domain_id'])
            ->first();
        if (! $domainRow) {
            throw new RuntimeException('Adaptive record domain was not found.');
        }

        return $this->recordRow((array) $row, $this->domainRow((array) $domainRow));
    }

    public function updateAdaptiveRecord(int $companyId, int $actorId, array $record, array $data, string $status): array
    {
        $this->assertActor($companyId, $actorId);
        if ((int) ($record['company_id'] ?? 0) !== $companyId) {
            throw new RuntimeException('Adaptive record does not belong to the active company.');
        }
        $updated = $this->db->table(self::RECORDS)
            ->where('company_id', $companyId)
            ->where('id', (int) $record['id'])
            ->update([
                'status' => $status,
                'data' => json_encode($data, JSON_THROW_ON_ERROR),
                'updated_by_user_id' => $actorId,
                'updated_at' => now(),
            ]);
        if ($updated < 1 && ! $this->db->table(self::RECORDS)->where('company_id', $companyId)->where('id', (int) $record['id'])->exists()) {
            throw new RuntimeException('Adaptive record was not found.');
        }

        return $this->findAdaptiveRecord($companyId, (string) $record['public_id'])
            ?? throw new RuntimeException('Updated adaptive record could not be reloaded.');
    }

    public function searchAdaptiveRecords(int $companyId, array $domain, ?string $query, ?string $status, int $limit = 50): array
    {
        $this->assertCompany($companyId);
        $this->assertDomainCompany($companyId, $domain);
        $rows = $this->db->table(self::RECORDS)
            ->where('company_id', $companyId)
            ->where('adaptive_domain_id', (int) $domain['id'])
            ->when($status, static fn ($builder) => $builder->where('status', $status))
            ->when($query, static fn ($builder) => $builder->where('data', 'like', '%' . $query . '%'))
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 100)))
            ->get();

        return array_map(fn (mixed $row): array => $this->recordRow((array) $row, $domain), $rows->all());
    }

    /** @param array<string,mixed> $blueprint */
    private function insertDomainVersion(
        int $companyId,
        int $domainId,
        int $version,
        array $blueprint,
        int $actorId,
        string $changeType,
        ?string $sourceRequestPublicId,
    ): void {
        $this->db->table(self::VERSIONS)->insert([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'adaptive_domain_id' => $domainId,
            'version' => $version,
            'change_type' => $changeType,
            'source_request_public_id' => $sourceRequestPublicId,
            'blueprint' => json_encode($blueprint, JSON_THROW_ON_ERROR),
            'created_by_user_id' => $actorId,
            'created_at' => now(),
        ]);
    }

    private function assertActor(int $companyId, int $actorId): void
    {
        $this->assertCompany($companyId);
        if ($actorId !== $this->actorId()) {
            throw new RuntimeException('Repository actor does not match the active WorkCore actor.');
        }
    }

    /** @param array<string,mixed> $domain */
    private function assertDomainCompany(int $companyId, array $domain): void
    {
        if ((int) ($domain['company_id'] ?? 0) !== $companyId) {
            throw new RuntimeException('Adaptive domain does not belong to the active company.');
        }
    }

    /** @param array<string,mixed> $domain */
    private function assertActiveDomain(int $companyId, array $domain): void
    {
        $this->assertDomainCompany($companyId, $domain);
        if ((string) ($domain['status'] ?? '') !== 'active') {
            throw new RuntimeException('Adaptive domain is not active for the current company.');
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function requestRow(array $row): array
    {
        $row['assessment'] = $this->decodeJson($row['assessment'] ?? null);
        $row['plan'] = $this->decodeJson($row['plan'] ?? null);
        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function domainRow(array $row): array
    {
        $row['blueprint'] = $this->decodeJson($row['blueprint'] ?? null);
        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function versionRow(array $row): array
    {
        $row['blueprint'] = $this->decodeJson($row['blueprint'] ?? null);
        return $row;
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $domain @return array<string,mixed> */
    private function recordRow(array $row, array $domain): array
    {
        $row['data'] = $this->decodeJson($row['data'] ?? null);
        $row['domain'] = $this->domainSummary($domain);
        return $row;
    }

    /** @param array<string,mixed> $domain @return array<string,mixed> */
    private function domainSummary(array $domain): array
    {
        return [
            'id' => $domain['id'] ?? null,
            'company_id' => $domain['company_id'] ?? null,
            'public_id' => $domain['public_id'] ?? null,
            'slug' => $domain['slug'] ?? null,
            'name' => $domain['name'] ?? null,
            'capability_key' => $domain['capability_key'] ?? null,
            'version' => $domain['version'] ?? null,
            'status' => $domain['status'] ?? null,
            'blueprint' => $domain['blueprint'] ?? [],
        ];
    }

    /** @return array<string,mixed> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }
}
