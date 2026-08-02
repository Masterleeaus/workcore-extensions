<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use InvalidArgumentException;

class ComplianceRulePackRegistry
{
    public function packs(): array
    {
        return config('managedpremises_compliance_rule_packs.packs', []);
    }

    public function disclaimer(): string
    {
        return (string) config('managedpremises_compliance_rule_packs.disclaimer', 'Templates require local verification.');
    }

    public function get(string $key): array
    {
        $pack = $this->packs()[$key] ?? null;
        if (!$pack) {
            throw new InvalidArgumentException("Unknown compliance rule pack: {$key}");
        }

        return array_merge($pack, ['key' => $key, 'disclaimer' => $pack['disclaimer'] ?? $this->disclaimer()]);
    }

    public function labels(): array
    {
        return collect($this->packs())->mapWithKeys(
            fn (array $pack, string $key) => [$key => $pack['name'] ?? $key]
        )->all();
    }

    public function forProfile(?string $profileKey): array
    {
        return collect($this->packs())
            ->filter(fn (array $pack) => empty($pack['profile_key']) || $pack['profile_key'] === $profileKey)
            ->all();
    }
}
