<?php

declare(strict_types=1);

namespace App\Domains\WorkCore\System\Intelligence\Execution;

use App\Domains\WorkCore\System\Queue\WorkCoreContextPayloadFactory;
use App\Domains\WorkCore\System\Queue\WorkCoreJob;
use InvalidArgumentException;

abstract class TenantScopedIntelligenceJob extends WorkCoreJob
{
    public function __construct(
        public readonly string $taskKey,
        ?WorkCoreContextPayloadFactory $contextFactory = null,
    ) {
        if (trim($taskKey) === '') {
            throw new InvalidArgumentException('An intelligence task key is required.');
        }
        parent::__construct($contextFactory);
    }
}
