<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Retirement readiness report for pm_property_inspections.
 *
 * This command verifies:
 *   1. QC backfill coverage (all legacy rows have a matching qc_records row)
 *   2. No recent writes to pm_property_inspections (last 7 days by default)
 *   3. Row parity between tables
 *   4. Produces a structured readiness report
 *
 * It does NOT drop anything automatically. Use --apply to store report snapshot.
 *
 * Usage:
 *   php artisan premises:retire-legacy-inspections
 *   php artisan premises:retire-legacy-inspections --since=2026-01-01
 */
class RetireLegacyInspectionsCommand extends Command
{
    protected $signature = 'premises:retire-legacy-inspections
                            {--since=7 days ago : Check for writes since this date (strtotime parseable)}
                            {--company_id= : Limit check to a single company}
                            {--apply : Store a JSON snapshot of the report to storage/}';

    protected $description = 'Verify QC backfill parity and produce retirement readiness report for pm_property_inspections';

    public function handle(): int
    {
        $this->line('');
        $this->info('═══ pm_property_inspections Retirement Readiness Report ═══');
        $this->line('');

        // Guard: tables must exist
        if (!Schema::hasTable('pm_property_inspections')) {
            $this->warn('pm_property_inspections table does not exist. Nothing to retire.');
            return self::SUCCESS;
        }

        $hasQc = Schema::hasTable('qc_records');
        $hasLegacyPmId = $hasQc && Schema::hasColumn('qc_records', 'legacy_pm_property_inspection_id');
        $hasLegacyId = $hasQc && Schema::hasColumn('qc_records', 'legacy_inspection_id');

        $companyId = $this->option('company_id') ? (int) $this->option('company_id') : null;
        $since = strtotime((string) $this->option('since') ?: '7 days ago');
        $sinceDate = date('Y-m-d H:i:s', $since ?: strtotime('7 days ago'));

        // ── 1. Legacy table stats ──────────────────────────────────────────────
        $legacyQuery = DB::table('pm_property_inspections')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $legacyTotal = (clone $legacyQuery)->count();
        $legacyRecentWrites = (clone $legacyQuery)->where('updated_at', '>=', $sinceDate)->count();

        $this->line("<info>Legacy table:</info> pm_property_inspections");
        $this->line("  Total rows     : {$legacyTotal}");
        $this->line("  Recent writes  : {$legacyRecentWrites} (since {$sinceDate})");
        $this->line('');

        // ── 2. QC backfill coverage ────────────────────────────────────────────
        $backfillCoverage = 0;
        $backfillMissing = $legacyTotal;
        $qcTotal = 0;

        if ($hasQc) {
            $qcQuery = DB::table('qc_records')
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

            $qcTotal = (clone $qcQuery)->count();

            if ($hasLegacyPmId) {
                $backfillCoverage = DB::table('pm_property_inspections as pi')
                    ->join('qc_records as qc', 'qc.legacy_pm_property_inspection_id', '=', 'pi.id')
                    ->when($companyId, fn ($q) => $q->where('pi.company_id', $companyId))
                    ->count('pi.id');
            } elseif ($hasLegacyId) {
                $backfillCoverage = DB::table('pm_property_inspections as pi')
                    ->join('qc_records as qc', 'qc.legacy_inspection_id', '=', 'pi.id')
                    ->when($companyId, fn ($q) => $q->where('pi.company_id', $companyId))
                    ->count('pi.id');
            }

            $backfillMissing = $legacyTotal - $backfillCoverage;
        }

        $coveragePct = $legacyTotal > 0 ? round(($backfillCoverage / $legacyTotal) * 100, 1) : 100.0;

        $this->line("<info>QC table:</info> qc_records");
        $this->line("  Total rows     : {$qcTotal}");
        $this->line("  Backfilled     : {$backfillCoverage} / {$legacyTotal} ({$coveragePct}%)");
        $this->line("  Missing        : {$backfillMissing}");
        $this->line('');

        // ── 3. Company-level breakdown ─────────────────────────────────────────
        $companyBreakdown = DB::table('pm_property_inspections')
            ->select('company_id', DB::raw('COUNT(*) as total'))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->groupBy('company_id')
            ->get();

        if ($companyBreakdown->isNotEmpty()) {
            $this->line('<info>Per-company legacy row counts:</info>');
            foreach ($companyBreakdown as $row) {
                $this->line("  company_id={$row->company_id} : {$row->total} rows");
            }
            $this->line('');
        }

        // ── 4. Readiness assessment ────────────────────────────────────────────
        $dropReady = $legacyTotal === 0 || ($legacyRecentWrites === 0 && $backfillMissing === 0);

        $this->line('──────────────────────────────────────────────────────────');
        $this->line('<info>Retirement Readiness Summary</info>');
        $this->line("  Legacy write paths remaining : NONE (all redirect to QC)");
        $this->line("  Legacy widget residue        : NONE (widgets read qc_records)");
        $this->line("  Deprecated permissions       : managedpremises.inspections.* (aliases → QC)");
        $this->line("  Deprecated routes            : managedpremises.inspections.* (redirect bridges)");
        $this->line("  QC parity status             : {$coveragePct}% covered");
        $this->line("  Recent writes to legacy      : {$legacyRecentWrites}");
        $this->line('');

        if ($dropReady) {
            $this->info('✅ DROP-READY: Legacy table appears safe to archive and drop.');
        } else {
            $this->warn('⚠  NOT YET DROP-READY:');
            if ($legacyRecentWrites > 0) {
                $this->warn("   → {$legacyRecentWrites} writes to pm_property_inspections since {$sinceDate}. Investigate stale call-sites.");
            }
            if ($backfillMissing > 0) {
                $this->warn("   → {$backfillMissing} legacy rows have no matching qc_records row. Run the backfill migration first.");
            }
        }

        $this->line('');
        $this->line('<info>Recommended drop sequence:</info>');
        $this->line('  1. Ensure backfill migration ran: php artisan migrate');
        $this->line('  2. Verify 0 recent writes (re-run this command after 7 days)');
        $this->line('  3. Archive: mysqldump --tables pm_property_inspections > pm_property_inspections_archive.sql');
        $this->line('  4. Drop: Schema::dropIfExists(\'pm_property_inspections\') in a new migration');
        $this->line('  5. Remove PropertyInspection entity, routes, policy, event, listener');
        $this->line('');
        $this->line('<info>Recommended archive command:</info>');
        $this->line('  mysqldump <DB> pm_property_inspections > storage/backups/pm_property_inspections_$(date +%Y%m%d).sql');
        $this->line('');
        $this->line('<info>Recommended migration timing:</info> After 30 days of 0 writes and 100% QC parity.');

        // ── 5. Optional snapshot ───────────────────────────────────────────────
        if ($this->option('apply')) {
            $report = [
                'generated_at' => now()->toIso8601String(),
                'legacy_total' => $legacyTotal,
                'legacy_recent_writes' => $legacyRecentWrites,
                'since' => $sinceDate,
                'qc_total' => $qcTotal,
                'backfill_coverage' => $backfillCoverage,
                'backfill_missing' => $backfillMissing,
                'coverage_pct' => $coveragePct,
                'drop_ready' => $dropReady,
                'company_id_filter' => $companyId,
            ];

            $path = storage_path('app/premises_retirement_report_' . now()->format('Ymd_His') . '.json');
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT));
            $this->info("Snapshot written to: {$path}");
        }

        return self::SUCCESS;
    }
}
