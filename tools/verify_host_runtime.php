<?php

declare(strict_types=1);

use App\Domains\WorkCore\Contracts\OperationContextContract;
use App\Domains\WorkCore\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Actions\BusinessActionDispatcher;
use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Capabilities\CapabilityRegistry;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use App\Domains\WorkCore\System\Registry\WorkModuleRegistry;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Routing\Router;

/** @return never */
function failVerification(string $message): void
{
    fwrite(STDERR, "Runtime verification failed: {$message}\n");
    exit(1);
}

/** @return array<string,mixed> */
function readJsonObject(string $path): array
{
    if (! is_file($path)) {
        failVerification("JSON file not found: {$path}");
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (! is_array($decoded)) {
        failVerification("Invalid JSON object: {$path}");
    }

    return $decoded;
}

/** @return list<string> */
function expectedModulesForProfile(string $repositoryRoot, string $profilesPath, string $profileName): array
{
    $profiles = readJsonObject($profilesPath);
    $profile = $profiles[$profileName] ?? null;
    if (! is_array($profile) || ! is_array($profile['packages'] ?? null)) {
        failVerification("Unknown or invalid install profile: {$profileName}");
    }

    $modules = [];
    foreach ($profile['packages'] as $packageName) {
        if (! is_string($packageName) || $packageName === 'workcore/shared-foundation') {
            continue;
        }

        $slug = str_replace('workcore/', '', $packageName);
        $extensionPath = $repositoryRoot . '/packages/workcore-' . $slug . '/extension.json';
        $extension = readJsonObject($extensionPath);
        foreach ((array) ($extension['runtime_keys'] ?? []) as $module) {
            if (is_string($module) && $module !== '') {
                $modules[$module] = true;
            }
        }
    }

    $modules = array_keys($modules);
    sort($modules);

    return array_values($modules);
}

/** @param list<string> $expected @param list<string> $actual */
function assertSameList(string $label, array $expected, array $actual): void
{
    sort($expected);
    sort($actual);
    if ($expected !== $actual) {
        failVerification(
            $label . ' mismatch. Expected [' . implode(', ', $expected) . '] but received [' . implode(', ', $actual) . '].',
        );
    }
}

$options = getopt('', ['repo:', 'host:', 'profiles:', 'profile:', 'enabled:']);
$repositoryRoot = realpath((string) ($options['repo'] ?? '.'));
$hostRoot = realpath((string) ($options['host'] ?? 'integration/host-overlay'));
$profilesPath = realpath((string) ($options['profiles'] ?? 'compatibility/install-profiles.json'));
$profileName = (string) ($options['profile'] ?? '');
$enabled = filter_var($options['enabled'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if ($repositoryRoot === false || $hostRoot === false || $profilesPath === false || $profileName === '' || $enabled === null) {
    failVerification('Required arguments: --repo, --host, --profiles, --profile and --enabled=true|false.');
}

$enabledValue = $enabled ? 'true' : 'false';
putenv("WORKCORE_ENABLED={$enabledValue}");
$_ENV['WORKCORE_ENABLED'] = $enabledValue;
$_SERVER['WORKCORE_ENABLED'] = $enabledValue;

$autoloadPath = $hostRoot . '/vendor/autoload.php';
$bootstrapPath = $hostRoot . '/bootstrap/app.php';
if (! is_file($autoloadPath) || ! is_file($bootstrapPath)) {
    failVerification('The host must have installed Composer dependencies before runtime verification.');
}

require $autoloadPath;
$app = require $bootstrapPath;
$kernel = $app->make(ConsoleKernel::class);
$kernel->bootstrap();
$router = $app->make(Router::class);

if (! $enabled) {
    foreach ([
        WorkModuleRegistry::class,
        BusinessActionRegistry::class,
        ReadModelRegistry::class,
        CapabilityRegistry::class,
        BusinessActionDispatcher::class,
        TenantContextContract::class,
        OperationContextContract::class,
    ] as $binding) {
        if ($app->bound($binding)) {
            failVerification("WorkCore-disabled host unexpectedly bound {$binding}.");
        }
    }

    if ($router->getRoutes()->getByName('api.workcore.actions.index') !== null) {
        failVerification('WorkCore-disabled host unexpectedly registered API routes.');
    }

    echo json_encode([
        'enabled' => false,
        'profile' => $profileName,
        'bindings' => 0,
        'routes' => 0,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

foreach ([
    WorkModuleRegistry::class,
    BusinessActionRegistry::class,
    ReadModelRegistry::class,
    CapabilityRegistry::class,
    BusinessActionDispatcher::class,
    TenantContextContract::class,
    OperationContextContract::class,
] as $binding) {
    if (! $app->bound($binding)) {
        failVerification("WorkCore-enabled host did not bind {$binding}.");
    }
}

$expectedModules = array_values(array_filter(
    expectedModulesForProfile($repositoryRoot, $profilesPath, $profileName),
    static fn (string $module): bool => (bool) config("workcore.modules.{$module}.enabled", false),
));
$moduleRegistry = $app->make(WorkModuleRegistry::class);
assertSameList('Defined modules', $expectedModules, array_keys($moduleRegistry->all()));
assertSameList('Loaded modules', $expectedModules, array_keys($moduleRegistry->loaded()));

$capabilityRegistry = $app->make(CapabilityRegistry::class);
$expectedCapabilityCount = count((array) config('workcore.capabilities', []));
$actualCapabilityCount = count($capabilityRegistry->all());
if ($actualCapabilityCount !== $expectedCapabilityCount) {
    failVerification("Capability registry count {$actualCapabilityCount} does not match config count {$expectedCapabilityCount}.");
}

$actionCount = count($app->make(BusinessActionRegistry::class)->all());
$readModelCount = count($app->make(ReadModelRegistry::class)->all());
if ($expectedModules !== [] && ($actionCount === 0 || $readModelCount === 0)) {
    failVerification('Installed domain packages did not register both business actions and read models.');
}

$middleware = $router->getMiddleware();
foreach (['workcore.tenant', 'workcore.capability', 'workcore.api'] as $alias) {
    if (! isset($middleware[$alias])) {
        failVerification("Missing WorkCore middleware alias {$alias}.");
    }
}

if ((bool) config('workcore.api.routes_enabled', false)) {
    if ($router->getRoutes()->getByName('api.workcore.actions.index') === null) {
        failVerification('Enabled WorkCore API routes were not registered.');
    }
} elseif ($router->getRoutes()->getByName('api.workcore.actions.index') !== null) {
    failVerification('Disabled WorkCore API routes were unexpectedly registered.');
}

echo json_encode([
    'enabled' => true,
    'profile' => $profileName,
    'defined_modules' => array_keys($moduleRegistry->all()),
    'loaded_modules' => array_keys($moduleRegistry->loaded()),
    'capabilities' => $actualCapabilityCount,
    'actions' => $actionCount,
    'read_models' => $readModelCount,
    'api_routes_enabled' => (bool) config('workcore.api.routes_enabled', false),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
