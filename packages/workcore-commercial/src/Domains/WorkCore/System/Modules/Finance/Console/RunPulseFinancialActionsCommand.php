<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Finance\Console;

use App\Domains\WorkCore\System\Modules\Finance\Automation\Runtime\PulseFinancialActionWorker;
use App\Domains\WorkCore\System\Modules\Finance\Contracts\Clock;
use Illuminate\Console\Command;

final class RunPulseFinancialActionsCommand extends Command
{
    protected $signature = 'workcore:finance:pulse-work {--limit=50}';
    protected $aliases = ['titan-money:pulse-work'];
    protected $description = 'Lease and execute due Titan Money actions scheduled by Titan Pulse.';

    public function handle(PulseFinancialActionWorker $worker, Clock $clock): int
    {
        $report = $worker->run($clock->now(), max(1, (int) $this->option('limit')));
        $this->line(json_encode($report, JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
