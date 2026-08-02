<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Console\Commands;

use App\Domains\WorkCore\System\Outbox\OutboxPublisherContract;
use Illuminate\Console\Command;

final class PublishWorkCoreOutboxCommand extends Command
{
    protected $signature = 'workcore:outbox:publish {--limit=100 : Maximum messages to attempt}';
    protected $description = 'Publish pending WorkCore outbox messages through the configured transport.';

    public function handle(OutboxPublisherContract $publisher): int
    {
        $result = $publisher->publishPending((int) $this->option('limit'));
        $this->info(sprintf(
            'WorkCore outbox: %d published, %d scheduled for retry, %d failed.',
            $result['published'],
            $result['retried'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
