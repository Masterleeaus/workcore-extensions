<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$passes = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$passes): void {
    if ($condition) {
        $passes++;
        return;
    }
    $failures[] = $message;
};

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$requiredFiles = [
    'app/Domains/WorkCore/System/Modules/Documents/Providers/WorkDocumentsServiceProvider.php',
    'app/Domains/WorkCore/System/Modules/Documents/Repositories/EloquentDocumentRepository.php',
    'app/Domains/WorkCore/System/Modules/Assurance/Providers/WorkAssuranceServiceProvider.php',
    'app/Domains/WorkCore/System/Modules/Assurance/Repositories/EloquentAssuranceRepository.php',
    'app/Domains/WorkCore/System/Modules/TrustAccounting/Providers/WorkTrustAccountingServiceProvider.php',
    'app/Domains/WorkCore/System/Modules/TrustAccounting/Repositories/EloquentTrustAccountingRepository.php',
    'app/Domains/WorkCore/System/ReadModels/ReadModelExecutor.php',
    'app/Domains/WorkCore/System/External/ExternalCandidateLookup.php',
    'app/Domains/WorkCore/Routes/api.php',
    'app/Domains/WorkCore/Database/Migrations/2026_07_26_020000_create_tz_documents_and_evidence_tables.php',
    'app/Domains/WorkCore/Database/Migrations/2026_07_26_020010_create_tz_assurance_extension_tables.php',
    'app/Domains/WorkCore/Database/Migrations/2026_07_26_040000_create_tz_trust_accounting_tables.php',
];
foreach ($requiredFiles as $file) {
    $assert(is_file($root . '/' . $file), "Missing required extracted file: {$file}");
}

