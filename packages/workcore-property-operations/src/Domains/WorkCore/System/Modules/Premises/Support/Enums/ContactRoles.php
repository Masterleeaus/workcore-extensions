<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Support\Enums;

class ContactRoles
{
    public const OWNER = 'owner';
    public const AGENT = 'agent';
    public const TENANT = 'tenant';
    public const TRADIE = 'tradie';
    public const CLEANER = 'cleaner';
    public const EMERGENCY = 'emergency';

    public static function all(): array
    {
        return [self::OWNER, self::AGENT, self::TENANT, self::TRADIE, self::CLEANER, self::EMERGENCY];
    }
}
