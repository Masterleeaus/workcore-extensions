<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Context;

use Illuminate\Support\Str;

final class ContextIdNormalizer
{
    private const MAX_LENGTH = 128;

    public function internal(?string $candidate = null): string
    {
        $normalised = $this->normalise($candidate);

        return $normalised ?? (string) Str::ulid();
    }

    public function optional(?string $candidate): ?string
    {
        return $this->normalise($candidate);
    }

    private function normalise(?string $candidate): ?string
    {
        if ($candidate === null) {
            return null;
        }

        $candidate = trim($candidate);
        if ($candidate === '' || strlen($candidate) > self::MAX_LENGTH) {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\-]*$/', $candidate)) {
            return null;
        }

        return $candidate;
    }
}
