<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Contracts;

use App\Domains\WorkCore\System\AI\DTO\ModelRequest;
use App\Domains\WorkCore\System\AI\DTO\ModelResponse;
use App\Domains\WorkCore\System\AI\DTO\ResolvedModelProfile;
use Throwable;

interface AiRunRecorderContract
{
    public function start(ModelRequest $request, ?ResolvedModelProfile $profile = null): string;
    public function complete(string $runPublicId, ModelResponse $response, ?ResolvedModelProfile $profile = null): void;
    public function fail(string $runPublicId, Throwable $exception, ?ResolvedModelProfile $profile = null): void;
}
