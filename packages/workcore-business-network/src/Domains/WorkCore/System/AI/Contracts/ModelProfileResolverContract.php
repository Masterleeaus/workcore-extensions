<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Contracts;

use App\Domains\WorkCore\System\AI\DTO\ModelRequest;
use App\Domains\WorkCore\System\AI\DTO\ResolvedModelProfile;

interface ModelProfileResolverContract
{
    /** @return list<ResolvedModelProfile> */
    public function resolve(ModelRequest $request): array;
}
