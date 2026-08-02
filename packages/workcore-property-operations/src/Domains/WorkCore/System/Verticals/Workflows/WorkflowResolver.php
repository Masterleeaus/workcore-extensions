<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Verticals\Workflows;

use App\Domains\WorkCore\System\Verticals\CompiledVerticalProfile;

final class WorkflowResolver
{
    public function allows(CompiledVerticalProfile $profile, string $key, string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }
        $workflow = $profile->workflow($key);
        if ($workflow === []) {
            return false;
        }
        return in_array($to, array_map('strval', (array) (($workflow['transitions'] ?? [])[$from] ?? [])), true);
    }

    /** @return list<string> */
    public function next(CompiledVerticalProfile $profile, string $key, string $from): array
    {
        return array_values(array_unique(array_map('strval', (array) (($profile->workflow($key)['transitions'] ?? [])[$from] ?? []))));
    }
}
