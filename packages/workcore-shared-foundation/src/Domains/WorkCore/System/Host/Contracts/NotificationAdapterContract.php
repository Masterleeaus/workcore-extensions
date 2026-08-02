<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host\Contracts;

use App\Domains\WorkCore\System\Notifications\NotificationIntent;

interface NotificationAdapterContract
{
    public function dispatch(NotificationIntent $intent): void;
}
