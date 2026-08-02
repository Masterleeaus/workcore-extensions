<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Codebooks;

use App\Domains\WorkCore\System\Verticals\CompiledVerticalProfile;

final class CodebookResolver
{
    public function __construct(private CodebookRegistry $registry) {}

    /** @return list<string> */
    public function values(CompiledVerticalProfile $profile, string $key): array
    {
        $canonical = $this->registry->get($key);
        if (! $profile->isConfigured()) {
            return array_keys($canonical['values']);
        }

        $rule = $profile->codebook($key);
        $values = array_values(array_unique(array_map('strval', (array) ($rule['include'] ?? []))));
        if ($values === []) {
            $values = array_keys($canonical['values']);
        }
        $excluded = array_fill_keys(array_map('strval', (array) ($rule['exclude'] ?? [])), true);
        $values = array_values(array_filter($values, static fn (string $value): bool => ! isset($excluded[$value])));
        $this->registry->assertKnown($key, $values);
        return $values;
    }

    public function default(CompiledVerticalProfile $profile, string $key): ?string
    {
        $rule = $profile->codebook($key);
        $default = isset($rule['default']) ? (string) $rule['default'] : $this->registry->get($key)['default'];
        if ($default !== null) {
            $this->registry->assertKnown($key, [$default]);
        }
        return $default;
    }

    public function label(CompiledVerticalProfile $profile, string $key, string $value): string
    {
        $rule = $profile->codebook($key);
        $label = $rule['labels'][$value] ?? null;
        return is_string($label) && $label !== '' ? $label : $this->registry->label($key, $value);
    }

    /** @return list<array{value:string,label:string,default:bool}> */
    public function options(CompiledVerticalProfile $profile, string $key): array
    {
        $default = $this->default($profile, $key);
        return array_map(fn (string $value): array => [
            'value' => $value,
            'label' => $this->label($profile, $key, $value),
            'default' => $value === $default,
        ], $this->values($profile, $key));
    }
}
