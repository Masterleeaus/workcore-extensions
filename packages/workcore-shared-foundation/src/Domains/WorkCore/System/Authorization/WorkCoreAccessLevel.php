<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Authorization;

enum WorkCoreAccessLevel: string
{
    case All = 'all';
    case Added = 'added';
    case Owned = 'owned';
    case Both = 'both';
    case None = 'none';

    public function grantsAnyAccess(): bool
    {
        return $this !== self::None;
    }

    public function allowsAny(): bool
    {
        return $this->grantsAnyAccess();
    }
}
