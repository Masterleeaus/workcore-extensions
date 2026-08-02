<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Documents\Services;

use JsonException;

final class CanonicalPayloadHasher
{
    /** @throws JsonException */
    public function hash(array $payload): string
    {
        $normalised = $this->normalise($payload);
        return hash('sha256', json_encode($normalised, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function normalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalise($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalise($item);
        }

        return $value;
    }
}
