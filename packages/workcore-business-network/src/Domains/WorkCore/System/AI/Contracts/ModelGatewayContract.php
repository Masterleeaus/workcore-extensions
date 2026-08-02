<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\AI\Contracts;

use App\Domains\WorkCore\System\AI\DTO\{ModelRequest,ModelResponse};

interface ModelGatewayContract
{
    public function generate(ModelRequest $request): ModelResponse;
}
