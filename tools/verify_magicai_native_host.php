<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Registry\WorkModuleRegistry;
use Illuminate\Contracts\Console\Kernel;

$options = getopt('', ['host:', 'profiles:', 'profile:']);
$host = realpath((string) ($options['host'] ?? ''));
$profilesPath = realpath((string) ($options['profiles'] ?? ''));
$profileName = (string) ($options['profile'] ?? '');
if ($host === false || $profilesPath === false || $profileName === '') {
    fwrite(STDERR, "Usage: verify_magicai_native_host.php --host=... --profiles=... --profile=...\n");
    exit(2);
}

$profiles = json_decode((string) file_get_contents($profilesPath), true, flags: JSON_THROW_ON_ERROR);
$profile = $profiles[$profileName] ?? null;
if (! is_array($profile)) {
    fwrite(STDERR, "Unknown fixture profile [{$profileName}].\n");
    exit(2);
}

require $host . '/vendor/autoload.php';
$app = require $host . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$workcoreEnabled = (bool) ($profile['workcore_enabled'] ?? true);
$parentAbsent = (bool) ($profile['parent_absent'] ?? false);
$expectedLoaded = array_values((array) ($profile['loaded_modules'] ?? []));
sort($expectedLoaded);

$registryBound = $app->bound(WorkModuleRegistry::class);
if ($parentAbsent || ! $workcoreEnabled) {
    $assert(! $registryBound, 'WorkModuleRegistry must remain unbound when parent_absent or workcore_enabled is false.');
} else {
    $assert($registryBound, 'WorkModuleRegistry is not bound by the parent extension.');
}

$actualLoaded = [];
if ($registryBound) {
    /** @var WorkModuleRegistry $registry */
    $registry = $app->make(WorkModuleRegistry::class);
    $actualLoaded = array_keys($registry->loaded());
    sort($actualLoaded);
}
$assert($actualLoaded === $expectedLoaded, sprintf(
    'loaded_modules mismatch: expected [%s], got [%s]',
    implode(', ', $expectedLoaded),
    implode(', ', $actualLoaded),
));

$middleware = $app['router']->getMiddleware();
foreach (['workcore.tenant', 'workcore.capability', 'workcore.api'] as $alias) {
    if ($parentAbsent || ! $workcoreEnabled) {
        $assert(! array_key_exists($alias, $middleware), "Middleware [{$alias}] should be absent.");
    } else {
        $assert(array_key_exists($alias, $middleware), "Middleware [{$alias}] is missing.");
    }
}

$loadedProviders = $app->getLoadedProviders();
foreach ((array) ($profile['folders'] ?? []) as $folder) {
    $provider = "App\\Extensions\\{$folder}\\System\\{$folder}ServiceProvider";
    $assert(($loadedProviders[$provider] ?? false) === true, "Extension provider [{$provider}] was not loaded.");
}

foreach ([
    'App\\Domains\\WorkCore\\Providers\\BusinessNetworkServiceProvider',
    'App\\Domains\\WorkCore\\Providers\\CommercialServiceProvider',
    'App\\Domains\\WorkCore\\Providers\\WorkOperationsServiceProvider',
    'App\\Domains\\WorkCore\\Providers\\PropertyOperationsServiceProvider',
    'App\\Domains\\WorkCore\\Providers\\WorkforceAssuranceServiceProvider',
] as $aggregate) {
    $assert(($loadedProviders[$aggregate] ?? false) !== true, "Archived aggregate provider [{$aggregate}] loaded unexpectedly.");
}

if ($failures !== []) {
    fwrite(STDERR, "MagicAI native host verification FAILED for {$profileName}\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo json_encode([
    'profile' => $profileName,
    'workcore_enabled' => $workcoreEnabled,
    'parent_absent' => $parentAbsent,
    'loaded_modules' => $actualLoaded,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
