<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Contracts;

interface ExtensionRegisterKeyProviderInterface
{
    public function registerKey(): string;
}
