<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host;

use App\Domains\WorkCore\System\Host\Contracts\NotificationAdapterContract;
use App\Domains\WorkCore\System\Notifications\NotificationDispatcherContract;
use App\Domains\WorkCore\System\Notifications\NotificationIntent;

final class MagicAINotificationAdapter implements NotificationAdapterContract, NotificationDispatcherContract
{
    public function dispatch(NotificationIntent $intent): void
    {
        event('workcore.notification.intent', [$intent]);
    }
}
