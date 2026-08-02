<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Repositories;

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Expansion\Contracts\AdaptiveFeatureRepositoryContract;
use App\Domains\WorkCore\System\Persistence\TenantScopedRepository;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;

final class DatabaseAdaptiveFeatureRepository extends TenantScopedRepository implements AdaptiveFeatureRepositoryContract
{
    private const FEATURES = 'tz_adaptive_features';
    private const VERSIONS = 'tz_adaptive_feature_versions';
    private const RUNS = 'tz_adaptive_feature_runs';

    public function __construct(ConnectionInterface $db, TenantContextContract $tenant)
    {
        parent::__construct($db, $tenant);
    }

    public function activateFeature(int $companyId, string $requestPublicId, int $actorId, array $blueprint, ?array $domain): array
    {
        $this->assertActor($companyId, $actorId);

        return $this->db->transaction(function () use ($companyId, $requestPublicId, $actorId, $blueprint, $domain): array {
            $existing = $this->findFeatureByRequest($companyId, $requestPublicId);
            if ($existing !== null) {
                return $existing;
            }

            $publicId = (string) Str::ulid();
            $now = now();
            $this->db->table(self::FEATURES)->insert([
                'public_id' => $publicId,
                'company_id' => $companyId,
                'adaptive_domain_id' => $domain['id'] ?? null,
                'source_request_public_id' => $requestPublicId,
                'slug' => (string) $blueprint['slug'],
                'name' => (string) $blueprint['name'],
                'capability_key' => (string) $blueprint['capability_key'],
                'description' => (string) ($blueprint['description'] ?? ''),
                'version' => (int) ($blueprint['version'] ?? 1),
                'trigger_event' => (string) $blueprint['trigger']['event'],
                'subject_type' => (string) $blueprint['trigger']['subject_type'],
                'domain_key' => $blueprint['trigger']['domain'] ?? null,
                'risk' => (string) ($blueprint['risk'] ?? 'medium'),
                'status' => 'active',
                'blueprint' => json_encode($blueprint, JSON_THROW_ON_ERROR),
                'created_by_user_id' => $actorId,
                'updated_by_user_id' => $actorId,
                'activated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $feature = $this->findFeature($companyId, $publicId)
                ?? throw new RuntimeException('Activated adaptive feature could not be reloaded.');
            $this->insertVersion($companyId, (int) $feature['id'], (int) $feature['version'], $blueprint, $actorId, 'activated', $requestPublicId);

            return $feature;
        });
    }

    public function findFeature(int $companyId, string $identifier, bool $activeOnly = false): ?array
    {
        $this->assertCompany($companyId);
        $row = $this->db->table(self::FEATURES)
            ->where('company_id', $companyId)
            ->where(function ($query) use ($identifier): void {
                $query->where('public_id', $identifier)
                    ->orWhere('slug', $identifier)
                    ->orWhere('capability_key', $identifier);
            })
            ->when($activeOnly, static fn ($query) => $query->where('status', 'active'))
            ->first();

        return $row ? $this->featureRow((array) $row) : null;
    }

    public function findFeatureByRequest(int $companyId, string $requestPublicId): ?array
    {
        $this->assertCompany($companyId);
        $row = $this->db->table(self::FEATURES)
            ->where('company_id', $companyId)
            ->where('source_request_public_id', $requestPublicId)
            ->first();

        return $row ? $this->featureRow((array) $row) : null;
    }

    public function listFeatures(int $companyId, ?string $status = null, ?string $event = null, int $limit = 100): array
    {
        $this->assertCompany($companyId);
        $rows = $this->db->table(self::FEATURES)
            ->where('company_id', $companyId)
            ->when($status, static fn ($query) => $query->where('status', $status))
            ->when($event, static fn ($query) => $query->where('trigger_event', $event))
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 100)))
            ->get();

