<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration\Persistence;

use App\Domains\WorkCore\System\AI\Orchestration\Contracts\MemoryStoreContract;
use App\Domains\WorkCore\System\AI\Orchestration\DTO\MemoryItem;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final class DatabaseMemoryStore implements MemoryStoreContract
{
    public function __construct(private ConnectionInterface $db) {}

    public function recall(int $companyId, int $actorId, ?string $agentPublicId, int $limit): array
    {
        $rows = $this->baseQuery($companyId, $actorId, $agentPublicId)
            ->orderByDesc('confidence')
            ->orderByDesc('updated_at')
            ->limit(max(1, min(100, $limit)))
            ->get();

        if ($rows->isNotEmpty()) {
            $this->db->table('tz_ai_memories')->whereIn('id', $rows->pluck('id')->all())->update(['last_used_at' => now()]);
        }

        return array_map(fn ($row): MemoryItem => $this->map($row), $rows->all());
    }

    public function remember(
        int $companyId,
        int $actorId,
        ?string $agentPublicId,
        string $scope,
        string $key,
        string $value,
        float $confidence,
        string $source,
        ?string $expiresAt = null,
    ): MemoryItem {
        $existing = $this->db->table('tz_ai_memories')
            ->where('company_id', $companyId)
            ->where('owner_user_id', $actorId)
            ->where('scope', $scope)
            ->where('memory_key', $key)
            ->when($agentPublicId === null, fn ($q) => $q->whereNull('agent_public_id'), fn ($q) => $q->where('agent_public_id', $agentPublicId))
            ->whereNull('deleted_at')
            ->first(['id','public_id']);

        $now = now();
        $values = [
            'agent_public_id' => $agentPublicId,
            'memory_value' => $value,
            'confidence' => $confidence,
            'source' => $source,
            'provenance' => json_encode(['created_by' => 'explicit_workcore_request'], JSON_THROW_ON_ERROR),
            'expires_at' => $expiresAt,
            'updated_at' => $now,
        ];

        if ($existing === null) {
            $publicId = (string) Str::ulid();
            $this->db->table('tz_ai_memories')->insert($values + [
                'public_id' => $publicId,
                'company_id' => $companyId,
                'owner_user_id' => $actorId,
                'scope' => $scope,
                'memory_key' => $key,
                'created_at' => $now,
            ]);
        } else {
            $publicId = (string) $existing->public_id;
            $this->db->table('tz_ai_memories')->where('id', $existing->id)->update($values);
        }

        $row = $this->db->table('tz_ai_memories')->where('company_id', $companyId)->where('public_id', $publicId)->first();
        return $this->map($row);
    }

    public function forget(int $companyId, int $actorId, string $memoryPublicId): bool
    {
        return $this->db->table('tz_ai_memories')
            ->where('company_id', $companyId)
            ->where('owner_user_id', $actorId)
            ->where('public_id', $memoryPublicId)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]) === 1;
    }

    public function listForActor(int $companyId, int $actorId, int $limit = 100): array
    {
        $rows = $this->baseQuery($companyId, $actorId, null)
            ->orderBy('scope')
            ->orderBy('memory_key')
            ->limit(max(1, min(200, $limit)))
            ->get();
        return array_map(fn ($row): MemoryItem => $this->map($row), $rows->all());
    }

    private function baseQuery(int $companyId, int $actorId, ?string $agentPublicId)
    {
        return $this->db->table('tz_ai_memories')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($actorId): void {
                $query->where('scope', 'company')->orWhere(function ($inner) use ($actorId): void {
                    $inner->where('scope', 'user')->where('owner_user_id', $actorId);
                });
            })
            ->where(function ($query) use ($agentPublicId): void {
                $query->whereNull('agent_public_id');
                if ($agentPublicId !== null) {
                    $query->orWhere('agent_public_id', $agentPublicId);
                }
            })
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    private function map(object $row): MemoryItem
    {
        return new MemoryItem(
            (string) $row->public_id,
            (int) $row->company_id,
            (int) $row->owner_user_id,
            $row->agent_public_id === null ? null : (string) $row->agent_public_id,
            (string) $row->scope,
            (string) $row->memory_key,
            (string) $row->memory_value,
            (float) $row->confidence,
            (string) $row->source,
            $row->expires_at === null ? null : (string) $row->expires_at,
        );
    }
}