$envExample = $read('.env.example');
$assert(str_contains($envExample, "APP_KEY=
"), 'Example environment must generate a unique application key during installation.');
$assert(! str_contains($envExample, 'APP_KEY=base64:'), 'Example environment still contains a shared application key.');
$assert(str_contains($envExample, 'WORKCORE_TRUST_ACCOUNTING_ENABLED=false'), 'Trust Accounting environment flag must default to disabled.');

$routes = $read('app/Domains/WorkCore/Routes/api.php');
$assert(str_contains($routes, 'auth:sanctum'), 'Standalone WorkCore API routes are not Sanctum compatible.');
$assert(! str_contains($routes, 'auth:api'), 'Standalone WorkCore API routes still contain MagicAI Passport middleware.');

$config = $read('app/Domains/WorkCore/Config/workcore.php');
foreach (['documents', 'assurance', 'trust_accounting'] as $module) {
    $assert(str_contains($config, "'{$module}' => ["), "WorkCore config does not define module/config key [{$module}].");
}
$assert(str_contains($config, 'WorkDocumentsServiceProvider::class'), 'Documents provider is not configured.');
$assert(str_contains($config, 'WorkAssuranceServiceProvider::class'), 'Assurance provider is not configured.');
$assert(str_contains($config, 'WorkTrustAccountingServiceProvider::class'), 'Trust Accounting provider is not configured.');
$assert(str_contains($config, "env('WORKCORE_TRUST_ACCOUNTING_ENABLED', false)"), 'Trust Accounting must default to disabled.');
$assert(!str_contains($config, "auth:api"), 'Standalone WorkCore config must not import MagicAI Passport middleware defaults.');

$provider = $read('app/Domains/WorkCore/WorkCoreServiceProvider.php');
$assert(str_contains($provider, "config('workcore.api.routes_enabled', false)"), 'WorkCore API route loading is not feature-flagged.');
$assert(str_contains($provider, 'registerRateLimiters'), 'WorkCore rate limiters are not registered.');
$assert(str_contains($provider, 'ReadModelExecutor::class'), 'ReadModelExecutor is not registered in the container.');
$assert(str_contains($provider, "if (! \$registry->isLoaded(\$key))"), 'Enabled modules outside aggregate providers are not automatically loaded.');

$registry = $read('app/Domains/WorkCore/System/Registry/WorkModuleRegistry.php');
$assert(str_contains($registry, 'public function has(string $key): bool'), 'Module registry cannot safely test disabled modules.');
$assert(str_contains($registry, 'if (! $this->has($key))'), 'Aggregate module loading does not skip disabled modules.');

$assuranceMigration = $read('app/Domains/WorkCore/Database/Migrations/2026_07_26_020010_create_tz_assurance_extension_tables.php');
$assert(!str_contains($assuranceMigration, "Schema::create('tz_corrective_actions'"), 'Assurance migration recreates canonical corrective actions.');
$assert(!str_contains($assuranceMigration, "Schema::create('tz_incidents'"), 'Assurance migration creates a parallel incident authority.');
$assert(str_contains($assuranceMigration, "Schema::table('tz_corrective_actions'"), 'Assurance migration does not extend canonical corrective actions with a finding link.');

$assuranceRepository = $read('app/Domains/WorkCore/System/Modules/Assurance/Repositories/EloquentAssuranceRepository.php');
$assert(str_contains($assuranceRepository, "tz_safety_incidents"), 'Assurance incidents are not adapted to canonical safety incidents.');
$assert(!str_contains($assuranceRepository, "table('tz_incidents')"), 'Assurance repository still writes to a parallel incident table.');
$assert(!str_contains($assuranceRepository, "'work_order_id' => \$workOrder?->id, 'action' =>"), 'Assurance corrective actions still target the donor-only action column.');
$assert(!str_contains($assuranceRepository, "'status' => 'planned', 'due_at' =>"), 'Assurance corrective actions still target the donor-only due_at column.');

$documentsResolver = $read('app/Domains/WorkCore/System/Modules/Documents/Services/OperationalSubjectResolver.php');
$assert(!str_contains($documentsResolver, "'incident' => 'tz_incidents'"), 'Documents subject resolution still points at the parallel incident table.');
$assert(str_contains($documentsResolver, "'incident' => 'tz_safety_incidents'"), 'Documents subject resolution does not support canonical safety incidents.');

$trustMigration = $read('app/Domains/WorkCore/Database/Migrations/2026_07_26_040000_create_tz_trust_accounting_tables.php');
$assert(!str_contains($trustMigration, "constrained('tz_premises_agreements')"), 'Trust Accounting migration depends on donor accommodation tables.');
$assert(str_contains($trustMigration, "agreement_public_id"), 'Trust matter schema lacks a host-neutral agreement reference.');

$trustRepository = $read('app/Domains/WorkCore/System/Modules/TrustAccounting/Repositories/EloquentTrustAccountingRepository.php');
$assert(!str_contains($trustRepository, "optionalId('tz_premises_agreements'"), 'Trust Accounting repository still depends on donor accommodation tables.');
$assert(str_contains($trustRepository, "'agreement_public_id' =>"), 'Trust Accounting repository does not persist host-neutral agreement references.');

$migrationFiles = array_merge(
    glob($root . '/database/migrations/*.php') ?: [],
    glob($root . '/app/Domains/WorkCore/Database/Migrations/*.php') ?: [],
);
$tableOwners = [];
foreach ($migrationFiles as $migration) {
    $contents = (string) file_get_contents($migration);
    if (preg_match_all("/Schema::create\\(['\"]([^'\"]+)['\"]/", $contents, $matches)) {
        foreach ($matches[1] as $table) {
            $tableOwners[$table][] = basename($migration);
        }
    }
}
foreach (['tz_corrective_actions', 'tz_safety_incidents'] as $table) {
    $owners = $tableOwners[$table] ?? [];
    $assert(count($owners) === 1, "Canonical table {$table} must have exactly one Schema::create owner; found " . count($owners) . '.');
}
foreach ($tableOwners as $table => $owners) {
    $assert(count($owners) === 1, "Table {$table} has multiple Schema::create owners: " . implode(', ', $owners));
}
foreach (array_keys($tableOwners) as $table) {
    $assert(!str_starts_with($table, 'tz_finance_'), "Parallel donor finance table was introduced: {$table}");
    $assert(!str_starts_with($table, 'tz_payment_'), "Parallel donor payment table was introduced: {$table}");
}

if ($failures !== []) {
    fwrite(STDERR, "MagicAI → WorkCore extraction verification FAILED\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    fwrite(STDERR, sprintf("%d checks passed; %d failed.\n", $passes, count($failures)));
    exit(1);
}

echo sprintf("MagicAI → WorkCore extraction verification passed: %d checks.\n", $passes);
