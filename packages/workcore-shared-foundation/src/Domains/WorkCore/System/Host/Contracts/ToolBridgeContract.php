<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host\Contracts;

interface ToolBridgeContract
{
    /** @return array<string,array<string,mixed>> */
    public function tools(): array;
}
