<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Console\Commands;

use Illuminate\Console\Command;
use App\Domains\WorkCore\System\Modules\Premises\Entities\Property;
use App\Domains\WorkCore\System\Modules\Premises\Services\PremiseComplianceService;

class RefreshComplianceCommand extends Command
{
    protected $signature = 'managedpremises:refresh-compliance {--company_id=} {--premise_id=}';
    protected $description = 'Refresh compliance occurrence due and overdue states for a company or premise.';

    public function handle(PremiseComplianceService $service): int
    {
        $companyId = (int) ($this->option('company_id') ?? 0);
        if ($companyId <= 0) {
            $this->error('company_id is required.');
            return self::FAILURE;
        }

        $query = Property::withoutGlobalScopes()->where('company_id', $companyId);
        if ($premiseId = (int) ($this->option('premise_id') ?? 0)) {
            $query->whereKey($premiseId);
        }

        $premises = 0;
        $changed = 0;
        $query->orderBy('id')->chunkById(100, function ($records) use ($service, &$premises, &$changed): void {
            foreach ($records as $premise) {
                $result = $service->refreshDueStatuses($premise);
                $premises++;
                $changed += $result['changed'];
            }
        });

        $this->info("Refreshed {$premises} premise(s); {$changed} occurrence(s) changed state.");
        return self::SUCCESS;
    }
}
