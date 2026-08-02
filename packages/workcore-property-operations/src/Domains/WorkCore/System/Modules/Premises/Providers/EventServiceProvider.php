<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
                \App\Domains\WorkCore\System\Modules\Premises\Events\PropertyVisitCreated::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PropertyInspectionCompleted::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\NotifyPropertyContacts::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PropertyDocumentUploaded::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PropertyApprovalRequested::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\NotifyPropertyContacts::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseAccessItemChanged::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseConditionCreated::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseConditionStatusChanged::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseIncidentCreated::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseIncidentStatusChanged::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseIncidentWorkLinked::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseWorkflowCreated::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseWorkflowStatusChanged::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseWorkflowStageAdvanced::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseApplicationSubmitted::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseApplicationStatusChanged::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseApplicationConverted::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseMessageCreated::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseNoticeStatusChanged::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseSelfServiceRequestCreated::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
        \App\Domains\WorkCore\System\Modules\Premises\Events\PremiseSelfServiceRequestWorkLinked::class => [
            \App\Domains\WorkCore\System\Modules\Premises\Listeners\LogPropertyEvent::class,
        ],
    ];
}
