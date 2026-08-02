<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Contracts;

interface ExpansionRepositoryContract
{
    /** @return array<int,array<string,mixed>> */
    public function activeDomains(int $companyId): array;

    /** @param array<string,mixed> $assessment @param array<string,mixed> $plan @return array<string,mixed> */
    public function createRequest(
        int $companyId,
        int $actorId,
        string $requirement,
        string $normalisedRequirement,
        array $assessment,
        array $plan,
        string $status,
    ): array;

    /** @return array<string,mixed>|null */
    public function findRequest(int $companyId, string $publicId): ?array;

    /** @return array<int,array<string,mixed>> */
    public function listRequests(int $companyId, ?string $status = null, ?string $deliveryMode = null, int $limit = 50): array;

    /** @return array<string,mixed> */
    public function approveRequest(int $companyId, string $publicId, int $actorId, ?string $notes = null): array;

    /** @return array<string,mixed> */
    public function markRequestStatus(int $companyId, string $publicId, string $status): array;

    /** @param array<string,mixed> $blueprint @return array<string,mixed> */
    public function activateAdaptiveDomain(int $companyId, string $requestPublicId, int $actorId, array $blueprint): array;

    /** @return array<string,mixed>|null */
    public function findAdaptiveDomain(int $companyId, string $identifier, bool $activeOnly = false): ?array;

    /** @return array<string,mixed>|null */
    public function findAdaptiveDomainByRequest(int $companyId, string $requestPublicId): ?array;

    /** @return array<int,array<string,mixed>> */
    public function listAdaptiveDomains(int $companyId, ?string $status = null): array;

    /** @return array<int,array<string,mixed>> */
    public function listAdaptiveDomainVersions(int $companyId, array $domain, int $limit = 50): array;

    /** @return array<string,mixed> */
    public function restoreAdaptiveDomainVersion(int $companyId, int $actorId, array $domain, int $version): array;

    /** @param array<string,mixed> $domain @param array<string,mixed> $data @return array<string,mixed> */
    public function createAdaptiveRecord(int $companyId, int $actorId, array $domain, array $data, string $status): array;

    /** @return array<string,mixed>|null */
    public function findAdaptiveRecord(int $companyId, string $publicId): ?array;

    /** @param array<string,mixed> $record @param array<string,mixed> $data @return array<string,mixed> */
    public function updateAdaptiveRecord(int $companyId, int $actorId, array $record, array $data, string $status): array;

    /** @return array<int,array<string,mixed>> */
    public function searchAdaptiveRecords(int $companyId, array $domain, ?string $query, ?string $status, int $limit = 50): array;
}
