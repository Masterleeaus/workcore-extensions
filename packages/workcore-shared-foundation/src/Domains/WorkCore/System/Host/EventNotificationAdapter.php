<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Host;

use App\Domains\WorkCore\System\Host\Contracts\NotificationAdapterContract;
use App\Domains\WorkCore\System\Notifications\{NotificationDispatcherContract, NotificationIntent};
use Illuminate\Contracts\Events\Dispatcher;

final class EventNotificationAdapter implements NotificationAdapterContract, NotificationDispatcherContract
{
    public function __construct(private Dispatcher $events) {}

    public function dispatch(NotificationIntent $intent): void
    {
        $this->events->dispatch($intent);
    }
}
