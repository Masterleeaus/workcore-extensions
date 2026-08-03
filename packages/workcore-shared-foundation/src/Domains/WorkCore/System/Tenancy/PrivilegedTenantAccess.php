<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Tenancy;

use App\Domains\WorkCore\System\Contracts\PrivilegedTenantAccessContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

final class PrivilegedTenantAccess implements PrivilegedTenantAccessContract
{
    private const TABLE = 'tz_privileged_tenant_access_audits';

    private int $depth = 0;

    public function __construct(
        private ConnectionInterface $db,
        private LoggerInterface $logger,
    ) {}

    public function isActive(): bool
    {
        return $this->depth > 0;
    }

    public function run(int|string $actorId, string $reason, callable $callback): mixed
    {
        $actor = trim((string) $actorId);
        $reason = trim($reason);
        if ($actor === '' || $actor === '0') {
            throw new InvalidArgumentException('Privileged WorkCore tenant access requires an actor identifier.');
        }
        if ($reason === '') {
            throw new InvalidArgumentException('Privileged WorkCore tenant access requires a reason.');
        }

        $auditId = (string) Str::ulid();
        $now = now();
        $this->db->table(self::TABLE)->insert([
            'public_id' => $auditId,
            'actor_id' => $actor,
            'reason' => mb_substr($reason, 0, 1000),
            'status' => 'started',
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->logger->warning('workcore.cross_company_access.started', [
            'audit_id' => $auditId,
            'actor_id' => $actor,
            'reason' => $reason,
        ]);

        $this->depth++;
        try {
            $result = $callback();
            $completedAt = now();
            $this->db->table(self::TABLE)
                ->where('public_id', $auditId)
                ->where('status', 'started')
                ->update([
                    'status' => 'completed',
                    'completed_at' => $completedAt,
                    'updated_at' => $completedAt,
                ]);
            $this->logger->info('workcore.cross_company_access.completed', [
                'audit_id' => $auditId,
                'actor_id' => $actor,
            ]);

            return $result;
        } catch (Throwable $exception) {
            try {
                $failedAt = now();
                $this->db->table(self::TABLE)
                    ->where('public_id', $auditId)
                    ->update([
                        'status' => 'failed',
                        'error_message' => mb_substr($exception->getMessage(), 0, 1000),
                        'failed_at' => $failedAt,
                        'updated_at' => $failedAt,
                    ]);
            } catch (Throwable $auditException) {
                $this->logger->critical('workcore.cross_company_access.audit_failed', [
                    'audit_id' => $auditId,
                    'actor_id' => $actor,
                    'audit_error' => $auditException->getMessage(),
                ]);
            }
            $this->logger->error('workcore.cross_company_access.failed', [
                'audit_id' => $auditId,
                'actor_id' => $actor,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            $this->depth--;
        }
    }
}
