<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Persistence;

use App\Domains\WorkCore\System\AI\Contracts\ModelProfileResolverContract;
use App\Domains\WorkCore\System\AI\DTO\{ModelRequest,ResolvedModelProfile};
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;

final class DatabaseModelProfileResolver implements ModelProfileResolverContract
{
    public function __construct(private ConnectionInterface $db) {}

    public function resolve(ModelRequest $request): array
    {
        $profiles = [];
        if (Schema::hasTable('tz_ai_model_profiles') && Schema::hasTable('tz_ai_provider_profiles')) {
            $rows = $this->db->table('tz_ai_model_profiles as m')
                ->join('tz_ai_provider_profiles as p', function ($join): void {
                    $join->on('p.id', '=', 'm.provider_profile_id')->on('p.company_id', '=', 'm.company_id');
                })
                ->where('m.company_id', $request->companyId)
                ->where('m.is_active', true)
                ->where('p.is_active', true)
                ->whereNull('m.deleted_at')
                ->whereNull('p.deleted_at')
                ->whereIn('m.purpose', [$request->purpose, '*'])
                ->when($request->model !== null && $request->model !== '', fn ($query) => $query->where('m.model_key', $request->model))
                ->orderByDesc('m.is_default')
                ->orderBy('m.priority')
                ->orderBy('p.priority')
                ->get([
                    'm.public_id as model_profile_public_id', 'm.model_key', 'm.purpose', 'm.settings as model_settings', 'm.priority as model_priority',
                    'p.public_id as provider_profile_public_id', 'p.provider_key', 'p.name as provider_name', 'p.base_url', 'p.secret_reference', 'p.settings as provider_settings', 'p.priority as provider_priority',
                ]);
            foreach ($rows as $row) {
                $profiles[] = new ResolvedModelProfile(
                    companyId: $request->companyId,
                    providerKey: (string) $row->provider_key,
                    providerName: (string) $row->provider_name,
                    baseUrl: (string) ($row->base_url ?? ''),
                    secretReference: $row->secret_reference === null ? null : (string) $row->secret_reference,
                    model: (string) $row->model_key,
                    purpose: (string) $row->purpose,
                    priority: ((int) $row->model_priority * 1000) + (int) $row->provider_priority,
                    providerProfilePublicId: (string) $row->provider_profile_public_id,
                    modelProfilePublicId: (string) $row->model_profile_public_id,
                    providerSettings: $this->decode($row->provider_settings),
                    modelSettings: $this->decode($row->model_settings),
                );
            }
        }

        if ($profiles !== []) {
            return $profiles;
        }

        foreach ((array) config('workcore_ai.fallback_profiles', []) as $index => $profile) {
            if (! is_array($profile) || ! ($profile['enabled'] ?? true)) {
                continue;
            }
            $purpose = (string) ($profile['purpose'] ?? '*');
            if (! in_array($purpose, ['*', $request->purpose], true)) {
                continue;
            }
            if ($request->model !== null && $request->model !== '' && (string) ($profile['model'] ?? '') !== $request->model) {
                continue;
            }
            $profiles[] = new ResolvedModelProfile(
                companyId: $request->companyId,
                providerKey: (string) ($profile['provider'] ?? 'null'),
                providerName: (string) ($profile['name'] ?? $profile['provider'] ?? 'Null provider'),
                baseUrl: (string) ($profile['base_url'] ?? ''),
                secretReference: isset($profile['secret_reference']) ? (string) $profile['secret_reference'] : null,
                model: (string) ($profile['model'] ?? 'disabled'),
                purpose: $purpose,
                priority: (int) ($profile['priority'] ?? $index + 100),
                providerSettings: (array) ($profile['provider_settings'] ?? []),
                modelSettings: (array) ($profile['model_settings'] ?? []),
            );
        }

        usort($profiles, static fn (ResolvedModelProfile $a, ResolvedModelProfile $b): int => $a->priority <=> $b->priority);
        return $profiles;
    }

    /** @return array<string,mixed> */
    private function decode(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }
}
