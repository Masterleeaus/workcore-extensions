<?php

declare(strict_types=1);
namespace App\Domains\WorkCore\System\Modules\Support\Providers;
use App\Domains\WorkCore\System\Actions\{ActionDefinition,BusinessActionRegistry};
use App\Domains\WorkCore\System\Contracts\Records\SupportTicketAccessContract;
use App\Domains\WorkCore\System\Contracts\Records\Adapters\SupportTicketAccessAdapter;
use App\Domains\WorkCore\System\Modules\Support\Actions\{AddSupportTicketMessage,AssignSupportTicket,ChangeSupportTicketStatus,ConvertSupportTicketToWorkOrder,CreateSupportQueue,CreateSupportTicket,SearchSupportTickets};
use App\Domains\WorkCore\System\Modules\Support\Contracts\SupportTicketRepositoryContract;
use App\Domains\WorkCore\System\Modules\Support\Repositories\EloquentSupportTicketRepository;
use App\Domains\WorkCore\System\ReadModels\{ReadModelDefinition,ReadModelRegistry};
use Illuminate\Support\ServiceProvider;
final class WorkSupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SupportTicketRepositoryContract::class,EloquentSupportTicketRepository::class);
        $this->app->bind(SupportTicketAccessContract::class,SupportTicketAccessAdapter::class);
        $a=$this->app->make(BusinessActionRegistry::class);
        $a->register(new ActionDefinition('workcore.support_queue.create',CreateSupportQueue::class,'high',true,'workcore.support',(string)config('workcore.support.permissions.manage_queues')));
        $a->register(new ActionDefinition('workcore.support_ticket.create',CreateSupportTicket::class,'medium',true,'workcore.support',(string)config('workcore.support.permissions.create')));
        $a->register(new ActionDefinition('workcore.support_ticket.message.add',AddSupportTicketMessage::class,'low',true,'workcore.support',(string)config('workcore.support.permissions.reply')));
        $a->register(new ActionDefinition('workcore.support_ticket.assign',AssignSupportTicket::class,'medium',true,'workcore.support',(string)config('workcore.support.permissions.assign')));
        $a->register(new ActionDefinition('workcore.support_ticket.change_status',ChangeSupportTicketStatus::class,'medium',true,'workcore.support',(string)config('workcore.support.permissions.status')));
        $a->register(new ActionDefinition('workcore.support_ticket.convert_to_work_order',ConvertSupportTicketToWorkOrder::class,'high',true,'workcore.support',(string)config('workcore.support.permissions.convert_to_work_order')));
        $this->app->make(ReadModelRegistry::class)->register(new ReadModelDefinition('workcore.support_ticket.search',SearchSupportTickets::class,'workcore.support', permission: (string) config('workcore.support.permissions.view')));
    }
    public function boot(): void{if((bool)config('workcore.support.routes_enabled',false))$this->loadRoutesFrom(__DIR__.'/../routes/api.php');}
}
