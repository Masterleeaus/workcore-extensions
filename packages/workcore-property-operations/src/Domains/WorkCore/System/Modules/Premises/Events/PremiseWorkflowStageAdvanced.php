<?php

namespace App\Domains\WorkCore\System\Modules\Premises\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Domains\WorkCore\System\Modules\Premises\Entities\PremiseWorkflow;

class PremiseWorkflowStageAdvanced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PremiseWorkflow $workflow,
        public ?string $completedStage,
        public ?string $nextStage
    ) {}
}
