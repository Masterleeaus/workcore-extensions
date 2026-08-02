<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Rosters\Providers;

use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\{AvailabilityAccessContract,RosterAccessContract,ShiftAccessContract};
use App\Domains\WorkCore\System\Contracts\Records\Adapters\{AvailabilityAccessAdapter,RosterAccessAdapter,ShiftAccessAdapter};
use App\Domains\WorkCore\System\Modules\Rosters\Actions\{AssignRosterShift,CreateAvailabilityRule,CreateRoster,CreateRosterShift,PublishRoster,SearchAvailability,SearchRosters};
use App\Domains\WorkCore\System\Modules\Rosters\Contracts\RosterRepositoryContract;
use App\Domains\WorkCore\System\Modules\Rosters\Repositories\EloquentRosterRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;

final class WorkRostersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RosterRepositoryContract::class, EloquentRosterRepository::class);
        $this->app->bind(AvailabilityAccessContract::class, AvailabilityAccessAdapter::class);
        $this->app->bind(RosterAccessContract::class, RosterAccessAdapter::class);
        $this->app->bind(ShiftAccessContract::class, ShiftAccessAdapter::class);

        $actions = $this->app->make(BusinessActionRegistry::class);
        $actions->register(new ActionDefinition('workcore.availability_rule.create', CreateAvailabilityRule::class, 'low', true, 'workcore.rosters', (string) config('workcore.rosters.permissions.availability')));
        $actions->register(new ActionDefinition('workcore.roster.create', CreateRoster::class, 'medium', true, 'workcore.rosters', (string) config('workcore.rosters.permissions.manage')));
        $actions->register(new ActionDefinition('workcore.roster_shift.create', CreateRosterShift::class, 'medium', true, 'workcore.rosters', (string) config('workcore.rosters.permissions.manage')));
        $actions->register(new ActionDefinition('workcore.roster_shift.assign', AssignRosterShift::class, 'medium', true, 'workcore.rosters', (string) config('workcore.rosters.permissions.assign')));
        $actions->register(new ActionDefinition('workcore.roster.publish', PublishRoster::class, 'high', true, 'workcore.rosters', (string) config('workcore.rosters.permissions.publish')));

        $readModels = $this->app->make(ReadModelRegistry::class);
        $readModels->register(new ReadModelDefinition('workcore.roster.search', SearchRosters::class, 'workcore.rosters', permission: (string) config('workcore.rosters.permissions.view')));
        $readModels->register(new ReadModelDefinition('workcore.availability.search', SearchAvailability::class, 'workcore.rosters', permission: (string) config('workcore.rosters.permissions.view')));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../../../Resources/views', 'workcore');
        if ((bool) config('workcore.rosters.routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
        if ((bool) config('workcore.rosters.ui_routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }
    }
}
