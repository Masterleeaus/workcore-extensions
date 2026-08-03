<?php

declare(strict_types=1);

use App\Domains\WorkCore\System\Actions\Contracts\EntitlementResolverContract;
use App\Domains\WorkCore\System\Actions\Contracts\EntitlementRevisionResolverContract;
use App\Domains\WorkCore\System\Entitlements\CompanyEntitlementRefreshService;
use App\Domains\WorkCore\System\Entitlements\Contracts\EffectiveSubscriptionResolverContract;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

$options = getopt('', ['host:']);
$host = realpath((string) ($options['host'] ?? ''));
if ($host === false) {
    fwrite(STDERR, "Usage: verify_workcore_entitlement_projection.php --host=...\n");
    exit(2);
}

require $host . '/vendor/autoload.php';
$app = require $host . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

try {
    foreach ([
        CompanyEntitlementRefreshService::class,
        EntitlementResolverContract::class,
        EntitlementRevisionResolverContract::class,
        EffectiveSubscriptionResolverContract::class,
    ] as $binding) {
        $assert($app->bound($binding), "Required entitlement binding [{$binding}] is missing.");
    }

    if (! Schema::hasTable('plans')) {
        Schema::create('plans', static function (Blueprint $table): void {
            $table->id();
            $table->string('frequency')->default('monthly');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('subscriptions')) {
        Schema::create('subscriptions', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('plan_id')->index();
            $table->string('status', 80)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    $migrationPaths = glob(
        $host . '/app/Extensions/WorkCore/database/migrations/*add_workcore_entitlements_to_magicai_plans.php',
    ) ?: [];
    $assert(count($migrationPaths) === 1, 'The native MagicAI plan compatibility migration was not installed exactly once.');
    $migration = require $migrationPaths[0];
    $migration->up();

    $now = now();
    $userId = (int) DB::table('users')->insertGetId([
        'name' => 'WorkCore Entitlement Fixture',
        'email' => 'workcore-entitlement-fixture@example.test',
        'password' => password_hash('fixture-password', PASSWORD_BCRYPT),
        'email_verified_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $companyId = (int) DB::table('tz_companies')->insertGetId([
        'public_id' => '01K1W0RKCOREENTITLEMENT01',
        'name' => 'WorkCore Entitlement Fixture',
        'slug' => 'workcore-entitlement-fixture',
        'status' => 'active',
        'timezone' => 'Australia/Melbourne',
        'currency_code' => 'AUD',
        'country_code' => 'AU',
        'gst_registered' => false,
        'owner_user_id' => $userId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $planId = (int) DB::table('plans')->insertGetId([
        'frequency' => 'monthly',
        'ext_workcore_core' => true,
        'ext_workcore_operations' => false,
        'ext_workcore_workforce' => false,
        'ext_workcore_resources' => false,
        'ext_workcore_commercial' => false,
        'ext_workcore_offline' => false,
        'ext_workcore_ai_actions' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $subscriptionId = (int) DB::table('subscriptions')->insertGetId([
        'user_id' => $userId,
        'plan_id' => $planId,
        'status' => 'active',
        'starts_at' => $now->copy()->subMinute(),
        'ends_at' => $now->copy()->addMonth(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    /** @var CompanyEntitlementRefreshService $refresh */
    $refresh = $app->make(CompanyEntitlementRefreshService::class);
    /** @var EntitlementResolverContract $entitlements */
    $entitlements = $app->make(EntitlementResolverContract::class);
    /** @var EntitlementRevisionResolverContract $revisions */
    $revisions = $app->make(EntitlementRevisionResolverContract::class);
    /** @var EffectiveSubscriptionResolverContract $subscriptions */
    $subscriptions = $app->make(EffectiveSubscriptionResolverContract::class);

    $snapshot = $subscriptions->resolve($companyId);
    $assert($snapshot->status === 'active', 'The MagicAI subscription was not normalized as active.');
    $assert($snapshot->featureEnabled('ext_workcore_core'), 'The core plan feature was not normalized as enabled.');

    $firstRevision = $refresh->refresh($companyId);
    $assert($firstRevision === 1, "Expected first entitlement revision 1, got {$firstRevision}.");
    $assert($entitlements->allows($companyId, 'workcore.crm'), 'Core plan must grant WorkCore CRM.');
    $assert(! $entitlements->allows($companyId, 'workcore.finance'), 'Core-only plan must not grant WorkCore Finance.');

    $stableRevision = $refresh->refresh($companyId);
    $assert($stableRevision === $firstRevision, 'An unchanged subscription must not increment the entitlement revision.');

    DB::table('plans')->where('id', $planId)->update([
        'ext_workcore_commercial' => true,
        'updated_at' => $now->copy()->addSecond(),
    ]);
    $changedRevision = $refresh->refresh($companyId);
    $assert($changedRevision === 2, "Expected changed entitlement revision 2, got {$changedRevision}.");
    $assert($entitlements->allows($companyId, 'workcore.finance'), 'Commercial plan feature must grant WorkCore Finance.');

    DB::table('subscriptions')->where('id', $subscriptionId)->update([
        'ends_at' => $now->copy()->subSecond(),
        'updated_at' => $now->copy()->addSeconds(2),
    ]);
    $expiredRevision = $refresh->refresh($companyId);
    $assert($expiredRevision === 3, "Expected expired entitlement revision 3, got {$expiredRevision}.");
    $assert(! $entitlements->allows($companyId, 'workcore.crm'), 'Expired subscription must deny optional CRM capability.');
    $assert(! $entitlements->allows($companyId, 'workcore.finance'), 'Expired subscription must deny optional Finance capability.');
    $assert($revisions->revision($companyId) === $expiredRevision, 'Revision resolver did not return the persisted revision.');

    echo json_encode([
        'company_id' => $companyId,
        'first_revision' => $firstRevision,
        'stable_revision' => $stableRevision,
        'changed_revision' => $changedRevision,
        'expired_revision' => $expiredRevision,
        'crm_after_expiry' => $entitlements->allows($companyId, 'workcore.crm'),
        'finance_after_expiry' => $entitlements->allows($companyId, 'workcore.finance'),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, "WorkCore entitlement projection verification FAILED\n");
    fwrite(STDERR, ' - ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
