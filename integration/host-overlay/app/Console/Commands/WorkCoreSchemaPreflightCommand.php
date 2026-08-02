<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class WorkCoreSchemaPreflightCommand extends Command
{
    protected $signature = 'workcore:schema-preflight {--strict : Fail when required host or WorkCore schema is missing}';

    protected $description = 'Inspect WorkCore host schema and WorkCore migration readiness without modifying data.';

    public function handle(): int
    {
        $hostTables = [
            'users', 'conversations', 'participants', 'messages', 'message_reads',
            'settings', 'jobs', 'job_batches', 'failed_jobs', 'personal_access_tokens',
        ];

        $workCoreTables = [
            'tz_companies', 'tz_company_memberships', 'tz_customers', 'tz_leads',
            'tz_domain_events', 'tz_outbox_messages',
        ];

        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            $this->error('Database connection failed: '.$exception->getMessage());
            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $missingHost = array_values(array_filter($hostTables, static fn (string $table): bool => ! Schema::hasTable($table)));
        $missingWorkCore = array_values(array_filter($workCoreTables, static fn (string $table): bool => ! Schema::hasTable($table)));

        $this->table(['Layer', 'Expected', 'Present', 'Missing'], [
            ['WorkCore host', count($hostTables), count($hostTables) - count($missingHost), implode(', ', $missingHost) ?: 'none'],
            ['WorkCore core', count($workCoreTables), count($workCoreTables) - count($missingWorkCore), implode(', ', $missingWorkCore) ?: 'none'],
        ]);

        if ($missingHost !== []) {
            $this->error('Required WorkCore host tables are missing. Do not run WorkCore activation until the host schema is restored.');
        }

        if ($missingWorkCore !== []) {
            $this->warn('WorkCore migrations remain pending: '.implode(', ', $missingWorkCore));
        } else {
            $this->info('WorkCore core schema is present.');
        }

        if ($this->option('strict') && ($missingHost !== [] || $missingWorkCore !== [])) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
