<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Expansion\Services;

use InvalidArgumentException;

final class AdaptiveFeatureValueResolver
{
    /** @param array<string,mixed> $source @param array<string,mixed> $context */
    public function resolve(array $source, array $context): mixed
    {
        if (array_key_exists('value', $source)) {
            return $source['value'];
        }
        $path = trim((string) ($source['from'] ?? ''));
        if ($path === '') {
            throw new InvalidArgumentException('Adaptive feature value requires a literal value or context path.');
        }
        $cursor = $context;
        foreach (explode('.', $path) as $segment) {
            if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                throw new InvalidArgumentException("Adaptive feature context path [{$path}] was not found.");
            }
            $cursor = $cursor[$segment];
        }
        return $cursor;
    }

    /** @param array<string,array<string,mixed>> $map @param array<string,mixed> $context @return array<string,mixed> */
    public function resolveMap(array $map, array $context): array
    {
        $resolved = [];
        foreach ($map as $key => $source) {
            $resolved[(string) $key] = $this->resolve($source, $context);
        }
        return $resolved;
    }
}
