<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Services;

use Illuminate\Validation\ValidationException;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;

class PremiseWorkflowRegistry
{
    public function operatingProfileKey(Property $property): string
    {
        $profiles = config('managedpremises_operations.profiles', []);
        $aliases = config('managedpremises_operations.profile_aliases', []);
        $key = $property->profileKey();
        $key = $aliases[$key] ?? $key;

        return array_key_exists($key, $profiles)
            ? $key
            : (string) config('managedpremises_operations.default_profile', 'generic');
    }

    public function profile(Property $property): array
    {
        $key = $this->operatingProfileKey($property);
        $profile = config("managedpremises_operations.profiles.{$key}", []);
        $profile['key'] = $key;

        return $profile;
    }

    public function templates(Property $property): array
    {
        return $this->profile($property)['workflows'] ?? [];
    }

    public function template(Property $property, string $templateKey): array
    {
        $template = $this->templates($property)[$templateKey] ?? null;
        if (!$template) {
            throw ValidationException::withMessages([
                'template_key' => 'The selected workflow is not available for this premises profile.',
            ]);
        }

        $template['key'] = $templateKey;
        return $template;
    }
}
