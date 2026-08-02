<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Console;

use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use App\Domains\WorkCore\System\Modules\Finance\Integrations\Runtime\TitanMoneyOutboxRelay;
use Illuminate\Console\Command;

final class RelayTitanMoneyOutboxCommand extends Command
{
    protected $signature = 'workcore:finance:outbox-relay {--limit=100}';
    protected $aliases = ['titan-money:outbox-relay'];
    protected $description = 'Relay Titan Money outbox events to Titan Signal and Titan Talk.';

    public function handle(TitanMoneyOutboxRelay $relay, Clock $clock): int
    {
        $report = $relay->run($clock->now(), max(1, (int) $this->option('limit')));
        $this->line(json_encode($report, JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
