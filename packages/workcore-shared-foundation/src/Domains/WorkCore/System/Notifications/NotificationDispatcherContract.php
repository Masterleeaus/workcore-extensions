<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Notifications;

interface NotificationDispatcherContract
{
    public function dispatch(NotificationIntent $intent): void;
}
