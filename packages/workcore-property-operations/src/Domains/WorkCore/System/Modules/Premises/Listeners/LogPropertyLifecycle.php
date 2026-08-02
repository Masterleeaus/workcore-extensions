<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Listeners;

use Illuminate\Support\Facades\Log;
use App\Domains\WorkCore\System\Modules\Premises\Events\PropertyCreated;
use App\Domains\WorkCore\System\Modules\Premises\Events\PropertyUpdated;

class LogPropertyLifecycle
{
    public function handle(PropertyCreated|PropertyUpdated $event): void
    {
        Log::info('ManagedPremises lifecycle event', [
            'event' => get_class($event),
            'property_id' => $event->property->id,
            'company_id' => $event->property->company_id,
        ]);
    }
}
