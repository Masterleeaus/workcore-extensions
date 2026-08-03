<?php

declare(strict_types=1);

namespace App\Extensions\WorkCore\System\Console\Commands;

use App\Domains\WorkCore\System\Entitlements\CompanyEntitlementRefreshService;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Throwable;

final class RefreshWorkCoreEntitlementsCommand extends Command
{
    protected $signature = 'workcore:refresh-entitlements
        {company? : WorkCore company ID}
        {--all : Refresh every active WorkCore company}';

    protected $description = 'Project normalized MagicAI plan entitlements into WorkCore company capabilities.';

    public function __construct(
        private CompanyEntitlementRefreshService $refresh,
        private ConnectionInterface $db,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $company = $this->argument('company');
        $all = (bool) $this->option('all');
        if (($company === null && ! $all) || ($company !== null && $all)) {
            $this->error('Provide exactly one company ID or use --all.');

            return self::INVALID;
        }

        $companyIds = $all
            ? $this->db->table('tz_companies')
                ->where('status', 'active')
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($value): int => (int) $value)
                ->all()
            : [(int) $company];

        $failures = 0;
        foreach ($companyIds as $companyId) {
            if ($companyId < 1) {
                $this->error("Invalid WorkCore company ID [{$companyId}].");
                $failures++;
                continue;
            }
            try {
                $revision = $this->refresh->refresh((int) $companyId);
                $this->info("Company {$companyId}: entitlement revision {$revision}.");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Company {$companyId}: {$exception->getMessage()}");
                $failures++;
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
