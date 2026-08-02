<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Contracts;

use DateTimeImmutable;

interface AdaptiveFeatureRepositoryContract
{
    /** @param array<string,mixed> $blueprint @return array<string,mixed> */
    public function activateFeature(int $companyId, string $requestPublicId, int $actorId, array $blueprint, ?array $domain): array;

    /** @return array<string,mixed>|null */
    public function findFeature(int $companyId, string $identifier, bool $activeOnly = false): ?array;

    /** @return array<string,mixed>|null */
    public function findFeatureByRequest(int $companyId, string $requestPublicId): ?array;

    /** @return array<int,array<string,mixed>> */
    public function listFeatures(int $companyId, ?string $status = null, ?string $event = null, int $limit = 100): array;

    /** @return array<int,array<string,mixed>> */
    public function activeFeaturesForEvent(int $companyId, string $event, string $subjectType, ?string $domain = null): array;

    /** @return array<string,mixed> */
    public function disableFeature(int $companyId, string $identifier, int $actorId, string $reason): array;

    /** @return array<string,mixed>|null */
    public function findRunByIdempotency(int $companyId, string $idempotencyKey): ?array;

    /** @param array<string,mixed> $feature @param array<string,mixed> $context @param array<string,mixed> $simulation @return array<string,mixed> */
    public function startRun(
        int $companyId,
        int $actorId,
        array $feature,
        string $event,
        string $subjectType,
        string $subjectPublicId,
        string $idempotencyKey,
        int $executionDepth,
        array $context,
        array $simulation,
    ): array;

    /** @param array<string,mixed> $output @return array<string,mixed> */
    public function completeRun(int $companyId, string $runPublicId, array $output, string $status = 'completed'): array;

    /** @return array<string,mixed> */
    public function failRun(int $companyId, string $runPublicId, string $error): array;

    /** @param array<string,mixed> $feature @return array{executions_last_hour:int,last_executed_at:?DateTimeImmutable} */
    public function executionStats(int $companyId, array $feature, string $subjectPublicId): array;

    /** @param array<string,mixed> $feature @return array<int,array<string,mixed>> */
    public function listRuns(int $companyId, array $feature, ?string $status = null, int $limit = 100): array;
}
