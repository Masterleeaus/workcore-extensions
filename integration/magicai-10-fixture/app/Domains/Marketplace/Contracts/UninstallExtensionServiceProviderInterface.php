<?php

declare(strict_types=1);

namespace App\Domains\Marketplace\Contracts;

interface UninstallExtensionServiceProviderInterface
{
    public static function uninstall(): void;
}
