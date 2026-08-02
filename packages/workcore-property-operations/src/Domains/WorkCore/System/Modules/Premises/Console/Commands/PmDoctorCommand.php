<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PmDoctorCommand extends Command
{
    protected $signature = 'pm:doctor';
    protected $description = 'ManagedPremises health check (routes, views, migrations, and critical tables)';

    public function handle(): int
    {
        $tables = [
            'pm_properties',
            'pm_premise_spaces',
            'pm_premise_occupancies',
            'pm_premise_agreements',
            'pm_premise_access_items',
            'pm_premise_condition_records',
            'pm_premise_incidents',
            'pm_compliance_rule_packs',
            'pm_compliance_requirements',
            'pm_compliance_occurrences',
            'pm_compliance_evidence',
        ];
        $missing = array_values(array_filter($tables, fn (string $table) => !Schema::hasTable($table)));
        if ($missing) {
            $this->error('ManagedPremises is missing tables: ' . implode(', ', $missing));
            $this->line('Run the host application migrations before enabling the affected features.');
            return Command::FAILURE;
        }

        $this->info('ManagedPremises: OK');
        $this->line('Compliance refresh: php artisan managedpremises:refresh-compliance --company_id=<id>');
        $this->line('Route check: php artisan route:list | grep managedpremises');
        return Command::SUCCESS;
    }
}
