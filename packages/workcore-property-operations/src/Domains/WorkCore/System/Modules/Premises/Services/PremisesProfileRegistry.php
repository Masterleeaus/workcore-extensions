<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class PremisesProfileRegistry
{
    public function profiles(): array
    {
        return config('managedpremises.profiles', config('managedpremises_profiles.profiles', []));
    }

    public function premiseTypes(): array
    {
        return config('managedpremises.premise_types', config('managedpremises_profiles.premise_types', Property::PROPERTY_TYPES));
    }

    public function defaultKey(): string
    {
        return (string) config('managedpremises.default', config('managedpremises_profiles.default', Property::DEFAULT_PROFILE));
    }

    public function keys(): array
    {
        return array_keys($this->profiles());
    }

    public function labels(): array
    {
        return collect($this->profiles())
            ->mapWithKeys(fn (array $profile, string $key) => [$key => $profile['label'] ?? $key])
            ->all();
    }

    public function resolve(string|null $key, array $terminologyOverrides = [], array $featureOverrides = []): array
    {
        $profiles = $this->profiles();
        $requestedKey = $key ?: $this->defaultKey();
        $resolvedKey = array_key_exists($requestedKey, $profiles) ? $requestedKey : 'custom';
        $profile = $profiles[$resolvedKey] ?? [
            'label' => 'Custom Premises',
            'description' => '',
            'terminology' => [],
            'features' => [],
            'default_type' => 'other',
        ];

        $profile['key'] = $requestedKey;
        $profile['resolved_key'] = $resolvedKey;
        $profile['terminology'] = array_replace($profile['terminology'] ?? [], $terminologyOverrides);
        $profile['features'] = array_replace($profile['features'] ?? [], $featureOverrides);

        return $profile;
    }

    public function forProperty(Property $property): array
    {
        return $this->resolve(
            $property->profile_key,
            $property->terminology_overrides ?? [],
            $property->feature_overrides ?? []
        );
    }
}
