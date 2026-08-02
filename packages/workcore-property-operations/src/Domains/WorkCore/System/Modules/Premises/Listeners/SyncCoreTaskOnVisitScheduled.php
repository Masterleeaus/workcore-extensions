<?php
namespace App\Domains\WorkCore\System\Modules\Premises\Listeners;

use App\Domains\WorkCore\System\Modules\Premises\Events\PropertyVisitScheduled;
use App\Domains\WorkCore\System\Modules\Premises\Integrations\Core\TaskAdapterInterface;
use App\Domains\WorkCore\System\Modules\Premises\Integrations\Core\HrAdapterInterface;

class SyncCoreTaskOnVisitScheduled
{
    public function __construct(
        protected TaskAdapterInterface $tasks,
        protected HrAdapterInterface $hr
    ) {}

    public function handle(PropertyVisitScheduled $event): void
    {
        // Optional: create/update a core task
        $this->tasks->upsertTaskForVisit($event->visit);

        // Optional: inform HR roster/assignment
        $this->hr->reflectAssignment($event->visit);
    }
}
