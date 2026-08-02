<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Orchestration;

use App\Domains\WorkCore\System\AI\Orchestration\Contracts\AgentProfileResolverContract;
use App\Domains\WorkCore\System\AI\Orchestration\DTO\ResolvedAgentProfile;
use App\Domains\WorkCore\System\AI\Orchestration\Exceptions\AiOrchestrationException;
use Illuminate\Database\ConnectionInterface;

final class DatabaseAgentProfileResolver implements AgentProfileResolverContract
{
    public function __construct(private ConnectionInterface $db) {}

    public function resolve(int $companyId, ?string $agentPublicId): ResolvedAgentProfile
    {
        $default = (array) config('workcore_ai.orchestration.default_agent', []);
        $query = $this->db->table('tz_ai_agents as a')
            ->join('tz_ai_agent_versions as v', function ($join): void {
                $join->on('v.agent_id', '=', 'a.id')
                    ->on('v.company_id', '=', 'a.company_id');
            })
            ->leftJoin('tz_ai_model_profiles as m', function ($join): void {
                $join->on('m.id', '=', $this->db->raw('COALESCE(v.model_profile_id, a.default_model_profile_id)'))
                    ->on('m.company_id', '=', 'a.company_id');
            })
            ->where('a.company_id', $companyId)
            ->where('a.status', 'active')
            ->where('v.status', 'published')
            ->whereNull('a.deleted_at')
            ->whereNull('v.deleted_at');

        if ($agentPublicId !== null && trim($agentPublicId) !== '') {
            $query->where('a.public_id', $agentPublicId);
        } elseif (($default['key'] ?? null) !== null) {
            $query->where('a.agent_key', (string) $default['key']);
        }

        $row = $query->orderByDesc('v.version')->first([
            'a.public_id as agent_public_id', 'a.agent_key', 'a.name', 'a.settings as agent_settings',
            'v.public_id as version_public_id', 'v.system_prompt', 'v.tool_allowlist', 'v.guardrails',
            'm.model_key',
        ]);

        if ($row !== null) {
            return new ResolvedAgentProfile(
                (string) $row->agent_public_id,
                (string) $row->version_public_id,
                (string) $row->agent_key,
                (string) $row->name,
                (string) $row->system_prompt,
                $this->stringList($row->tool_allowlist ?? null, ['*']),
                $this->arrayValue($row->guardrails ?? null),
                $row->model_key === null ? null : (string) $row->model_key,
                $this->arrayValue($row->agent_settings ?? null),
            );
        }

        if ($agentPublicId !== null && trim($agentPublicId) !== '') {
            throw new AiOrchestrationException('The requested AI agent was not found or has no published version.', 404);
        }

        return new ResolvedAgentProfile(
            null,
            null,
            (string) ($default['key'] ?? 'titan-zero'),
            (string) ($default['name'] ?? 'Titan Zero'),
            (string) ($default['system_prompt'] ?? 'You are Titan Zero, the native WorkCore business assistant. Use only the tools exposed for the active company and actor.'),
            $this->stringList($default['tool_allowlist'] ?? ['*'], ['*']),
            $this->arrayValue($default['guardrails'] ?? []),
            isset($default['model']) ? (string) $default['model'] : null,
            $this->arrayValue($default['settings'] ?? []),
        );
    }

    /** @return array<string,mixed> */
    private function arrayValue(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }

    /** @param list<string> $fallback @return list<string> */
    private function stringList(mixed $value, array $fallback): array
    {
        $items = $this->arrayValue($value);
        if (array_is_list($items)) {
            $items = array_values(array_filter(array_map(static fn ($item): string => trim((string) $item), $items), static fn (string $item): bool => $item !== ''));
        } else {
            $items = [];
        }
        return $items === [] ? $fallback : $items;
    }
}
