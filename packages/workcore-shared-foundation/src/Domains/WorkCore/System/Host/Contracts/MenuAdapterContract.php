<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host\Contracts;

interface MenuAdapterContract
{
    public function register(): void;
    public function clearCache(): void;
}
