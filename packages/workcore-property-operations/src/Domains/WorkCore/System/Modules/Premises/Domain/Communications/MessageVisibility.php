<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Domain\Communications;

final class MessageVisibility
{
    public const VALUES = ['portal', 'internal', 'restricted'];
    public static function portalCanRead(string $visibility): bool { return $visibility === 'portal'; }
}
