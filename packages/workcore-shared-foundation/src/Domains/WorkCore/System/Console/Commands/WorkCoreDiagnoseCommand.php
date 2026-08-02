<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Console\Commands;

use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Capabilities\CapabilityRegistry;
use App\Domains\WorkCore\System\Contracts\PermissionResolverContract;
use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Contracts\TenantResolverContract;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class WorkCoreDiagnoseCommand extends Command
{
    protected $signature = 'workcore:diagnose {--strict : Return failure when optional runtime resources are missing}';

    protected $description = 'Verify WorkCore provider bindings, configuration, routes, migrations and database readiness.';

    public function handle(): int
    {
        $checks = [
            'enabled' => fn (): bool => (bool) config('workcore.enabled', false),
            'tenant_context_binding' => fn (): bool => app()->bound(TenantContextContract::class),
            'tenant_resolver_binding' => fn (): bool => app()->bound(TenantResolverContract::class),
            'permission_resolver_binding' => fn (): bool => app()->bound(PermissionResolverContract::class),
            'capability_registry' => fn (): bool => app()->bound(CapabilityRegistry::class),
            'action_registry' => fn (): bool => app()->bound(BusinessActionRegistry::class),
            'read_model_registry' => fn (): bool => app()->bound(ReadModelRegistry::class),
            'company_switch_route' => fn (): bool => ! config('workcore.tenancy.routes_enabled', true)
                || Route::has('workcore.company.switch'),
            'database_connection' => function (): bool {
                DB::connection()->getPdo();
                return true;
            },
            'core_schema' => fn (): bool => Schema::hasTable('tz_companies')
                && Schema::hasTable('tz_company_memberships'),
            'crm_schema' => fn (): bool => ! config('workcore.modules.crm.enabled', true)
                || (Schema::hasTable('tz_customers') && Schema::hasTable('tz_leads')),
        ];

        $failed = [];
        foreach ($checks as $name => $check) {
            try {
                $passed = (bool) $check();
            } catch (Throwable $exception) {
                $passed = false;
                $failed[$name] = $exception->getMessage();
            }

            if (! $passed && ! isset($failed[$name])) {
                $failed[$name] = 'check returned false';
            }

            $this->line(sprintf('%s %s', $passed ? '<info>PASS</info>' : '<error>FAIL</error>', $name));
        }

        if ($failed === []) {
            $this->info('WorkCore diagnostics passed.');
            return self::SUCCESS;
        }

        $this->newLine();
        foreach ($failed as $name => $message) {
            $this->warn($name . ': ' . $message);
        }

        if (! $this->option('strict')) {
            $this->warn('Diagnostics found incomplete runtime resources. Re-run with --strict in CI.');
            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
