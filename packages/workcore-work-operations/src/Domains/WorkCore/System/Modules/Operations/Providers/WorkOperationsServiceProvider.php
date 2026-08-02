<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Operations\Providers;

use App\Domains\WorkCore\System\Actions\ActionDefinition;
use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\TaskAccessAdapter;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\WorkOrderAccessAdapter;
use App\Domains\WorkCore\System\Contracts\Records\TaskAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\WorkOrderAccessContract;
use App\Domains\WorkCore\System\Modules\Operations\Actions\AddTimeEntry;
use App\Domains\WorkCore\System\Modules\Operations\Actions\ChangeWorkOrderStatus;
use App\Domains\WorkCore\System\Modules\Operations\Actions\CreateWorkOrder;
use App\Domains\WorkCore\System\Modules\Operations\Actions\SearchWorkOrders;
use App\Domains\WorkCore\System\Modules\Operations\Actions\UpdateWorkOrderTaskStatus;
use App\Domains\WorkCore\System\Modules\Operations\Contracts\WorkOrderRepositoryContract;
use App\Domains\WorkCore\System\Modules\Operations\Repositories\EloquentWorkOrderRepository;
use App\Domains\WorkCore\System\ReadModels\ReadModelDefinition;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use Illuminate\Support\ServiceProvider;

final class WorkOperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WorkOrderRepositoryContract::class, EloquentWorkOrderRepository::class);
        $this->app->bind(WorkOrderAccessContract::class, WorkOrderAccessAdapter::class);
        $this->app->bind(TaskAccessContract::class, TaskAccessAdapter::class);

        $actions = $this->app->make(BusinessActionRegistry::class);
        $actions->register(new ActionDefinition(
            key: 'workcore.work_order.create',
            handler: CreateWorkOrder::class,
            risk: 'medium',
            requiresConfirmation: true,
            capability: 'workcore.operations',
            permission: (string) config('workcore.operations.permissions.create'),
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.work_order.change_status',
            handler: ChangeWorkOrderStatus::class,
            risk: 'high',
            requiresConfirmation: true,
            capability: 'workcore.operations',
            permission: (string) config('workcore.operations.permissions.status'),
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.work_order_task.update_status',
            handler: UpdateWorkOrderTaskStatus::class,
            risk: 'medium',
            requiresConfirmation: true,
            capability: 'workcore.operations',
            permission: (string) config('workcore.operations.permissions.tasks_update'),
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.time_entry.add',
            handler: AddTimeEntry::class,
            risk: 'medium',
            requiresConfirmation: true,
            capability: 'workcore.operations',
            permission: (string) config('workcore.operations.permissions.time_entries_create'),
        ));

        $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition(
            key: 'workcore.work_order.search',
            handler: SearchWorkOrders::class,
            capability: 'workcore.operations',
            permission: (string) config('workcore.operations.permissions.view'),
        ));
    }

    public function boot(): void
    {
        if ((bool) config('workcore.operations.routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}
