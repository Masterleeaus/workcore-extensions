<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Assets\Providers;

use App\Domains\WorkCore\System\Actions\{ActionDefinition, BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\AssetAccessContract;
use App\Domains\WorkCore\System\Modules\Assets\Console\{ListOverdueAssetCustodyCommand, ProcessAssetDueCommand, ProcessOverdueAssetCustodyCommand};
use App\Domains\WorkCore\System\Contracts\Records\Adapters\AssetAccessAdapter;
use App\Domains\WorkCore\System\Modules\Assets\Actions\{
    AssignAsset,
    ChangeAssetStatus,
    CheckinAsset,
    CheckoutAsset,
    CompleteAssetMaintenance,
    CreateAsset,
    CreateAssetCategory,
    CreateAssetFromSupplyReceipt,
    CreateAssetMaintenancePlan,
    CreateAssetWarranty,
    CreateAssetWarrantyClaim,
    GetAssetAvailability,
    LinkAssetDocument,
    LookupAssetIdentifier,
    OverrideAssetStatus,
    RecordAssetCalibration,
    RecordAssetCondition,
    RecordAssetMaintenance,
    RecordAssetMeterReading,
    RegisterAssetIdentifier,
    ReportAssetDamage,
    SearchAssets,
    SearchOverdueAssetCustody,
    StartAssetMaintenance,
    TransferAsset,
    UpdateAssetWarrantyClaim,
    WriteOffAsset
};
use App\Domains\WorkCore\System\Modules\Assets\Contracts\AssetRepositoryContract;
use App\Domains\WorkCore\System\Modules\Assets\Repositories\EloquentAssetRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition, ReadModelRegistry};
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

final class WorkAssetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AssetRepositoryContract::class, EloquentAssetRepository::class);
        $this->app->bind(AssetAccessContract::class, AssetAccessAdapter::class);

        $actions = $this->app->make(BusinessActionRegistry::class);
        $definitions = [
            ['workcore.asset_category.create', CreateAssetCategory::class, 'low', 'manage'],
            ['workcore.asset.create', CreateAsset::class, 'medium', 'manage'],
            ['workcore.asset.supply_handoff.create', CreateAssetFromSupplyReceipt::class, 'medium', 'manage'],
            ['workcore.asset.assign', AssignAsset::class, 'medium', 'assign'],
            ['workcore.asset.checkout', CheckoutAsset::class, 'medium', 'checkout'],
            ['workcore.asset.checkin', CheckinAsset::class, 'medium', 'checkin'],
            ['workcore.asset.transfer', TransferAsset::class, 'high', 'transfer'],
            ['workcore.asset.change_status', ChangeAssetStatus::class, 'high', 'status'],
            ['workcore.asset.writeoff', WriteOffAsset::class, 'critical', 'writeoff'],
            ['workcore.asset.override_status', OverrideAssetStatus::class, 'critical', 'override'],
            ['workcore.asset.condition.record', RecordAssetCondition::class, 'low', 'condition'],
            ['workcore.asset.damage.report', ReportAssetDamage::class, 'medium', 'damage'],
            ['workcore.asset.maintenance_plan.create', CreateAssetMaintenancePlan::class, 'medium', 'maintenance'],
            ['workcore.asset.record_maintenance', RecordAssetMaintenance::class, 'medium', 'maintenance'],
            ['workcore.asset.maintenance.start', StartAssetMaintenance::class, 'high', 'maintenance'],
            ['workcore.asset.maintenance.complete', CompleteAssetMaintenance::class, 'high', 'maintenance'],
            ['workcore.asset.record_meter_reading', RecordAssetMeterReading::class, 'low', 'readings'],
            ['workcore.asset.calibration.record', RecordAssetCalibration::class, 'high', 'calibration'],
            ['workcore.asset.warranty.create', CreateAssetWarranty::class, 'medium', 'warranty'],
            ['workcore.asset.warranty_claim.create', CreateAssetWarrantyClaim::class, 'medium', 'warranty'],
            ['workcore.asset.warranty_claim.update', UpdateAssetWarrantyClaim::class, 'high', 'warranty'],
            ['workcore.asset.identifier.register', RegisterAssetIdentifier::class, 'low', 'identifiers'],
            ['workcore.asset.document.link', LinkAssetDocument::class, 'low', 'manage'],
        ];
        foreach ($definitions as [$key, $handler, $risk, $permission]) {
            $actions->register(new ActionDefinition(
                $key,
                $handler,
                $risk,
                true,
                'workcore.assets',
                (string) config("workcore.assets.permissions.{$permission}"),
                ['domain' => 'durable_assets', 'canonical_owner' => 'WorkCore Assets'],
            ));
        }

        $reads = $this->app->make(ReadModelRegistry::class);
        $viewPermission = (string) config('workcore.assets.permissions.view');
        $reads->register(new ReadModelDefinition('workcore.asset.search', SearchAssets::class, 'workcore.assets', permission: $viewPermission));
        $reads->register(new ReadModelDefinition('workcore.asset.availability', GetAssetAvailability::class, 'workcore.assets', permission: $viewPermission));
        $reads->register(new ReadModelDefinition('workcore.asset.identifier.lookup', LookupAssetIdentifier::class, 'workcore.assets', permission: $viewPermission));
        $reads->register(new ReadModelDefinition('workcore.asset.custody.overdue', SearchOverdueAssetCustody::class, 'workcore.assets', permission: $viewPermission));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ProcessAssetDueCommand::class, ProcessOverdueAssetCustodyCommand::class, ListOverdueAssetCustodyCommand::class]);
            if ((bool) config('workcore.assets.schedule_due_processing', true)) {
                $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                    $schedule->command('workcore:assets:process-due')
                        ->everyFifteenMinutes()
                        ->withoutOverlapping()
                        ->onOneServer();
                });
            }
            if ((bool) config('workcore.assets.schedule_overdue_custody_processing', true)) {
                $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                    $schedule->command('workcore:assets:process-overdue-custody')
                        ->everyFifteenMinutes()
                        ->withoutOverlapping()
                        ->onOneServer();
                });
            }
        }
        if ((bool) config('workcore.assets.routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}
