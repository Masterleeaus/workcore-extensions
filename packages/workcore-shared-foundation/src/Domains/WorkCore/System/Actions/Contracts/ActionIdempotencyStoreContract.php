<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Actions\Contracts;

use App\Domains\WorkCore\System\Actions\ActionResult;
use App\Domains\WorkCore\System\Actions\ActionRequest;

interface ActionIdempotencyStoreContract
{
    public function replay(ActionRequest $request): ?ActionResult;
    public function reserve(ActionRequest $request): void;
    public function complete(ActionRequest $request, ActionResult $result): void;
    public function fail(ActionRequest $request, string $message): void;
}
