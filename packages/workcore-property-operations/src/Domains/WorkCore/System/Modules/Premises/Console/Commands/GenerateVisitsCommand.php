<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Console\Commands;

use Illuminate\Console\Command;
use App\Domains\WorkCore\System\Modules\Premises\Services\VisitGenerationService;
use DateTimeImmutable;

class GenerateVisitsCommand extends Command
{
    protected $signature = 'pm:generate-visits {--company_id=} {--days=30}';
    protected $description = 'Generate upcoming Property visits from active service plans (tenant-safe).';

    public function handle(VisitGenerationService $service): int
    {
        $companyId = (int)($this->option('company_id') ?? 0);
        $days = max(1, (int)($this->option('days') ?? 30));

        if ($companyId <= 0) {
            $this->error('company_id is required.');
            return self::FAILURE;
        }

        $from = new DateTimeImmutable('now');
        $until = $from->modify('+' . $days . ' days');

        $count = $service->generateForCompany($companyId, $from, $until, 60);

        $this->info('Generated ' . $count . ' visits.');
        return self::SUCCESS;
    }
}
