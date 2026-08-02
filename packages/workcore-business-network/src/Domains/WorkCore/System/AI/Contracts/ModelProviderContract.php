<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Contracts;

use App\Domains\WorkCore\System\AI\DTO\ModelRequest;
use App\Domains\WorkCore\System\AI\DTO\ModelResponse;

interface ModelProviderContract
{
    public function key(): string;

    public function generate(ModelRequest $request): ModelResponse;

    /** @return array{ok:bool,provider:string,reason:?string} */
    public function health(): array;
}
