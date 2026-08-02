<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host;

use App\Domains\WorkCore\System\Host\Contracts\MenuAdapterContract;

final class NullMenuAdapter implements MenuAdapterContract
{
    public function register(): void {}

    public function clearCache(): void {}
}
