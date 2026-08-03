<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Contracts\TenantContextContract;
use App\Domains\WorkCore\System\Entitlements\CompanyEntitlementRefreshService;
use App\Extensions\WorkCore\System\Navigation\MagicAIMenuSynchronizer;
use App\Extensions\WorkCore\System\Navigation\WorkCoreWorkspaceManifest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

$options = getopt('', ['host:']);
$host = realpath((string) ($options['host'] ?? ''));
if ($host === false) {
    fwrite(STDERR, "Usage: verify_workcore_workspace_navigation.php --host=...\n");
    exit(2);
}

require $host . '/vendor/autoload.php';
$app = require $host . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new \RuntimeException($message);
    }
};

try {
    foreach ([
        MagicAIMenuSynchronizer::class,
        WorkCoreWorkspaceManifest::class,
        TenantContextContract::class,
        CompanyEntitlementRefreshService::class,
    ] as $binding) {
        $assert($app->bound($binding), "Required workspace binding [{$binding}] is missing.");
    }

    Schema::dropIfExists('menus');
    Schema::create('menus', static function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('parent_id')->nullable()->index();
        $table->string('key')->unique();
        $table->string('route')->nullable();
        $table->string('route_slug')->nullable();
        $table->string('label');
        $table->string('icon')->nullable();
        $table->unsignedInteger('order')->default(0);
        $table->boolean('is_active')->default(true)->index();
        $table->text('params')->nullable();
        $table->string('type')->default('item');
        $table->string('extension')->nullable()->index();
        $table->boolean('custom_menu')->default(false);
        $table->timestamps();
    });

    $now = now();
    DB::table('menus')->insert([
        [
            'key' => 'workcore_commercial_dropdown',
            'route' => 'stale.route',
            'route_slug' => 'stale-path',
            'label' => 'Administrator Commercial Label',
            'icon' => 'stale-icon',
            'order' => 999,
            'is_active' => false,
            'params' => '[]',
            'type' => 'item',
            'extension' => 'workcore',
            'custom_menu' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'key' => 'workcore_custom_retired',
            'route' => 'dashboard.user.workcore.retired.index',
            'route_slug' => 'retired',
            'label' => 'Retired WorkCore menu',
            'icon' => 'tabler-x',
            'order' => 1000,
            'is_active' => true,
            'params' => '[]',
            'type' => 'item',
            'extension' => 'workcore',
            'custom_menu' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    /** @var MagicAIMenuSynchronizer $menus */
    $menus = $app->make(MagicAIMenuSynchronizer::class);
    $first_sync = $menus->sync();
    $second_sync = $menus->sync();

    $commercialMenu = DB::table('menus')->where('key', 'workcore_commercial_dropdown')->first();
    $retiredMenu = DB::table('menus')->where('key', 'workcore_custom_retired')->first();
    $assert($commercialMenu !== null, 'The Commercial root menu was not synchronized.');
    $assert($commercialMenu->route === 'dashboard.user.workcore.commercial.index', 'Vendor route fields were not repaired.');
    $assert((int) $commercialMenu->order === 999, 'Administrator menu order was not preserved.');
    $assert((bool) $commercialMenu->is_active === false, 'Administrator-disabled menu state was not preserved.');
    $assert($retiredMenu !== null && (bool) $retiredMenu->is_active === false, 'Retired WorkCore menu was not disabled.');
    $assert($second_sync['created'] === 0 && $second_sync['updated'] === 0, 'The second menu synchronization was not idempotent.');
    $assert((int) DB::table('menus')->where('extension', 'workcore')->count() === 43, 'Unexpected WorkCore menu count after synchronization.');

    foreach ([
        'dashboard.user.workcore.crm.index',
        'dashboard.user.workcore.operations.index',
        'dashboard.user.workcore.workforce.index',
        'dashboard.user.workcore.resources.index',
        'dashboard.user.workcore.commercial.index',
        'api.workcore.workspaces.index',
        'api.workcore.workspaces.show',
    ] as $routeName) {
        $assert(Route::has($routeName), "Required workspace route [{$routeName}] is missing.");
    }

    $userId = (int) DB::table('users')->where('email', 'workcore-entitlement-fixture@example.test')->value('id');
    $companyId = (int) DB::table('tz_companies')->where('slug', 'workcore-entitlement-fixture')->value('id');
    $subscription = DB::table('subscriptions')->where('user_id', $userId)->first();
    $assert($userId > 0 && $companyId > 0 && $subscription !== null, 'The entitlement fixture records are unavailable.');

    DB::table('subscriptions')->where('id', $subscription->id)->update([
        'status' => 'active',
        'ends_at' => $now->copy()->addMonth(),
        'updated_at' => $now->copy()->addMinutes(3),
    ]);
    DB::table('plans')->where('id', $subscription->plan_id)->update([
        'ext_workcore_core' => false,
        'ext_workcore_commercial' => true,
        'updated_at' => $now->copy()->addMinutes(3),
    ]);

    /** @var CompanyEntitlementRefreshService $refresh */
    $refresh = $app->make(CompanyEntitlementRefreshService::class);
    $refresh->refresh($companyId);

    /** @var TenantContextContract $tenant */
    $tenant = $app->make(TenantContextContract::class);
    $tenant->set($companyId, $userId);
    /** @var WorkCoreWorkspaceManifest $manifest */
    $manifest = $app->make(WorkCoreWorkspaceManifest::class);
    $before = $manifest->forActiveCompany();
    $beforeKeys = array_column($before['workspaces'], 'key');
    $resources_with_commercial_only = in_array('resources', $beforeKeys, true);
    $commercial_before_expiry = in_array('commercial', $beforeKeys, true);
    $assert($resources_with_commercial_only, 'Resources did not expose commercial-owned Inventory and Supply sections.');
    $assert($commercial_before_expiry, 'Commercial workspace was absent for an active entitled subscription.');
    $assert(! in_array('crm', $beforeKeys, true), 'CRM remained visible without the core plan feature.');

    $resources = $manifest->findForCompany($companyId, 'resources');
    $resourceSections = array_column($resources['sections'] ?? [], 'key');
    $assert(in_array('inventory', $resourceSections, true), 'Inventory was absent from a Commercial-only Resources workspace.');
    $assert(in_array('supply', $resourceSections, true), 'Supply was absent from a Commercial-only Resources workspace.');

    DB::table('subscriptions')->where('id', $subscription->id)->update([
        'ends_at' => $now->copy()->subSecond(),
        'updated_at' => $now->copy()->addMinutes(4),
    ]);
    $refresh->refresh($companyId);
    $after = $manifest->forActiveCompany();
    $afterKeys = array_column($after['workspaces'], 'key');
    $commercial_after_expiry = in_array('commercial', $afterKeys, true);
    $assert(! $commercial_after_expiry, 'Commercial workspace remained visible after subscription expiry.');
    $assert(! in_array('resources', $afterKeys, true), 'Resources remained visible after subscription expiry.');

    echo json_encode([
        'first_sync' => $first_sync,
        'second_sync' => $second_sync,
        'menu_count' => DB::table('menus')->where('extension', 'workcore')->count(),
        'resources_with_commercial_only' => $resources_with_commercial_only,
        'commercial_before_expiry' => $commercial_before_expiry,
        'commercial_after_expiry' => $commercial_after_expiry,
        'entitlement_revision_before' => $before['entitlement_revision'],
        'entitlement_revision_after' => $after['entitlement_revision'],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (\Throwable $exception) {
    fwrite(STDERR, "WorkCore workspace navigation verification FAILED\n");
    fwrite(STDERR, ' - ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