        return array_map(fn (mixed $row): array => $this->featureRow((array) $row), $rows->all());
    }

    public function activeFeaturesForEvent(int $companyId, string $event, string $subjectType, ?string $domain = null): array
    {
        $this->assertCompany($companyId);
        $rows = $this->db->table(self::FEATURES)
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('trigger_event', $event)
            ->where('subject_type', $subjectType)
            ->when($domain, static fn ($query) => $query->where(function ($nested) use ($domain): void {
                $nested->whereNull('domain_key')->orWhere('domain_key', $domain);
            }))
            ->orderBy('id')
            ->get();

        return array_map(fn (mixed $row): array => $this->featureRow((array) $row), $rows->all());
    }

    public function disableFeature(int $companyId, string $identifier, int $actorId, string $reason): array
    {
        $this->assertActor($companyId, $actorId);
        $feature = $this->findFeature($companyId, $identifier)
            ?? throw new RuntimeException('Adaptive feature was not found.');
        if ((string) $feature['status'] === 'disabled') {
            return $feature;
        }

        $blueprint = (array) $feature['blueprint'];
        $blueprint['metadata'] = [
            ...(array) ($blueprint['metadata'] ?? []),
            'disabled_reason' => trim($reason),
            'disabled_by_user_id' => $actorId,
        ];
        $version = ((int) $feature['version']) + 1;
        $blueprint['version'] = $version;
        $this->db->table(self::FEATURES)
            ->where('company_id', $companyId)
            ->where('id', (int) $feature['id'])
            ->update([
                'status' => 'disabled',
                'version' => $version,
                'blueprint' => json_encode($blueprint, JSON_THROW_ON_ERROR),
                'updated_by_user_id' => $actorId,
                'disabled_at' => now(),
                'updated_at' => now(),
            ]);
        $this->insertVersion($companyId, (int) $feature['id'], $version, $blueprint, $actorId, 'disabled', null);

        return $this->findFeature($companyId, (string) $feature['public_id'])
            ?? throw new RuntimeException('Disabled adaptive feature could not be reloaded.');
    }

    public function findRunByIdempotency(int $companyId, string $idempotencyKey): ?array
    {
        $this->assertCompany($companyId);
        $row = $this->db->table(self::RUNS)
            ->where('company_id', $companyId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        return $row ? $this->runRow((array) $row) : null;
    }

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
    ): array {
        $this->assertActor($companyId, $actorId);
        $existing = $this->findRunByIdempotency($companyId, $idempotencyKey);
        if ($existing !== null) {
            return $existing;
        }
        $publicId = (string) Str::ulid();
        $this->db->table(self::RUNS)->insertOrIgnore([
            'public_id' => $publicId,
            'company_id' => $companyId,
            'adaptive_feature_id' => (int) $feature['id'],
            'feature_version' => (int) $feature['version'],
            'trigger_event' => $event,
            'subject_type' => $subjectType,
            'subject_public_id' => $subjectPublicId,
            'idempotency_key' => $idempotencyKey,
            'execution_depth' => $executionDepth,
            'status' => 'running',
            'input_context' => json_encode($context, JSON_THROW_ON_ERROR),
            'simulation' => json_encode($simulation, JSON_THROW_ON_ERROR),
            'output' => json_encode([], JSON_THROW_ON_ERROR),
            'actor_id' => $actorId,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $this->findRunByIdempotency($companyId, $idempotencyKey)
            ?? throw new RuntimeException('Adaptive feature run could not be reloaded.');
    }

    public function completeRun(int $companyId, string $runPublicId, array $output, string $status = 'completed'): array
    {
        $this->assertCompany($companyId);
        if (! in_array($status, ['completed', 'skipped', 'partially_completed'], true)) {
            throw new RuntimeException("Adaptive feature run completion status [{$status}] is invalid.");
        }
        $this->db->table(self::RUNS)
            ->where('company_id', $companyId)
            ->where('public_id', $runPublicId)
            ->update([
                'status' => $status,
                'output' => json_encode($output, JSON_THROW_ON_ERROR),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        return $this->findRun($companyId, $runPublicId)
            ?? throw new RuntimeException('Completed adaptive feature run could not be reloaded.');
    }

    public function failRun(int $companyId, string $runPublicId, string $error): array
    {
        $this->assertCompany($companyId);
        $this->db->table(self::RUNS)
            ->where('company_id', $companyId)
            ->where('public_id', $runPublicId)
            ->update([
                'status' => 'failed',
                'error_message' => substr($error, 0, 4000),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        return $this->findRun($companyId, $runPublicId)
            ?? throw new RuntimeException('Failed adaptive feature run could not be reloaded.');
    }

    public function executionStats(int $companyId, array $feature, string $subjectPublicId): array
    {
        $this->assertCompany($companyId);
        $featureQuery = $this->db->table(self::RUNS)
            ->where('company_id', $companyId)
            ->where('adaptive_feature_id', (int) $feature['id']);
        $count = (clone $featureQuery)->where('started_at', '>=', now()->subHour())->count();
        $last = (clone $featureQuery)
            ->where('subject_public_id', $subjectPublicId)
            ->orderByDesc('started_at')
            ->value('started_at');
        return [
            'executions_last_hour' => (int) $count,
            'last_executed_at' => $last ? new DateTimeImmutable((string) $last) : null,
        ];
    }

    public function listRuns(int $companyId, array $feature, ?string $status = null, int $limit = 100): array
    {
        $this->assertCompany($companyId);
        $rows = $this->db->table(self::RUNS)
            ->where('company_id', $companyId)
            ->where('adaptive_feature_id', (int) $feature['id'])
            ->when($status, static fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 100)))
            ->get();
        return array_map(fn (mixed $row): array => $this->runRow((array) $row), $rows->all());
    }

    private function findRun(int $companyId, string $publicId): ?array
    {
        $row = $this->db->table(self::RUNS)
            ->where('company_id', $companyId)
            ->where('public_id', $publicId)
            ->first();
        return $row ? $this->runRow((array) $row) : null;
    }

    /** @param array<string,mixed> $blueprint */
    private function insertVersion(int $companyId, int $featureId, int $version, array $blueprint, int $actorId, string $changeType, ?string $requestPublicId): void
    {
        $this->db->table(self::VERSIONS)->insert([
            'public_id' => (string) Str::ulid(),
            'company_id' => $companyId,
            'adaptive_feature_id' => $featureId,
            'version' => $version,
            'change_type' => $changeType,
            'source_request_public_id' => $requestPublicId,
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

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function featureRow(array $row): array
    {
        $row['blueprint'] = $this->decodeJson($row['blueprint'] ?? null);
        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function runRow(array $row): array
    {
        $row['input_context'] = $this->decodeJson($row['input_context'] ?? null);
        $row['simulation'] = $this->decodeJson($row['simulation'] ?? null);
        $row['output'] = $this->decodeJson($row['output'] ?? null);
        return $row;
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
