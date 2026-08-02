<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Dispatch\Providers;

use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\Adapters\DispatchAssignmentAccessAdapter;
use App\Domains\WorkCore\System\Contracts\Records\DispatchAssignmentAccessContract;
use App\Domains\WorkCore\System\Modules\Dispatch\Actions\{ChangeDispatchStatus,CreateDispatchAssignment,ReassignDispatchAssignment,SearchDispatchBoard};
use App\Domains\WorkCore\System\Modules\Dispatch\Contracts\DispatchRepositoryContract;
use App\Domains\WorkCore\System\Modules\Dispatch\Repositories\EloquentDispatchRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;

final class WorkDispatchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DispatchRepositoryContract::class, EloquentDispatchRepository::class);
        $this->app->bind(DispatchAssignmentAccessContract::class, DispatchAssignmentAccessAdapter::class);
        $actions = $this->app->make(BusinessActionRegistry::class);
        $actions->register(new ActionDefinition('workcore.dispatch.assign', CreateDispatchAssignment::class, 'medium', true, 'workcore.dispatch', (string) config('workcore.dispatch.permissions.assign')));
        $actions->register(new ActionDefinition('workcore.dispatch.reassign', ReassignDispatchAssignment::class, 'medium', true, 'workcore.dispatch', (string) config('workcore.dispatch.permissions.reassign')));
        $actions->register(new ActionDefinition('workcore.dispatch.change_status', ChangeDispatchStatus::class, 'medium', true, 'workcore.dispatch', (string) config('workcore.dispatch.permissions.status')));
        $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition('workcore.dispatch.search', SearchDispatchBoard::class, 'workcore.dispatch', permission: (string) config('workcore.dispatch.permissions.view')));
    }

    public function boot(): void
    {
        if ((bool) config('workcore.dispatch.routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}
