<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Modules\Scheduling\Providers;

use App\Domains\WorkCore\System\Actions\ActionDefinition;
use App\Domains\WorkCore\System\Actions\BusinessActionRegistry;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\AppointmentAccessAdapter;
use App\Domains\WorkCore\System\Contracts\Records\AppointmentAccessContract;
use App\Domains\WorkCore\System\Modules\Scheduling\Actions\ChangeAppointmentStatus;
use App\Domains\WorkCore\System\Modules\Scheduling\Actions\CreateAppointment;
use App\Domains\WorkCore\System\Modules\Scheduling\Actions\RescheduleAppointment;
use App\Domains\WorkCore\System\Modules\Scheduling\Actions\SearchAppointments;
use App\Domains\WorkCore\System\Modules\Scheduling\Contracts\AppointmentRepositoryContract;
use App\Domains\WorkCore\System\Modules\Scheduling\Repositories\EloquentAppointmentRepository;
use App\Domains\WorkCore\System\ReadModels\ReadModelDefinition;
use App\Domains\WorkCore\System\ReadModels\ReadModelRegistry;
use Illuminate\Support\ServiceProvider;

final class WorkSchedulingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AppointmentRepositoryContract::class, EloquentAppointmentRepository::class);
        $this->app->bind(AppointmentAccessContract::class, AppointmentAccessAdapter::class);

        $actions = $this->app->make(BusinessActionRegistry::class);
        $actions->register(new ActionDefinition(
            key: 'workcore.appointment.create',
            handler: CreateAppointment::class,
            risk: 'medium',
            requiresConfirmation: true,
            capability: 'workcore.scheduling',
            permission: (string) config('workcore.scheduling.permissions.create'),
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.appointment.reschedule',
            handler: RescheduleAppointment::class,
            risk: 'medium',
            requiresConfirmation: true,
            capability: 'workcore.scheduling',
            permission: (string) config('workcore.scheduling.permissions.update'),
        ));
        $actions->register(new ActionDefinition(
            key: 'workcore.appointment.change_status',
            handler: ChangeAppointmentStatus::class,
            risk: 'medium',
            requiresConfirmation: true,
            capability: 'workcore.scheduling',
            permission: (string) config('workcore.scheduling.permissions.status'),
        ));
        $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition(
            key: 'workcore.appointment.search',
            handler: SearchAppointments::class,
            capability: 'workcore.scheduling',
            permission: (string) config('workcore.scheduling.permissions.view'),
        ));
    }

    public function boot(): void
    {
        if ((bool) config('workcore.scheduling.routes_enabled', false)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}
